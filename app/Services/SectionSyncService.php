<?php

namespace App\Services;

use App\Contracts\Syncable;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SectionSyncService
{
    /** @var array<string, class-string> */
    private const SECTION_MODELS = [
        'case_context' => ClinicalCase::class,
        'vitals_availability' => ClinicalCase::class,
        'investigations_availability' => ClinicalCase::class,
        'medication_chart_availability' => ClinicalCase::class,
        'clinical_profile' => CaseClinicalProfile::class,
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /** @return class-string */
    public function modelClassForSection(string $sectionKey): string
    {
        return self::SECTION_MODELS[$sectionKey] ?? throw new \InvalidArgumentException("Unknown section key: {$sectionKey}");
    }

    /** @param Model&Syncable $model @param array<string, mixed> $attributes @return array{status:string,httpStatus:int,model:Model&Syncable} */
    public function sync(Model&Syncable $model, User $user, string $sectionKey, string $clientOperationId, int $baseLockVersion, array $attributes, ?string $resolution, bool $confirmed): array
    {
        $expectedClass = $this->modelClassForSection($sectionKey);
        abort_unless($model::class === $expectedClass, 500, 'Model/section mismatch.');
        return DB::transaction(function () use ($model, $user, $sectionKey, $expectedClass, $clientOperationId, $baseLockVersion, $attributes, $resolution, $confirmed): array {
            /** @var Model&Syncable $locked */
            $locked = $model::query()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $existing = SyncOperation::query()->where('user_id', $user->id)->where('client_operation_id', $clientOperationId)->first();
            if ($existing) {
                abort_unless($existing->section_key === $sectionKey && $existing->syncable_type === $expectedClass && $existing->syncable_id === $locked->getKey(), 409, 'Operation ID already used for a different action.');
                return ['status' => $existing->result_status, 'httpStatus' => $existing->result_status === 'conflict' ? 409 : 200, 'model' => $locked];
            }
            if (in_array($resolution, ['use_server', 'keep_local_copy'], true)) {
                $status = $resolution === 'use_server' ? 'resolved_server' : 'resolved_device_copy';
                $this->record($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, $status);
                $this->audit->record($user, $locked, "{$sectionKey}.conflict_resolved", ['section_key' => $sectionKey, 'resolution' => $resolution, 'base_lock_version' => $baseLockVersion, 'server_lock_version' => $locked->getLockVersion($sectionKey)]);
                return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
            }
            if ($baseLockVersion !== $locked->getLockVersion($sectionKey)) {
                $this->record($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, 'conflict');
                return ['status' => 'conflict', 'httpStatus' => 409, 'model' => $locked];
            }
            abort_if($resolution === 'replace_server' && ! $confirmed, 422, 'Replacing the server version requires explicit confirmation.');
            $locked->applySyncedAttributes($sectionKey, $attributes, $locked->getLockVersion($sectionKey) + 1);
            $status = $resolution === 'replace_server' ? 'resolved_replaced' : 'saved';
            $this->record($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, $status);
            if ($resolution === 'replace_server') $this->audit->record($user, $locked, "{$sectionKey}.conflict_resolved", ['section_key' => $sectionKey, 'resolution' => $resolution, 'base_lock_version' => $baseLockVersion, 'server_lock_version' => $locked->getLockVersion($sectionKey)]);
            return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
        });
    }

    /** @param Model&Syncable $model */
    private function record(User $user, Model&Syncable $model, string $sectionKey, string $operationId, int $baseVersion, string $status): void
    {
        SyncOperation::query()->create(['institution_id' => $model->getInstitutionId(), 'user_id' => $user->id, 'syncable_type' => $this->modelClassForSection($sectionKey), 'syncable_id' => $model->getKey(), 'client_operation_id' => $operationId, 'section_key' => $sectionKey, 'base_lock_version' => $baseVersion, 'result_status' => $status, 'server_version' => $model->getLockVersion($sectionKey)]);
    }
}
