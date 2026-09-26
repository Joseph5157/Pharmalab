<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreCaseVitalRequest;
use App\Http\Requests\Student\UpdateCaseVitalRequest;
use App\Http\Requests\Student\UpdateVitalsAvailabilityRequest;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CaseVitalController extends Controller
{
    public function store(StoreCaseVitalRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $data = $request->validated();
        $clientOperationId = $data['client_operation_id'];
        unset($data['client_operation_id']);

        $result = $sync->create(
            function () use ($case, $data, $request): CaseVital {
                if ($case->vitals_status !== 'recorded') {
                    $case->forceFill([
                        'vitals_status' => 'recorded',
                        'vitals_unavailable_reason' => null,
                        'vitals_availability_lock_version' => $case->vitals_availability_lock_version + 1,
                    ])->save();
                }

                return CaseVital::query()->create([
                    ...$data,
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'recorded_by' => $request->user()->id,
                ]);
            },
            $request->user(),
            'vitals',
            $clientOperationId,
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseVital, 500, 'Unexpected model type returned from sync.');

        return response()->json(['vital' => $this->payload($model)], $result['httpStatus']);
    }

    public function sync(UpdateCaseVitalRequest $request, ClinicalCase $case, CaseVital $vital, SectionSyncService $sync): JsonResponse
    {
        abort_unless($vital->clinical_case_id === $case->id, 404);

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $vital,
            $request->user(),
            'vitals',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseVital, 500, 'Unexpected model type returned from sync.');

        return response()->json(['section' => $this->payload($model)], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseVital $vital, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $vital);
        abort_unless($vital->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($vital, $request->user(), 'vitals', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            $model = $result['model'];
            abort_unless($model instanceof CaseVital, 500, 'Unexpected model type returned from sync.');

            return response()->json(['vital' => $this->payload($model)], 409);
        }

        return response()->noContent();
    }

    public function syncAvailability(UpdateVitalsAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        if (array_key_exists('vitals_status', $data) && $data['vitals_status'] === 'recorded') {
            $data['vitals_unavailable_reason'] = null;
        }

        $result = $sync->sync(
            $case,
            $request->user(),
            'vitals_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof ClinicalCase, 500, 'Unexpected model type returned from sync.');

        return response()->json(['section' => [
            'vitals_status' => $model->vitals_status,
            'vitals_unavailable_reason' => $model->vitals_unavailable_reason,
            'lock_version' => $model->vitals_availability_lock_version,
            'updated_at' => $model->updated_at->toIso8601String(),
        ]], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(CaseVital $vital): array
    {
        return [
            'id' => $vital->id,
            'observation_type' => $vital->observation_type,
            'value_numeric' => $vital->value_numeric,
            'value_text' => $vital->value_text,
            'value_systolic' => $vital->value_systolic,
            'value_diastolic' => $vital->value_diastolic,
            'unit' => $vital->unit,
            'observed_on' => $vital->observed_on?->toDateString(),
            'observed_at_time' => $vital->observed_at_time,
            'source' => $vital->source,
            'note' => $vital->note,
            'lock_version' => $vital->lock_version,
            'updated_at' => $vital->updated_at->toIso8601String(),
        ];
    }
}
