<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\RotationCaseTarget;
use App\Policies\RotationCaseTargetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class RotationCaseTargetTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    public function test_the_manage_ability_resolves_to_the_target_policy_not_the_rotation_policy(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation), $faculty);

        $this->assertInstanceOf(RotationCaseTargetPolicy::class, Gate::getPolicyFor(RotationCaseTarget::class));
        $this->assertTrue(Gate::forUser($faculty)->allows('manage', [RotationCaseTarget::class, $rotation]));
        // Passing only the rotation reaches RotationPolicy, which has no
        // `manage` ability — the wiring mistake this test guards against.
        $this->assertFalse(Gate::forUser($faculty)->allows('manage', $rotation));
    }

    public function test_assigned_faculty_can_create_update_and_delete_targets_with_audit(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation), $faculty);
        $this->actingAs($faculty);

        $this->from('/faculty/rotations')->post("/faculty/rotations/{$rotation->id}/targets", ['category' => null, 'target_count' => 6])
            ->assertRedirect('/faculty/rotations')->assertSessionHasNoErrors();
        $this->post("/faculty/rotations/{$rotation->id}/targets", ['category' => '  Cardiology ', 'target_count' => 3])
            ->assertSessionHasNoErrors();

        $category = RotationCaseTarget::query()->where('category', 'Cardiology')->firstOrFail();
        $this->assertSame('cardiology', $category->fresh()->category_key);
        $this->assertSame(2, RotationCaseTarget::query()->where('rotation_id', $rotation->id)->count());

        $this->patch("/faculty/rotations/{$rotation->id}/targets/{$category->id}", ['category' => 'Cardiology', 'target_count' => 4])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $category->fresh()->target_count);

        $this->delete("/faculty/rotations/{$rotation->id}/targets/{$category->id}")->assertSessionHasNoErrors();
        $this->assertNull(RotationCaseTarget::query()->find($category->id));

        $events = AuditEvent::query()->where('auditable_type', RotationCaseTarget::class)->pluck('event_type')->all();
        $this->assertSame(['rotation_case_target.created', 'rotation_case_target.created', 'rotation_case_target.updated', 'rotation_case_target.deleted'], $events);
    }

    public function test_any_currently_assigned_faculty_may_edit_a_target_another_faculty_created(): void
    {
        $rotation = $this->makeRotation();
        $creator = $this->makeFaculty($rotation, 'Dr Creator');
        $colleague = $this->makeFaculty($rotation, 'Dr Colleague');
        $this->assign($rotation, $this->makeStudent($rotation, 'One'), $creator);
        $this->assign($rotation, $this->makeStudent($rotation, 'Two'), $colleague);
        $target = $this->addTarget($rotation, 'Nephrology', 2, $creator);

        $this->actingAs($colleague)
            ->patch("/faculty/rotations/{$rotation->id}/targets/{$target->id}", ['category' => 'Nephrology', 'target_count' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $target->fresh()->target_count);
    }

    public function test_faculty_without_an_active_assignment_on_the_rotation_cannot_manage_targets(): void
    {
        $rotation = $this->makeRotation();
        $other = $this->makeRotation($this->institutionOf($rotation), 'Paediatrics');
        $elsewhere = $this->makeFaculty($rotation, 'Dr Elsewhere');
        $inactive = $this->makeFaculty($rotation, 'Dr Inactive');
        $this->assign($other, $this->makeStudent($rotation, 'Other ward'), $elsewhere);
        $this->assign($rotation, $this->makeStudent($rotation, 'Past'), $inactive, 'inactive');

        foreach ([$elsewhere, $inactive] as $faculty) {
            $this->actingAs($faculty)
                ->post("/faculty/rotations/{$rotation->id}/targets", ['category' => null, 'target_count' => 6])
                ->assertForbidden();
        }

        $this->assertSame(0, RotationCaseTarget::query()->withoutGlobalScopes()->count());
    }

    public function test_cross_institution_faculty_cannot_reach_another_institutions_rotation(): void
    {
        $rotation = $this->makeRotation();
        $foreign = $this->makeRotation(null, 'Foreign rotation');
        $foreignFaculty = $this->makeFaculty($foreign);
        $this->assign($foreign, $this->makeStudent($foreign), $foreignFaculty);
        $target = $this->addTarget($rotation, 'Cardiology', 3, $this->makeFaculty($rotation));

        // The institution global scope hides the rotation entirely: 404, not 403.
        $this->actingAs($foreignFaculty)
            ->post("/faculty/rotations/{$rotation->id}/targets", ['category' => null, 'target_count' => 6])
            ->assertNotFound();
        $this->actingAs($foreignFaculty)
            ->patch("/faculty/rotations/{$rotation->id}/targets/{$target->id}", ['category' => 'X', 'target_count' => 1])
            ->assertNotFound();

        $this->assertSame(3, RotationCaseTarget::query()->withoutGlobalScopes()->find($target->id)->target_count);
    }

    public function test_students_and_administrators_cannot_manage_targets_and_administrators_can_view(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $admin = $this->makeAdministrator($rotation);
        $this->assign($rotation, $student, $this->makeFaculty($rotation));

        $this->actingAs($student)->post("/faculty/rotations/{$rotation->id}/targets", ['category' => null, 'target_count' => 6])->assertForbidden();
        $this->actingAs($admin)->post("/faculty/rotations/{$rotation->id}/targets", ['category' => null, 'target_count' => 6])->assertForbidden();

        $this->assertTrue(Gate::forUser($admin)->allows('view', [RotationCaseTarget::class, $rotation]));
        $this->assertFalse(Gate::forUser($admin)->allows('manage', [RotationCaseTarget::class, $rotation]));
        $this->assertFalse(Gate::forUser($this->makeAdministrator($this->makeRotation(null, 'Other')))->allows('view', [RotationCaseTarget::class, $rotation]));
    }

    public function test_a_target_id_from_another_rotation_is_not_found_through_this_rotation(): void
    {
        $rotation = $this->makeRotation();
        $other = $this->makeRotation($this->institutionOf($rotation), 'Surgery');
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation, 'A'), $faculty);
        $otherTarget = $this->addTarget($other, 'Orthopaedics', 2, $this->makeFaculty($rotation, 'Dr Other'));

        // Faculty may manage $rotation but not $other; the scoped binding must
        // stop them editing $other's target through $rotation's URL.
        $this->actingAs($faculty)
            ->patch("/faculty/rotations/{$rotation->id}/targets/{$otherTarget->id}", ['category' => 'Hijacked', 'target_count' => 9])
            ->assertNotFound();
        $this->actingAs($faculty)
            ->delete("/faculty/rotations/{$rotation->id}/targets/{$otherTarget->id}")
            ->assertNotFound();

        $this->assertSame('Orthopaedics', $otherTarget->fresh()->category);
    }

    public function test_duplicate_categories_and_a_second_overall_target_are_clean_validation_errors(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation), $faculty);
        $this->addTarget($rotation, null, 6, $faculty);
        $cardiology = $this->addTarget($rotation, 'Cardiology', 3, $faculty);
        $neuro = $this->addTarget($rotation, 'Neurology', 2, $faculty);
        $this->actingAs($faculty);

        foreach (['Cardiology', ' cardiology ', 'CARDIOLOGY'] as $variant) {
            $this->from('/faculty/rotations')->post("/faculty/rotations/{$rotation->id}/targets", ['category' => $variant, 'target_count' => 1])
                ->assertRedirect('/faculty/rotations')
                ->assertSessionHasErrors(['category' => 'This rotation already has a target for that category. Edit it instead.']);
        }

        $this->post("/faculty/rotations/{$rotation->id}/targets", ['category' => '', 'target_count' => 4])
            ->assertSessionHasErrors(['category' => 'This rotation already has an overall target. Edit it instead.']);

        // Renaming one category onto another is the same collision.
        $this->patch("/faculty/rotations/{$rotation->id}/targets/{$neuro->id}", ['category' => 'cardiology', 'target_count' => 2])
            ->assertSessionHasErrors('category');
        // Re-saving a target with its own name is not a duplicate.
        $this->patch("/faculty/rotations/{$rotation->id}/targets/{$cardiology->id}", ['category' => 'Cardiology', 'target_count' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, RotationCaseTarget::query()->where('rotation_id', $rotation->id)->count());
    }

    public function test_a_concurrent_duplicate_that_slips_past_the_pre_check_is_still_a_validation_error(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation), $faculty);

        // Simulate a second request committing the same category between this
        // request's pre-check and its insert.
        $raced = false;
        RotationCaseTarget::creating(function () use (&$raced, $rotation, $faculty): void {
            if ($raced) {
                return;
            }
            $raced = true;
            DB::table('rotation_case_targets')->insert([
                'id' => (string) Str::ulid(),
                'institution_id' => $rotation->institution_id,
                'rotation_id' => $rotation->id,
                'category' => 'Cardiology',
                'target_count' => 3,
                'created_by' => $faculty->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($faculty)
            ->from('/faculty/rotations')
            ->post("/faculty/rotations/{$rotation->id}/targets", ['category' => 'cardiology', 'target_count' => 2])
            ->assertRedirect('/faculty/rotations')
            ->assertSessionHasErrors(['category' => 'This rotation already has a target for that category. Edit it instead.']);

        $this->assertTrue($raced);
        // The simulated competing row is written on the same connection, so it
        // rolls back with this request's transaction; what matters is that
        // this request's own row was never kept.
        $this->assertSame(0, RotationCaseTarget::query()->where('rotation_id', $rotation->id)->where('target_count', 2)->count());
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'rotation_case_target.created')->count());
    }

    public function test_target_count_is_validated(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation), $faculty);
        $this->actingAs($faculty);

        foreach ([0, -1, 1000, 'many', null] as $count) {
            $this->post("/faculty/rotations/{$rotation->id}/targets", ['category' => 'X', 'target_count' => $count])
                ->assertSessionHasErrors('target_count');
        }
        $this->post("/faculty/rotations/{$rotation->id}/targets", ['category' => str_repeat('a', 81), 'target_count' => 1])
            ->assertSessionHasErrors('category');
    }

    public function test_rotations_page_lists_only_the_faculty_members_active_rotations(): void
    {
        $rotation = $this->makeRotation();
        $institution = $this->institutionOf($rotation);
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $this->makeStudent($rotation, 'A'), $faculty);
        $this->assign($rotation, $this->makeStudent($rotation, 'B'), $faculty);
        $past = $this->makeRotation($institution, 'Past rotation');
        $this->assign($past, $this->makeStudent($rotation, 'C'), $faculty, 'inactive');
        $someoneElses = $this->makeRotation($institution, 'Someone else');
        $this->assign($someoneElses, $this->makeStudent($rotation, 'D'), $this->makeFaculty($rotation, 'Dr Else'));
        $this->addTarget($rotation, 'Cardiology', 3, $faculty);
        $this->addTarget($rotation, null, 6, $faculty);

        $this->actingAs($faculty)->get('/faculty/rotations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('faculty/Rotations')
                ->has('rotations', 1)
                ->where('rotations.0.id', $rotation->id)
                ->where('rotations.0.active_students_count', 2)
                ->where('rotations.0.targets.0.category', null)
                ->where('rotations.0.targets.0.target_count', 6)
                ->where('rotations.0.targets.1.category', 'Cardiology'));
    }

    public function test_the_rotations_page_is_faculty_only(): void
    {
        $rotation = $this->makeRotation();

        $this->actingAs($this->makeStudent($rotation))->get('/faculty/rotations')->assertForbidden();
        $this->actingAs($this->makeAdministrator($rotation))->get('/faculty/rotations')->assertForbidden();
    }
}
