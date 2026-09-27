<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ReturnCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    /**
     * @param  array<int, array{section: string, body: string, is_flagged: bool}>  $sectionComments
     */
    public function __invoke(User $actor, ClinicalCase $case, string $reason, array $sectionComments): void
    {
        DB::transaction(function () use ($actor, $case, $reason, $sectionComments): void {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless(in_array($case->status, [CaseStatus::Submitted, CaseStatus::UnderReview], true), 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Case has no submitted version to return — this should be unreachable.');

            $fromStatus = $case->status;

            $case->update([
                'status' => CaseStatus::Returned,
            ]);

            $transition = CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Returned->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $reason,
            ]);

            foreach ($sectionComments as $comment) {
                CaseReviewComment::query()->create([
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'case_status_transition_id' => $transition->id,
                    'section' => $comment['section'],
                    'body' => $comment['body'],
                    'is_flagged' => $comment['is_flagged'],
                    'created_by' => $actor->id,
                ]);
            }

            $this->audit->record($actor, $case, 'clinical_case.returned', [
                'case_id' => $case->id,
                'reason' => $reason,
            ]);
        });
    }
}
