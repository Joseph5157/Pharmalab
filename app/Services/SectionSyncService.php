<?php

namespace App\Services;

use App\Contracts\Syncable;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
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
        'vitals' => CaseVital::class,
        'investigations' => CaseInvestigation::class,
        'medications' => CaseMedication::class,
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /** @return class-string */
    public function modelClassForSection(string $sectionKey): string
    {
        return self::SECTION_MODELS[$sectionKey] ?? throw new \InvalidArgumentException("Unknown section key: {$sectionKey}");
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{status:string,httpStatus:int,model:Model&Syncable}
     */
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
            if ($resolution === 'replace_server') {
                $this->audit->record($user, $locked, "{$sectionKey}.conflict_resolved", ['section_key' => $sectionKey, 'resolution' => $resolution, 'base_lock_version' => $baseLockVersion, 'server_lock_version' => $locked->getLockVersion($sectionKey)]);
            }

            return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
        });
    }

    /**
     * @param  \Closure(): (Model&Syncable)  $factory
     * @return array{status: string, httpStatus: int, model: Model&Syncable}
     */
    public function create(\Closure $factory, User $user, string $sectionKey, string $clientOperationId): array
    {
        $expectedClass = $this->modelClassForSection($sectionKey);

        return DB::transaction(function () use ($factory, $user, $sectionKey, $clientOperationId, $expectedClass): array {
            $existing = SyncOperation::query()
                ->where('user_id', $user->id)
                ->where('client_operation_id', $clientOperationId)
                ->first();

            if ($existing !== null) {
                abort_unless(
                    $existing->section_key === $sectionKey && $existing->syncable_type === $expectedClass,
                    409,
                    'Operation ID already used for a different action.',
                );

                /** @var Model&Syncable $model */
                $model = $expectedClass::query()->withoutGlobalScopes()->findOrFail($existing->syncable_id);

                return ['status' => $existing->result_status, 'httpStatus' => 201, 'model' => $model];
            }

            /** @var Model&Syncable $model */
            $model = $factory();
            $actualClass = $model::class;
            abort_unless($actualClass === $expectedClass, 500, "Model/section mismatch: {$sectionKey} expects {$expectedClass}, got {$actualClass}.");

            // The factory's INSERT can rely on column defaults (e.g.
            // lock_version, status) it never sets explicitly. Without a
            // refresh those stay null on this in-memory instance even though
            // the row itself has the default value, so the JSON response —
            // and anything a client seeds a fresh useSectionSync() from —
            // would carry a null lock_version and fail the next edit's
            // required base_lock_version validation.
            $model->refresh();

            $this->record($user, $model, $sectionKey, $clientOperationId, 0, 'saved');

            return ['status' => 'saved', 'httpStatus' => 201, 'model' => $model];
        });
    }

    /**
     * Deleting a row is subject to the same optimistic-concurrency check as
     * editing one — a client whose base_lock_version is stale must not be
     * able to delete a row it hasn't actually seen the latest state of. On a
     * successful delete this also records an audit event; the destroy()
     * endpoints this replaces previously called Model::delete() directly and
     * recorded nothing.
     *
     * @return array{status: string, httpStatus: int, model: (Model&Syncable)|null}
     */
    public function delete(Model&Syncable $model, User $user, string $sectionKey, int $baseLockVersion): array
    {
        $expectedClass = $this->modelClassForSection($sectionKey);
        $actualClass = $model::class;
        abort_unless($actualClass === $expectedClass, 500, "Model/section mismatch: {$sectionKey} expects {$expectedClass}, got {$actualClass}.");

        return DB::transaction(function () use ($model, $user, $sectionKey, $baseLockVersion): array {
            /** @var Model&Syncable $locked */
            $locked = $model::query()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            if ($baseLockVersion !== $locked->getLockVersion($sectionKey)) {
                return ['status' => 'conflict', 'httpStatus' => 409, 'model' => $locked];
            }

            $deletedId = $locked->getKey();
            $deletedLockVersion = $locked->getLockVersion($sectionKey);
            $locked->delete();

            $this->audit->record($user, $locked, "{$sectionKey}.deleted", [
                'deleted_id' => $deletedId,
                'lock_version_at_deletion' => $deletedLockVersion,
            ]);

            return ['status' => 'deleted', 'httpStatus' => 200, 'model' => null];
        });
    }

    private function record(User $user, Model&Syncable $model, string $sectionKey, string $operationId, int $baseVersion, string $status): void
    {
        SyncOperation::query()->create(['institution_id' => $model->getInstitutionId(), 'user_id' => $user->id, 'syncable_type' => $this->modelClassForSection($sectionKey), 'syncable_id' => $model->getKey(), 'client_operation_id' => $operationId, 'section_key' => $sectionKey, 'base_lock_version' => $baseVersion, 'result_status' => $status, 'server_version' => $model->getLockVersion($sectionKey)]);
    }
}
