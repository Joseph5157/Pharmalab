<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class SubmitCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    public function __invoke(User $actor, ClinicalCase $case): CaseVersion
    {
        $soap = $case->currentSoap;

        if ($soap === null) {
            throw new \InvalidArgumentException('Case must have a SOAP note before submission.');
        }

        return DB::transaction(function () use ($actor, $case, $soap): CaseVersion {
            $snapshot = [
                'encounter_date' => $case->encounter_date,
                'case_category' => $case->case_category,
                'age_value' => $case->age_value,
                'age_unit' => $case->age_unit,
                'sex' => $case->sex,
                'soap' => [
                    'subjective' => $soap->subjective,
                    'objective' => $soap->objective,
                    'assessment' => $soap->assessment,
                    'plan' => $soap->plan,
                ],
            ];

            $versionNumber = $case->current_revision_number + 1;
            $version = CaseVersion::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'version_number' => $versionNumber,
                'source_revision_number' => $soap->revision_number,
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $fromStatus = $case->status;
            $case->update([
                'status' => CaseStatus::Submitted,
                'current_revision_number' => $versionNumber,
                'submitted_at' => now(),
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Submitted->value,
                'actor_id' => $actor->id,
                'case_version_id' => $version->id,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.submitted', [
                'case_id' => $case->id,
                'version_number' => $versionNumber,
            ]);

            return $version;
        });
    }
}
