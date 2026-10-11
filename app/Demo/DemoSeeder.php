<?php

namespace App\Demo;

use App\Enums\UserStatus;
use App\Models\AcademicCohort;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\RotationCaseTarget;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the demo dataset. Creates only what is missing; any existing row
 * found by natural key must already match the dataset, otherwise seeding
 * stops with a DemoRefusedException. Existing records are never reassigned.
 *
 * Bootstrap lookups use exact identifiers with withoutGlobalScopes(), because
 * the command starts with no authenticated user.
 */
class DemoSeeder
{
    private string $step = 'starting';

    public function __construct(private readonly DemoCaseBuilder $builder) {}

    public function currentStep(): string
    {
        return $this->step;
    }

    public function seed(#[\SensitiveParameter] string $password): void
    {
        $this->step = 'the demo institution';
        $institution = $this->institution();

        $this->step = 'the demo accounts';
        $users = $this->users($institution, $password);

        $this->step = 'the academic structure';
        $rotation = $this->structure($institution);

        $this->step = 'the rotation assignments';
        $faculty = $users[DemoDataset::FACULTY];
        $assignments = [];
        foreach ([DemoDataset::STUDENT1, DemoDataset::STUDENT2] as $email) {
            $assignments[$email] = $this->assignment($institution, $rotation, $users[$email], $faculty);
        }

        $this->step = 'the rotation case targets';
        foreach (DemoDataset::targets() as $target) {
            $this->ensure(
                RotationCaseTarget::class, $institution,
                ['rotation_id' => $rotation->id, 'category' => $target['category']],
                ['target_count' => $target['target_count']],
                ['created_by' => $faculty->id],
                'rotation case target '.($target['category'] ?? 'overall'),
            );
        }

        foreach (DemoCaseScenarios::all() as $scenario) {
            $this->step = 'case scenario '.$scenario['key'];
            $student = $users[$scenario['student']];
            $assignment = $assignments[$scenario['student']];

            if ($this->scenarioCaseExists($scenario, $student, $assignment)) {
                continue;
            }

            DB::transaction(fn () => $this->builder->build($scenario, $assignment, $student, $faculty));
        }

        $this->step = 'finished';
    }

    private function institution(): Institution
    {
        $institution = Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->first();

        if ($institution === null) {
            return Institution::query()->create([
                'slug' => DemoDataset::INSTITUTION_SLUG,
                'name' => DemoDataset::INSTITUTION_NAME,
                'status' => 'active',
                'timezone' => DemoDataset::INSTITUTION_TIMEZONE,
            ]);
        }

        if ($institution->name !== DemoDataset::INSTITUTION_NAME) {
            throw new DemoRefusedException('institution '.DemoDataset::INSTITUTION_SLUG.' exists with a different name.');
        }

        return $institution;
    }

    /** @return array<string, User> */
    private function users(Institution $institution, #[\SensitiveParameter] string $password): array
    {
        $users = [];

        foreach (DemoDataset::users() as $email => $attributes) {
            $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();

            if ($user === null) {
                $user = new User([
                    'institution_id' => $institution->id,
                    'name' => $attributes['name'],
                    'email' => $email,
                    'role' => $attributes['role'],
                    'status' => UserStatus::Active,
                ]);
                $user->email_verified_at = Carbon::now();
            } elseif ($user->institution_id !== $institution->id) {
                throw new DemoRefusedException("user {$email} belongs to another institution.");
            } elseif ($user->role !== $attributes['role']) {
                throw new DemoRefusedException("user {$email} does not have the expected {$attributes['role']->value} role.");
            }

            // Re-applied on every run so rotating DEMO_PASSWORD takes effect.
            // The hashed cast stores only the hash.
            $user->password = $password;
            $user->save();

            $users[$email] = $user;
        }

        return $users;
    }

    private function structure(Institution $institution): Rotation
    {
        $programme = $this->ensure(Programme::class, $institution,
            ['code' => DemoDataset::PROGRAMME_CODE],
            ['name' => DemoDataset::PROGRAMME_NAME],
            ['duration_years' => 6, 'status' => 'active'],
            'programme '.DemoDataset::PROGRAMME_CODE);

        $cohort = $this->ensure(AcademicCohort::class, $institution,
            ['programme_id' => $programme->id, 'name' => DemoDataset::COHORT_NAME],
            [],
            ['admission_year' => 2022, 'academic_year_label' => '2026–27', 'status' => 'active'],
            'academic cohort '.DemoDataset::COHORT_NAME);

        $site = $this->ensure(ClinicalSite::class, $institution,
            ['code' => DemoDataset::SITE_CODE],
            ['name' => DemoDataset::SITE_NAME],
            ['status' => 'active'],
            'clinical site '.DemoDataset::SITE_CODE);

        $department = $this->ensure(Department::class, $institution,
            ['clinical_site_id' => $site->id, 'name' => DemoDataset::DEPARTMENT_NAME],
            [],
            ['status' => 'active'],
            'department '.DemoDataset::DEPARTMENT_NAME);

        $ward = $this->ensure(Ward::class, $institution,
            ['code' => DemoDataset::WARD_CODE],
            ['clinical_site_id' => $site->id, 'department_id' => $department->id, 'name' => DemoDataset::WARD_NAME],
            ['status' => 'active'],
            'ward '.DemoDataset::WARD_CODE);

        return $this->ensure(Rotation::class, $institution,
            ['programme_id' => $programme->id, 'name' => DemoDataset::ROTATION_NAME],
            ['academic_cohort_id' => $cohort->id, 'clinical_site_id' => $site->id, 'department_id' => $department->id, 'ward_id' => $ward->id],
            ['starts_on' => DemoDataset::ROTATION_STARTS_ON, 'ends_on' => DemoDataset::ROTATION_ENDS_ON, 'status' => 'active'],
            'rotation '.DemoDataset::ROTATION_NAME);
    }

    private function assignment(Institution $institution, Rotation $rotation, User $student, User $faculty): RotationAssignment
    {
        return $this->ensure(RotationAssignment::class, $institution,
            ['rotation_id' => $rotation->id, 'student_id' => $student->id],
            ['primary_preceptor_id' => $faculty->id],
            ['status' => 'active'],
            "rotation assignment for {$student->email}");
    }

    /**
     * A scenario whose case already exists is skipped, not repaired — reset
     * restores edited demo cases. The existing case must be on the demo
     * assignment, otherwise seeding refuses.
     *
     * @param  array{case_number: int}  $scenario
     */
    private function scenarioCaseExists(array $scenario, User $student, RotationAssignment $assignment): bool
    {
        $case = ClinicalCase::query()->withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('case_number', $scenario['case_number'])
            ->first();

        if ($case === null) {
            return false;
        }

        if ($case->rotation_assignment_id !== $assignment->id) {
            throw new DemoRefusedException("case {$scenario['case_number']} for {$student->email} exists but is not on the demo rotation assignment.");
        }

        return true;
    }

    /**
     * Find a row by natural key within the demo institution, or create it.
     * An existing row must match every $expected column exactly.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $create
     * @return TModel
     */
    protected function ensure(string $model, Institution $institution, array $key, array $expected, array $create, string $label): Model
    {
        $existing = $model::query()->withoutGlobalScopes()
            ->where('institution_id', $institution->id)
            ->where($key)
            ->first();

        if ($existing === null) {
            return $model::query()->withoutGlobalScopes()->create([
                'institution_id' => $institution->id,
                ...$key,
                ...$expected,
                ...$create,
            ]);
        }

        foreach ($expected as $column => $value) {
            if ((string) $existing->getRawOriginal($column) !== (string) $value) {
                throw new DemoRefusedException("{$label} exists but its {$column} does not match the demo dataset.");
            }
        }

        return $existing;
    }
}
