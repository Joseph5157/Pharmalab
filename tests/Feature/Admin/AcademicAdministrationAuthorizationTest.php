<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicAdministrationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_open_or_mutate_administration(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $this->get(route('admin.academic.index'))->assertForbidden();
        $this->get(route('admin.people.index'))->assertForbidden();
        $this->get(route('admin.clinical-sites.index'))->assertForbidden();
        $this->get(route('admin.rotations.index'))->assertForbidden();
        $this->post(route('admin.programmes.store'), ['name' => 'No access', 'code' => 'NO', 'duration_years' => 4])->assertForbidden();
        $this->assertDatabaseMissing('programmes', ['code' => 'NO']);
    }

    public function test_cross_institution_relationships_are_rejected(): void
    {
        $first = Institution::factory()->create();
        $second = Institution::factory()->create();
        $administrator = User::factory()->administrator()->create(['institution_id' => $first->id]);
        $otherProgramme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $second->id, 'name' => 'Other', 'code' => 'OTH', 'duration_years' => 4, 'status' => 'active']);
        $otherSite = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $second->id, 'name' => 'Other Hospital', 'code' => 'OH', 'status' => 'active']);
        $this->actingAs($administrator);

        $this->post(route('admin.cohorts.store'), ['programme_id' => $otherProgramme->id, 'name' => 'Foreign', 'admission_year' => 2026, 'academic_year_label' => '2026-27'])
            ->assertSessionHasErrors('programme_id');

        $this->post(route('admin.rotations.store'), ['name' => 'Foreign', 'programme_id' => $otherProgramme->id, 'clinical_site_id' => $otherSite->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'draft'])
            ->assertSessionHasErrors(['programme_id', 'clinical_site_id']);

        $this->assertDatabaseMissing('academic_cohorts', ['name' => 'Foreign']);
        $this->assertDatabaseMissing('rotations', ['name' => 'Foreign']);
    }

    public function test_assignment_requires_active_correctly_qualified_same_institution_users(): void
    {
        [$administrator, $rotation] = $this->administratorAndRotation();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $wrongRole = User::factory()->student()->create(['institution_id' => $administrator->institution_id]);
        $inactiveFaculty = User::factory()->faculty()->create(['institution_id' => $administrator->institution_id, 'status' => 'inactive']);
        $this->actingAs($administrator);

        $this->post(route('admin.rotation-assignments.store', $rotation), ['student_id' => $otherStudent->id, 'faculty_id' => $wrongRole->id])
            ->assertSessionHasErrors(['student_id', 'faculty_id']);
        $this->post(route('admin.rotation-assignments.store', $rotation), ['student_id' => $wrongRole->id, 'faculty_id' => $inactiveFaculty->id])
            ->assertSessionHasErrors('faculty_id');

        $this->assertDatabaseCount('rotation_assignments', 0);
    }

    public function test_cross_institution_route_binding_hides_records(): void
    {
        [$administrator] = $this->administratorAndRotation();
        $otherInstitution = Institution::factory()->create();
        $otherUser = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $this->actingAs($administrator)
            ->patch(route('admin.people.status', $otherUser), ['status' => 'inactive'])
            ->assertNotFound();
    }

    public static function nonAdministratorRoles(): array
    {
        return [[UserRole::Student], [UserRole::Faculty]];
    }

    /** @return array{User, Rotation} */
    private function administratorAndRotation(): array
    {
        $institution = Institution::factory()->create();
        $administrator = User::factory()->administrator()->create(['institution_id' => $institution->id]);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'draft']);

        return [$administrator, $rotation];
    }
}
