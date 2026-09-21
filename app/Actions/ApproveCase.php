<?php

namespace App\Actions;

use App\Enums\CaseStatus;
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

    public function __invoke(User $actor, ClinicalCase $case, ?string $summary = null): CaseVersion
    {
        $latestVersion = $case->versions()->latest('version_number')->first();

        if ($latestVersion === null) {
            throw new \InvalidArgumentException('Case has no submitted versions.');
        }

        return DB::transaction(function () use ($actor, $case, $latestVersion, $summary): CaseVersion {
            $fromStatus = $case->status;

            $latestVersion->update([
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $case->update([
                'status' => CaseStatus::Approved,
                'approved_at' => now(),
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Approved->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $summary,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.approved', [
                'case_id' => $case->id,
                'version_number' => $latestVersion->version_number,
            ]);

            return $latestVersion;
        });
    }
}
