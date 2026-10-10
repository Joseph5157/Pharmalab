<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\Rotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class CaseCategoryEnforcementTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    public function test_a_configured_category_is_accepted_and_stored_in_the_targets_canonical_casing(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories(['Cardiology', 'Nephrology']);

        $this->sync($student, $case, ['case_category' => '  cardiology '])
            ->assertOk()
            ->assertJsonPath('section.case_category', 'Cardiology')
            ->assertJsonPath('section.case_display.category_options', ['Cardiology', 'Nephrology']);

        $this->assertSame('Cardiology', $case->fresh()->case_category);
    }

    public function test_a_category_outside_the_rotations_list_is_rejected_even_from_a_raw_request(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories(['Cardiology', 'Nephrology']);

        $this->sync($student, $case, ['case_category' => 'Dermatology'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['case_category' => "Choose one of this rotation's categories: Cardiology, Nephrology."]);

        $this->assertNull($case->fresh()->case_category);
    }

    public function test_clearing_the_category_is_always_allowed(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories(['Cardiology']);
        $case->forceFill(['case_category' => 'Cardiology'])->save();

        $this->sync($student, $case, ['case_category' => null])->assertOk()->assertJsonPath('section.case_category', null);
    }

    public function test_free_text_stays_allowed_when_the_rotation_has_no_category_targets(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories([]);

        $this->sync($student, $case, ['case_category' => 'Medication reconciliation'])
            ->assertOk()
            ->assertJsonPath('section.case_category', 'Medication reconciliation')
            ->assertJsonPath('section.case_display.category_options', []);
    }

    public function test_an_overall_target_alone_does_not_restrict_categories(): void
    {
        [$student, $case, $rotation, $faculty] = $this->caseOnRotationWithCategories([]);
        $this->addTarget($rotation, null, 6, $faculty);

        $this->sync($student, $case, ['case_category' => 'Anything at all'])->assertOk();
    }

    public function test_editing_other_fields_does_not_revalidate_an_existing_legacy_category(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories(['Cardiology']);
        $case->forceFill(['case_category' => 'Entered before targets existed'])->save();

        // The real client sends the whole section, including the unchanged
        // legacy category, on every autosave.
        $this->sync($student, $case, ['case_category' => 'Entered before targets existed', 'age_value' => 40, 'age_unit' => 'years'])->assertOk()
            ->assertJsonPath('section.case_category', 'Entered before targets existed')
            ->assertJsonPath('section.age_value', 40);
        $this->sync($student, $case, ['age_value' => 41, 'age_unit' => 'years'])->assertOk();

        // Changing it to another unlisted value is still rejected.
        $this->sync($student, $case, ['case_category' => 'Something else'])->assertUnprocessable();
    }

    public function test_case_creation_applies_the_same_rule(): void
    {
        [$student, , $rotation] = $this->caseOnRotationWithCategories(['Cardiology']);
        $assignmentId = $student->rotationAssignments()->where('rotation_id', $rotation->id)->value('id');
        $this->actingAs($student);

        $this->post('/student/cases', ['rotation_assignment_id' => $assignmentId, 'case_category' => 'Dermatology'])
            ->assertSessionHasErrors('case_category');

        $this->post('/student/cases', ['rotation_assignment_id' => $assignmentId, 'case_category' => 'CARDIOLOGY'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Cardiology', ClinicalCase::query()->where('student_id', $student->id)->latest('case_number')->value('case_category'));
    }

    public function test_the_case_editor_receives_the_rotations_category_options(): void
    {
        [$student, $case] = $this->caseOnRotationWithCategories(['Nephrology', 'Cardiology']);

        $this->actingAs($student)->get("/student/cases/{$case->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('context.case_display.category_options', ['Cardiology', 'Nephrology']));
    }

    /**
     * @param  list<string>  $categories
     * @return array{0: User, 1: ClinicalCase, 2: Rotation, 3: User}
     */
    private function caseOnRotationWithCategories(array $categories): array
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        foreach ($categories as $category) {
            $this->addTarget($rotation, $category, 2, $faculty);
        }

        return [$student, $this->makeCase($assignment, CaseStatus::Draft), $rotation, $faculty];
    }

    /** @param array<string, mixed> $attributes */
    private function sync(User $student, ClinicalCase $case, array $attributes): TestResponse
    {
        return $this->actingAs($student)->putJson(route('student.cases.context.sync', $case), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => $case->fresh()->lock_version,
            ...$attributes,
        ]);
    }
}
