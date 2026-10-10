<?php

namespace Tests\Feature;

use App\Models\ClinicalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class NewCaseAssignmentSelectionTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    public function test_the_case_list_sends_only_the_students_own_active_assignments(): void
    {
        $medicine = $this->makeRotation(null, 'General Medicine');
        $institution = $this->institutionOf($medicine);
        $surgery = $this->makeRotation($institution, 'Surgery');
        $past = $this->makeRotation($institution, 'Past rotation');
        $student = $this->makeStudent($medicine);
        $classmate = $this->makeStudent($medicine, 'Classmate');
        $faculty = $this->makeFaculty($medicine);

        $medicineAssignment = $this->assign($medicine, $student, $faculty);
        $surgeryAssignment = $this->assign($surgery, $student, $faculty);
        $this->assign($past, $student, $faculty, 'inactive');
        $this->assign($medicine, $classmate, $faculty);

        $this->actingAs($student)->get('/student/cases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Cases')
                ->has('activeAssignments', 2)
                ->where('activeAssignments.0.id', $medicineAssignment->id)
                ->where('activeAssignments.0.rotation_name', 'General Medicine')
                ->where('activeAssignments.0.clinical_site_name', 'City Hospital')
                ->where('activeAssignments.0.ward_name', 'Ward 3')
                ->where('activeAssignments.1.id', $surgeryAssignment->id));
    }

    public function test_a_student_with_no_active_assignment_receives_an_empty_list(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $this->assign($rotation, $student, $this->makeFaculty($rotation), 'inactive');

        $this->actingAs($student)->get('/student/cases')
            ->assertInertia(fn (Assert $page) => $page->has('activeAssignments', 0));
    }

    public function test_posting_an_active_assignment_id_creates_a_case_on_that_assignment(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $assignment = $this->assign($rotation, $student, $this->makeFaculty($rotation));

        $this->actingAs($student)->post('/student/cases', ['rotation_assignment_id' => $assignment->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($assignment->id, ClinicalCase::query()->where('student_id', $student->id)->value('rotation_assignment_id'));
    }

    public function test_the_unchanged_backend_rule_still_rejects_missing_spoofed_inactive_and_cross_tenant_assignment_ids(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $this->assign($rotation, $student, $faculty);

        $classmateAssignment = $this->assign($this->makeRotation($this->institutionOf($rotation), 'Surgery'), $this->makeStudent($rotation, 'Classmate'), $faculty);
        $inactiveAssignment = $this->assign($this->makeRotation($this->institutionOf($rotation), 'Old'), $student, $faculty, 'inactive');
        $foreign = $this->makeRotation(null, 'Foreign');
        $foreignAssignment = $this->assign($foreign, $this->makeStudent($foreign), $this->makeFaculty($foreign));

        $this->actingAs($student);
        foreach ([null, $classmateAssignment->id, $inactiveAssignment->id, $foreignAssignment->id, '01JUNKJUNKJUNKJUNKJUNKJUNK'] as $id) {
            $this->post('/student/cases', $id === null ? [] : ['rotation_assignment_id' => $id])
                ->assertSessionHasErrors('rotation_assignment_id');
        }

        $this->assertSame(0, ClinicalCase::query()->withoutGlobalScopes()->count());
    }
}
