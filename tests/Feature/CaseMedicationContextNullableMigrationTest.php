<?php

namespace Tests\Feature;

use App\Models\CaseMedication;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseMedicationContextNullableMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'database/migrations/2026_09_29_000004_make_case_medication_context_nullable.php';

    public function test_an_existing_medication_row_survives_rollback_and_remigration(): void
    {
        [, $student, $case] = $this->makeCase();
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'medication_context' => 'chart', 'generic_name' => 'Metformin', 'status' => 'active', 'recorded_by' => $student->id,
        ]);

        Artisan::call('migrate:rollback', ['--path' => self::PATH]);
        Artisan::call('migrate', ['--path' => self::PATH]);

        $fresh = CaseMedication::query()->withoutGlobalScopes()->find($medication->id);
        $this->assertNotNull($fresh, 'the existing row must survive the rollback+remigration round trip');
        $this->assertSame('chart', $fresh->medication_context);
        $this->assertTrue(Schema::hasColumn('case_medications', 'medication_context'));
    }

    public function test_rollback_refuses_rather_than_silently_dropping_a_row_with_a_null_medication_context(): void
    {
        [, $student, $case] = $this->makeCase();
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'status' => 'active', 'recorded_by' => $student->id,
        ]);

        try {
            Artisan::call('migrate:rollback', ['--path' => self::PATH]);
            $this->fail('rollback should refuse when a row with a null medication_context exists, not silently drop it');
        } catch (\Throwable) {
            // Expected: the down() migration re-imposes NOT NULL, which must
            // fail loudly against an existing null value rather than
            // silently losing the row during the SQLite table rebuild.
        }

        $this->assertNotNull(CaseMedication::query()->withoutGlobalScopes()->find($medication->id), 'the row must still exist after the refused rollback');
        $this->assertTrue(Schema::hasColumn('case_medications', 'indication_unclear'), 'the table must still be the post-migration schema, not a partially rebuilt one');
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id,
            'student_id' => $student->id, 'primary_preceptor_id' => $student->id, 'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => 'draft',
        ]);

        return [$institution, $student, $case];
    }
}
