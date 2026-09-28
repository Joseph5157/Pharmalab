<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseStatusTransition;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ReopenCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    public function __invoke(User $actor, ClinicalCase $case, string $reason): void
    {
        DB::transaction(function () use ($actor, $case, $reason): void {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless($case->status === CaseStatus::Approved, 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Approved case has no version to reopen — this should be unreachable.');

            $case->update([
                'status' => CaseStatus::Returned,
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => CaseStatus::Approved->value,
                'to_status' => CaseStatus::Returned->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $reason,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.reopened', [
                'case_id' => $case->id,
                'version_number' => $latestVersion->version_number,
                'reason' => $reason,
            ]);
        });
    }
}
