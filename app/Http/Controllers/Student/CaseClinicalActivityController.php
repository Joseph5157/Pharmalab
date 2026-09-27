<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateAdrActivityRequest;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CaseClinicalActivityController extends Controller
{
    public function syncAdr(UpdateAdrActivityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $activity = $this->findOrCreateSingleton($case, ClinicalActivityType::Adr, $request->user()->id);

        $envelope = $request->syncEnvelope();
        $data = $this->mergeOrClearDetails($activity, $request->sectionData(), leavesConditionalState: fn (array $data): bool => array_key_exists('status', $data) && $data['status'] !== 'yes');

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activity_adr',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseClinicalActivity, 500, 'Unexpected model type returned from sync.');

        return response()->json(['activity' => $this->payload($model)], $result['httpStatus']);
    }

    /**
     * Guards against two near-simultaneous first-sync requests both creating
     * a singleton row: locks the parent case first (serializing concurrent
     * requests for the same case, the same technique SectionSyncService::sync()
     * uses), then firstOrCreate() inside that lock. The partial unique index
     * from this task's migration is the second, database-level line of
     * defense if this lock is ever bypassed by a future code path.
     */
    protected function findOrCreateSingleton(ClinicalCase $case, ClinicalActivityType $type, int $userId): CaseClinicalActivity
    {
        return DB::transaction(function () use ($case, $type, $userId): CaseClinicalActivity {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();

            return CaseClinicalActivity::query()->firstOrCreate(
                ['clinical_case_id' => $case->id, 'activity_type' => $type->value],
                ['institution_id' => $case->institution_id, 'recorded_by' => $userId],
            );
        });
    }

    /**
     * `details` is a JSON column — a request that only sends one nested key
     * must merge into the existing object, not replace it wholesale (that
     * was Slice 2 review finding #7: "Partial updates to activity `details`
     * must merge bounded keys"). The one exception is a genuine state exit
     * (e.g. ADR status leaving "yes"): once the UI stops showing the detail
     * fields, the stale data behind them must not silently persist either —
     * mirrors the allergy-field-clearing fix in Slice 2A Task 5.
     *
     * @param  array<string, mixed>  $data
     * @param  \Closure(array<string, mixed>): bool  $leavesConditionalState
     * @return array<string, mixed>
     */
    protected function mergeOrClearDetails(CaseClinicalActivity $activity, array $data, \Closure $leavesConditionalState): array
    {
        if ($leavesConditionalState($data)) {
            $data['details'] = null;

            return $data;
        }

        if (array_key_exists('details', $data)) {
            $data['details'] = [...($activity->details ?? []), ...$data['details']];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function payload(CaseClinicalActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'activity_type' => $activity->activity_type->value,
            'status' => $activity->status,
            'details' => $activity->details,
            'lock_version' => $activity->lock_version,
            'updated_at' => $activity->updated_at->toIso8601String(),
        ];
    }
}
