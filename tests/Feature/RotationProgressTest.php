<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Services\RotationProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class RotationProgressTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    public function test_only_approved_cases_count_toward_targets(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        $this->addTarget($rotation, null, 6, $faculty);
        $this->addTarget($rotation, 'Cardiology', 3, $faculty);

        foreach ([CaseStatus::Draft, CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Returned] as $status) {
            $this->makeCase($assignment, $status, 'Cardiology');
        }
        $this->makeCase($assignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($assignment, CaseStatus::Approved, ' cardiology ');

        $this->actingAs($student);
        $entry = app(RotationProgressService::class)->forStudent($student)->sole();

        $this->assertSame(['approved' => 2, 'target' => 6], $entry['overall']);
        $this->assertSame([['category' => 'Cardiology', 'approved' => 2, 'target' => 3]], $entry['categories']);
    }

    public function test_students_on_the_same_rotation_are_counted_independently(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $asha = $this->makeStudent($rotation, 'Asha');
        $vikram = $this->makeStudent($rotation, 'Vikram');
        $ashaAssignment = $this->assign($rotation, $asha, $faculty);
        $vikramAssignment = $this->assign($rotation, $vikram, $faculty);
        $this->addTarget($rotation, 'Cardiology', 3, $faculty);

        $this->makeCase($ashaAssignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($ashaAssignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($vikramAssignment, CaseStatus::Approved, 'Cardiology');

        $this->actingAs($faculty);
        $byStudent = app(RotationProgressService::class)->forFaculty($faculty)->keyBy(fn (array $e) => $e['student']['name']);

        $this->assertSame(2, $byStudent['Asha']['categories'][0]['approved']);
        $this->assertSame(1, $byStudent['Vikram']['categories'][0]['approved']);
        $this->assertSame(3, $byStudent['Vikram']['categories'][0]['target']);
    }

    public function test_a_category_with_approved_cases_but_no_target_is_kept_and_uncategorised_cases_count_toward_overall_only(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        $this->addTarget($rotation, 'Nephrology', 2, $faculty);

        $this->makeCase($assignment, CaseStatus::Approved, 'Legacy free text');
        $this->makeCase($assignment, CaseStatus::Approved, null);

        $this->actingAs($student);
        $entry = app(RotationProgressService::class)->forStudent($student)->sole();

        $this->assertSame(['approved' => 2, 'target' => null], $entry['overall']);
        $this->assertSame([
            ['category' => 'Nephrology', 'approved' => 0, 'target' => 2],
            ['category' => 'Legacy free text', 'approved' => 1, 'target' => null],
        ], $entry['categories']);
        $this->assertSame(1, $entry['uncategorised_approved']);
    }

    public function test_student_progress_page_shows_only_the_students_own_assignments(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation);
        $student = $this->makeStudent($rotation, 'Asha');
        $own = $this->assign($rotation, $student, $faculty);
        $this->assign($rotation, $this->makeStudent($rotation, 'Classmate'), $faculty);

        $this->actingAs($student)->get('/student/progress')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/Progress')
                ->has('assignments', 1)
                ->where('assignments.0.assignment_id', $own->id));
    }

    public function test_faculty_progress_page_shows_only_students_assigned_to_that_faculty_member(): void
    {
        $rotation = $this->makeRotation();
        $faculty = $this->makeFaculty($rotation, 'Dr Mine');
        $colleague = $this->makeFaculty($rotation, 'Dr Colleague');
        $mine = $this->assign($rotation, $this->makeStudent($rotation, 'Mine'), $faculty);
        $this->assign($rotation, $this->makeStudent($rotation, 'Theirs'), $colleague);
        $foreign = $this->makeRotation(null, 'Foreign');
        $this->assign($foreign, $this->makeStudent($foreign), $this->makeFaculty($foreign));

        $this->actingAs($faculty)->get('/faculty/progress')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('faculty/Progress')
                ->has('assignments', 1)
                ->where('assignments.0.assignment_id', $mine->id));
    }

    public function test_admin_progress_is_institution_scoped_and_sends_rotation_filter_data(): void
    {
        $medicine = $this->makeRotation(null, 'Medicine');
        $institution = $this->institutionOf($medicine);
        $surgery = $this->makeRotation($institution, 'Surgery');
        $faculty = $this->makeFaculty($medicine);
        $this->assign($medicine, $this->makeStudent($medicine, 'A'), $faculty);
        $this->assign($surgery, $this->makeStudent($medicine, 'B'), $faculty);
        $foreign = $this->makeRotation(null, 'Foreign');
        $this->assign($foreign, $this->makeStudent($foreign), $this->makeFaculty($foreign));

        $this->actingAs($this->makeAdministrator($medicine))->get('/admin/progress')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Progress')
                ->where('rotations', [['id' => $medicine->id, 'name' => 'Medicine'], ['id' => $surgery->id, 'name' => 'Surgery']])
                ->where('selectedRotationId', null)
                ->has('assignments', 2)
                ->where('approvedCases', []));
    }

    public function test_admin_rotation_filter_narrows_progress_and_lists_that_rotations_approved_cases(): void
    {
        $medicine = $this->makeRotation(null, 'Medicine');
        $surgery = $this->makeRotation($this->institutionOf($medicine), 'Surgery');
        $faculty = $this->makeFaculty($medicine);
        $medAssignment = $this->assign($medicine, $this->makeStudent($medicine, 'Asha'), $faculty);
        $surgAssignment = $this->assign($surgery, $this->makeStudent($medicine, 'Vikram'), $faculty);
        $approved = $this->makeCase($medAssignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($medAssignment, CaseStatus::Submitted, 'Cardiology');
        $this->makeCase($surgAssignment, CaseStatus::Approved, 'Ortho');

        $this->actingAs($this->makeAdministrator($medicine))->get('/admin/progress?rotation='.$medicine->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedRotationId', $medicine->id)
                ->has('assignments', 1)
                ->where('assignments.0.assignment_id', $medAssignment->id)
                ->has('approvedCases', 1)
                ->where('approvedCases.0.id', $approved->id)
                ->where('approvedCases.0.student_name', 'Asha')
                ->where('approvedCases.0.case_category', 'Cardiology'));
    }

    public function test_an_unknown_or_cross_tenant_rotation_filter_is_a_validation_error_not_an_empty_page(): void
    {
        $rotation = $this->makeRotation();
        $foreign = $this->makeRotation(null, 'Foreign');
        $admin = $this->makeAdministrator($rotation);

        foreach ([$foreign->id, 'not-a-rotation'] as $bad) {
            $this->actingAs($admin)->get('/admin/progress?rotation='.urlencode($bad))
                ->assertRedirect('/admin/progress')
                ->assertSessionHasErrors('rotation');
        }
    }

    public function test_progress_pages_are_role_restricted(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $admin = $this->makeAdministrator($rotation);

        $this->actingAs($faculty)->get('/student/progress')->assertForbidden();
        $this->actingAs($admin)->get('/student/progress')->assertForbidden();
        $this->actingAs($student)->get('/faculty/progress')->assertForbidden();
        $this->actingAs($admin)->get('/faculty/progress')->assertForbidden();
        $this->actingAs($student)->get('/admin/progress')->assertForbidden();
        $this->actingAs($faculty)->get('/admin/progress')->assertForbidden();
    }

    public function test_student_and_faculty_dashboards_show_real_numbers(): void
    {
        $rotation = $this->makeRotation(null, 'General Medicine');
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        $this->addTarget($rotation, null, 6, $faculty);
        $this->makeCase($assignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($assignment, CaseStatus::Approved, 'Cardiology');
        $this->makeCase($assignment, CaseStatus::Submitted);
        $this->makeCase($assignment, CaseStatus::UnderReview);
        $this->makeCase($assignment, CaseStatus::Draft);

        $this->actingAs($student)->get('/student')
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.0.value', 'General Medicine')
                ->where('metrics.1.value', '2 / 6')
                ->where('metrics.2.value', '2'));

        $this->actingAs($faculty)->get('/faculty')
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.0.value', '2')
                ->where('metrics.1.value', '1')
                ->where('metrics.1.detail', 'General Medicine'));
    }

    public function test_student_dashboard_without_an_active_assignment_says_so(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);

        $this->actingAs($student)->get('/student')
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.0.value', 'None')
                ->where('metrics.1.value', '—')
                ->where('metrics.2.value', '0'));
    }
}
