<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AcademicCohort;
use App\Models\ClinicalSite;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_the_structure_accounts_rotation_and_assignment(): void
    {
        $institution = Institution::factory()->create();
        $administrator = User::factory()->administrator()->create(['institution_id' => $institution->id]);
        $this->actingAs($administrator);

        $this->post(route('admin.programmes.store'), [
            'name' => 'Doctor of Pharmacy',
            'code' => 'pharmd',
            'duration_years' => 6,
        ])->assertRedirect();
        $programme = Programme::query()->sole();

        $this->post(route('admin.cohorts.store'), [
            'programme_id' => $programme->id,
            'name' => '2026 intake',
            'admission_year' => 2026,
            'academic_year_label' => '2026-27',
        ])->assertRedirect();
        $cohort = AcademicCohort::query()->sole();

        $this->post(route('admin.clinical-sites.store'), [
            'name' => 'City Teaching Hospital',
            'code' => 'cth',
        ])->assertRedirect();
        $site = ClinicalSite::query()->sole();

        $this->post(route('admin.departments.store'), [
            'clinical_site_id' => $site->id,
            'name' => 'General Medicine',
        ])->assertRedirect();
        $department = Department::query()->sole();

        $this->post(route('admin.wards.store'), [
            'clinical_site_id' => $site->id,
            'department_id' => $department->id,
            'name' => 'Medical Ward 3',
            'code' => 'mw3',
        ])->assertRedirect();
        $ward = Ward::query()->sole();

        $student = $this->createPerson('Student One', 'student.one@example.test', UserRole::Student);
        $faculty = $this->createPerson('Dr Faculty', 'faculty.one@example.test', UserRole::Faculty);

        $this->post(route('admin.rotations.store'), [
            'name' => 'General Medicine Block A',
            'programme_id' => $programme->id,
            'academic_cohort_id' => $cohort->id,
            'clinical_site_id' => $site->id,
            'department_id' => $department->id,
            'ward_id' => $ward->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'status' => 'active',
        ])->assertRedirect();
        $rotation = Rotation::query()->sole();

        $this->post(route('admin.rotation-assignments.store', $rotation), [
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
        ])->assertRedirect();

        $assignment = RotationAssignment::query()->sole();
        $this->assertSame($institution->id, $assignment->institution_id);
        $this->assertSame($student->id, $assignment->student_id);
        $this->assertSame($faculty->id, $assignment->primary_preceptor_id);
        $this->assertDatabaseHas('programmes', ['code' => 'PHARMD']);
        $this->assertDatabaseHas('clinical_sites', ['code' => 'CTH']);
        $this->assertDatabaseHas('wards', ['code' => 'MW3']);

        foreach (['programme.created', 'academic_cohort.created', 'clinical_site.created', 'department.created', 'ward.created', 'user.created', 'rotation.created', 'rotation_assignment.created'] as $event) {
            $this->assertDatabaseHas('audit_events', ['institution_id' => $institution->id, 'event_type' => $event]);
        }

        $this->assertDatabaseMissing('audit_events', ['metadata' => 'Password1!']);
    }

    public function test_admin_pages_render_with_institution_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator);

        $pages = [
            ['admin.academic.index', 'admin/Academic'],
            ['admin.clinical-sites.index', 'admin/ClinicalSites'],
            ['admin.people.index', 'admin/People'],
            ['admin.rotations.index', 'admin/Rotations'],
        ];

        foreach ($pages as [$route, $component]) {
            $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_reassigning_a_student_updates_the_existing_assignment(): void
    {
        [$administrator, $rotation] = $this->administratorAndRotation();
        $student = User::factory()->student()->create(['institution_id' => $administrator->institution_id]);
        $firstFaculty = User::factory()->faculty()->create(['institution_id' => $administrator->institution_id]);
        $secondFaculty = User::factory()->faculty()->create(['institution_id' => $administrator->institution_id]);
        $this->actingAs($administrator);

        $this->post(route('admin.rotation-assignments.store', $rotation), ['student_id' => $student->id, 'faculty_id' => $firstFaculty->id])->assertRedirect();
        $this->post(route('admin.rotation-assignments.store', $rotation), ['student_id' => $student->id, 'faculty_id' => $secondFaculty->id])->assertRedirect();

        $this->assertSame(1, RotationAssignment::query()->count());
        $this->assertSame($secondFaculty->id, RotationAssignment::query()->sole()->primary_preceptor_id);
    }

    public function test_account_rotation_and_assignment_status_changes_are_audited(): void
    {
        [$administrator, $rotation] = $this->administratorAndRotation();
        $student = User::factory()->student()->create(['institution_id' => $administrator->institution_id]);
        $faculty = User::factory()->faculty()->create(['institution_id' => $administrator->institution_id]);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $administrator->institution_id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => 'active',
        ]);
        $this->actingAs($administrator);

        $this->patch(route('admin.people.status', $student), ['status' => 'inactive'])->assertRedirect();
        $this->patch(route('admin.rotations.status', $rotation), ['status' => 'active'])->assertRedirect();
        $this->patch(route('admin.rotation-assignments.status', $assignment), ['status' => 'inactive'])->assertRedirect();

        foreach (['user.status_changed', 'rotation.status_changed', 'rotation_assignment.status_changed'] as $event) {
            $this->assertDatabaseHas('audit_events', ['institution_id' => $administrator->institution_id, 'event_type' => $event]);
        }
    }

    private function createPerson(string $name, string $email, UserRole $role): User
    {
        $this->post(route('admin.people.store'), [
            'name' => $name,
            'email' => $email,
            'role' => $role->value,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect();

        return User::query()->where('email', $email)->sole();
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
