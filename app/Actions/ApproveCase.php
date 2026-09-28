<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ApproveCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    /**
     * @param  array<int, array{section: string, body: string, is_flagged?: bool}>  $sectionComments
     */
    public function __invoke(User $actor, ClinicalCase $case, ?string $summary = null, array $sectionComments = []): CaseVersion
    {
        return DB::transaction(function () use ($actor, $case, $summary, $sectionComments): CaseVersion {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless(in_array($case->status, [CaseStatus::Submitted, CaseStatus::UnderReview], true), 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Case has no submitted versions.');

            $fromStatus = $case->status;

            $latestVersion->update([
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $case->update([
                'status' => CaseStatus::Approved,
                'approved_at' => now(),
            ]);

            $transition = CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Approved->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $summary,
            ]);

            foreach ($sectionComments as $comment) {
                CaseReviewComment::query()->create([
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'case_status_transition_id' => $transition->id,
                    'section' => $comment['section'],
                    'body' => $comment['body'],
                    'is_flagged' => false,
                    'created_by' => $actor->id,
                ]);
            }

            $this->audit->record($actor, $case, 'clinical_case.approved', [
                'case_id' => $case->id,
                'version_number' => $latestVersion->version_number,
            ]);

            return $latestVersion;
        });
    }
}
