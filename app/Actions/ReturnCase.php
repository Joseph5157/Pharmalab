<?php

namespace App\Actions;

use App\Enums\CaseStatus;
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

    public function __invoke(User $actor, ClinicalCase $case, string $reason): void
    {
        DB::transaction(function () use ($actor, $case, $reason): void {
            $fromStatus = $case->status;

            $case->update([
                'status' => CaseStatus::Returned,
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Returned->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.returned', [
                'case_id' => $case->id,
                'reason' => $reason,
            ]);
        });
    }
}
