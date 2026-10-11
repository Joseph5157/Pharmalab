<?php

namespace Tests\Feature;

use App\Demo\DemoDataset;
use App\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\CaseClinicalProfile;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\RotationAssignment;
use App\Models\Rotation;
use App\Models\User;
use App\Services\CaseExportPdfService;
use App\Services\RotationProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

class DemoDatasetTest extends TestCase
{
    use EnablesDemoMode, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDemoMode();
        $this->artisan('demo:seed')->assertSuccessful();
    }

    public function test_every_scenario_reaches_its_final_state_with_the_expected_history(): void
    {
        $expected = [
            [DemoDataset::STUDENT1, 1, CaseStatus::Approved, 1, ['submitted', 'approved']],
            [DemoDataset::STUDENT1, 2, CaseStatus::Approved, 2, ['submitted', 'returned', 'submitted', 'approved']],
            [DemoDataset::STUDENT1, 3, CaseStatus::Returned, 1, ['submitted', 'approved', 'returned']],
            [DemoDataset::STUDENT1, 4, CaseStatus::Returned, 1, ['submitted', 'returned']],
            [DemoDataset::STUDENT1, 5, CaseStatus::Submitted, 1, ['submitted']],
            [DemoDataset::STUDENT1, 6, CaseStatus::Draft, 0, []],
            [DemoDataset::STUDENT2, 1, CaseStatus::Approved, 1, ['submitted', 'approved']],
            [DemoDataset::STUDENT2, 2, CaseStatus::Submitted, 1, ['submitted']],
        ];

        foreach ($expected as [$email, $number, $status, $versions, $transitions]) {
            $case = $this->caseFor($email, $number);
            $this->assertSame($status, $case->status, "{$email} #{$number}");
            $this->assertSame($versions, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->count(), "{$email} #{$number}");
            $this->assertSame($transitions, CaseStatusTransition::query()->withoutGlobalScopes()
                ->where('clinical_case_id', $case->id)->orderBy('created_at')->orderBy('id')->pluck('to_status')
                ->map(fn ($s): string => $s instanceof CaseStatus ? $s->value : (string) $s)->all(), "{$email} #{$number}");
        }

        $this->assertSame(8, ClinicalCase::query()->withoutGlobalScopes()->count());
    }

    public function test_the_p2_review_round_records_flagged_comments_and_both_versions(): void
    {
        $case = $this->caseFor(DemoDataset::STUDENT1, 2);
        $returned = CaseStatusTransition::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->where('to_status', 'returned')->sole();

        $comments = CaseReviewComment::query()->withoutGlobalScopes()->where('case_status_transition_id', $returned->id)->orderBy('section')->get();
        $this->assertSame(['medication_chart', 'soap'], $comments->map(fn (CaseReviewComment $c): string => $c->section instanceof \BackedEnum ? $c->section->value : (string) $c->section)->all());
        $this->assertTrue($comments->every(fn (CaseReviewComment $c): bool => (bool) $c->is_flagged));
        $this->assertSame('Document the intended prednisolone dose duration.', $comments[0]->body);
        $this->assertSame('Specify monitoring parameters and follow-up targets.', $comments[1]->body);

        [$v1, $v2] = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->orderBy('version_number')->get()->all();
        $this->assertSame('Short course prescribed without stating duration.', $this->prednisoloneNotes($v1));
        $this->assertSame('Monitor clinical response.', $v1->snapshot['soap']['monitoring_plan']);
        $this->assertSame('Prednisolone 40 mg once daily for 5 days.', $this->prednisoloneNotes($v2));
        $this->assertStringStartsWith('Monitor SpO₂, respiratory rate', $v2->snapshot['soap']['monitoring_plan']);
        $this->assertNull($v1->approved_at);
        $this->assertNotNull($v2->approved_at);
    }

    public function test_the_reopened_case_keeps_its_approved_version_intact(): void
    {
        $case = $this->caseFor(DemoDataset::STUDENT1, 3);
        $faculty = $this->user(DemoDataset::FACULTY);
        $version = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole();

        $this->assertSame($faculty->id, $version->approved_by);
        $this->assertNotNull($version->approved_at);
        $this->assertSame(hash('sha256', json_encode($version->snapshot, JSON_THROW_ON_ERROR)), $version->snapshot_hash);

        $reopen = CaseStatusTransition::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertSame('approved', $reopen->from_status instanceof CaseStatus ? $reopen->from_status->value : $reopen->from_status);
        $this->assertStringContainsString('Potassium of 5.6 mmol/L', (string) $reopen->reason);
    }

    public function test_student_progress_matches_the_demonstration_script(): void
    {
        $priya = app(RotationProgressService::class)->forStudent($this->user(DemoDataset::STUDENT1))->sole();
        $this->assertSame(['approved' => 2, 'target' => 6], $priya['overall']);
        $this->assertSame([
            ['category' => 'Cardiology', 'approved' => 1, 'target' => 2],
            ['category' => 'Infectious Disease', 'approved' => 0, 'target' => 2],
            ['category' => 'Respiratory', 'approved' => 1, 'target' => 2],
        ], $priya['categories']);

        $rahul = app(RotationProgressService::class)->forStudent($this->user(DemoDataset::STUDENT2))->sole();
        $this->assertSame(['approved' => 1, 'target' => 6], $rahul['overall']);
        $this->assertSame([
            ['category' => 'Cardiology', 'approved' => 1, 'target' => 2],
            ['category' => 'Infectious Disease', 'approved' => 0, 'target' => 2],
            ['category' => 'Respiratory', 'approved' => 0, 'target' => 2],
        ], $rahul['categories']);
    }

    public function test_the_faculty_queue_holds_two_submitted_cases_across_two_students(): void
    {
        $faculty = $this->user(DemoDataset::FACULTY);
        $assignmentIds = RotationAssignment::query()->withoutGlobalScopes()->where('primary_preceptor_id', $faculty->id)->pluck('id');

        $queue = ClinicalCase::query()->withoutGlobalScopes()
            ->whereIn('rotation_assignment_id', $assignmentIds)
            ->whereIn('status', [CaseStatus::Submitted, CaseStatus::UnderReview])
            ->get();

        $this->assertCount(2, $queue);
        $this->assertEqualsCanonicalizing(
            [$this->caseFor(DemoDataset::STUDENT1, 5)->id, $this->caseFor(DemoDataset::STUDENT2, 2)->id],
            $queue->pluck('id')->all(),
        );
    }

    public function test_snapshots_freeze_the_rotation_site_and_ward(): void
    {
        $version = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $this->caseFor(DemoDataset::STUDENT1, 1)->id)->sole();

        $this->assertSame(DemoDataset::ROTATION_NAME, $version->snapshot['record_metadata']['rotation_name']);
        $this->assertSame(DemoDataset::SITE_NAME, $version->snapshot['record_metadata']['clinical_site_name']);
        $this->assertSame(DemoDataset::WARD_NAME, $version->snapshot['record_metadata']['ward_name']);
    }

    public function test_audit_events_are_attributed_to_the_right_demo_users(): void
    {
        $students = [$this->user(DemoDataset::STUDENT1)->id, $this->user(DemoDataset::STUDENT2)->id];
        $faculty = $this->user(DemoDataset::FACULTY)->id;

        $byEvent = AuditEvent::query()->withoutGlobalScopes()->get()->groupBy('event_type');

        $this->assertCount(8, $byEvent['clinical_case.submitted']);
        $this->assertCount(4, $byEvent['clinical_case.approved']);
        $this->assertCount(2, $byEvent['clinical_case.returned']);
        $this->assertCount(1, $byEvent['clinical_case.reopened']);
        $this->assertTrue($byEvent['clinical_case.submitted']->every(fn (AuditEvent $e): bool => in_array($e->actor_id, $students, true)));
        foreach (['clinical_case.approved', 'clinical_case.returned', 'clinical_case.reopened'] as $event) {
            $this->assertTrue($byEvent[$event]->every(fn (AuditEvent $e): bool => $e->actor_id === $faculty), $event);
        }
    }

    public function test_every_case_has_its_own_diagnosis(): void
    {
        $diagnoses = CaseClinicalProfile::query()->withoutGlobalScopes()->get()
            ->map(fn (CaseClinicalProfile $p): string => $p->diagnoses[0]['label']);

        $this->assertCount(8, $diagnoses);
        $this->assertCount(8, $diagnoses->unique());
    }

    public function test_seed_does_not_repair_a_case_edited_during_a_demo(): void
    {
        $draft = $this->caseFor(DemoDataset::STUDENT1, 6);
        $draft->forceFill(['case_category' => 'Edited live'])->save();

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame('Edited live', $draft->fresh()->case_category);
        $this->assertSame(8, ClinicalCase::query()->withoutGlobalScopes()->count());
    }

    public function test_an_extra_case_created_in_the_ui_does_not_block_seed(): void
    {
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->findOrFail($this->caseFor(DemoDataset::STUDENT1, 1)->rotation_assignment_id);
        ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $assignment->institution_id, 'student_id' => $assignment->student_id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 7, 'status' => CaseStatus::Draft,
        ]);

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame(9, ClinicalCase::query()->withoutGlobalScopes()->count());
    }

    public function test_seed_refuses_when_a_scenario_case_sits_on_another_assignment(): void
    {
        $draft = $this->caseFor(DemoDataset::STUDENT1, 6);
        $demoRotation = Rotation::query()->withoutGlobalScopes()->sole();
        $otherRotation = Rotation::query()->withoutGlobalScopes()->create([
            ...$demoRotation->only(['institution_id', 'programme_id', 'academic_cohort_id', 'clinical_site_id', 'department_id', 'ward_id']),
            'name' => 'Another Rotation', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);
        $otherAssignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $demoRotation->institution_id, 'rotation_id' => $otherRotation->id,
            'student_id' => $draft->student_id, 'primary_preceptor_id' => $this->user(DemoDataset::FACULTY)->id, 'status' => 'active',
        ]);
        $draft->forceFill(['rotation_assignment_id' => $otherAssignment->id])->save();

        $this->artisan('demo:seed')
            ->expectsOutputToContain('Demo seed refused: case 6 for student1@pharmalab-demo.test exists but is not on the demo rotation assignment. No demo data was changed.')
            ->assertFailed();
    }

    public function test_an_approved_demo_case_renders_as_a_pdf_with_its_record_metadata(): void
    {
        $case = $this->caseFor(DemoDataset::STUDENT1, 1);
        $this->actingAs($this->user(DemoDataset::STUDENT1));

        $html = app(CaseExportPdfService::class)->html($case);

        $this->assertStringContainsString(DemoDataset::ROTATION_NAME, $html);
        $this->assertStringContainsString(DemoDataset::SITE_NAME, $html);
        $this->assertStringContainsString(DemoDataset::WARD_NAME, $html);
        $this->assertStringContainsString('Home BP twice daily', $html);
    }

    private function user(string $email): User
    {
        return User::query()->withoutGlobalScopes()->where('email', $email)->sole();
    }

    private function caseFor(string $email, int $number): ClinicalCase
    {
        return ClinicalCase::query()->withoutGlobalScopes()
            ->where('student_id', $this->user($email)->id)
            ->where('case_number', $number)
            ->sole();
    }

    private function prednisoloneNotes(CaseVersion $version): ?string
    {
        foreach ($version->snapshot['medication_chart']['entries'] as $entry) {
            if ($entry['generic_name'] === 'Prednisolone') {
                return $entry['notes'];
            }
        }

        return null;
    }
}
