<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreCaseMedicationRequest;
use App\Http\Requests\Student\UpdateCaseMedicationRequest;
use App\Http\Requests\Student\UpdateMedicationChartAvailabilityRequest;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CaseMedicationController extends Controller
{
    public function store(StoreCaseMedicationRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $data = $request->validated();
        $clientOperationId = $data['client_operation_id'];
        unset($data['client_operation_id']);

        $result = $sync->create(
            function () use ($case, $data, $request): CaseMedication {
                if ($case->medication_chart_status !== 'documented') {
                    $case->forceFill([
                        'medication_chart_status' => 'documented',
                        'medication_chart_none_reason' => null,
                        'medication_chart_availability_lock_version' => $case->medication_chart_availability_lock_version + 1,
                    ])->save();
                }

                return CaseMedication::query()->create([
                    ...$data,
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'recorded_by' => $request->user()->id,
                ]);
            },
            $request->user(),
            'medications',
            $clientOperationId,
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseMedication, 500, 'Unexpected model type returned from sync.');

        return response()->json(['medication' => $this->payload($model)], $result['httpStatus']);
    }

    public function sync(UpdateCaseMedicationRequest $request, ClinicalCase $case, CaseMedication $medication, SectionSyncService $sync): JsonResponse
    {
        abort_unless($medication->clinical_case_id === $case->id, 404);

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $medication,
            $request->user(),
            'medications',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseMedication, 500, 'Unexpected model type returned from sync.');

        return response()->json(['medication' => $this->payload($model)], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseMedication $medication, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $medication);
        abort_unless($medication->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($medication, $request->user(), 'medications', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            $model = $result['model'];
            abort_unless($model instanceof CaseMedication, 500, 'Unexpected model type returned from sync.');

            return response()->json(['medication' => $this->payload($model)], 409);
        }

        return response()->noContent();
    }

    public function syncAvailability(UpdateMedicationChartAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        if (array_key_exists('medication_chart_status', $data) && $data['medication_chart_status'] === 'documented') {
            $data['medication_chart_none_reason'] = null;
        }

        $result = $sync->sync(
            $case,
            $request->user(),
            'medication_chart_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof ClinicalCase, 500, 'Unexpected model type returned from sync.');

        return response()->json(['section' => [
            'medication_chart_status' => $model->medication_chart_status,
            'medication_chart_none_reason' => $model->medication_chart_none_reason,
            'lock_version' => $model->medication_chart_availability_lock_version,
            'updated_at' => $model->updated_at->toIso8601String(),
        ]], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(CaseMedication $medication): array
    {
        return [
            'id' => $medication->id,
            'medication_context' => $medication->medication_context,
            'generic_name' => $medication->generic_name,
            'brand_name' => $medication->brand_name,
            'indication' => $medication->indication,
            'indication_unclear' => $medication->indication_unclear,
            'dose_amount' => $medication->dose_amount,
            'dose_unit' => $medication->dose_unit,
            'dosage_form' => $medication->dosage_form,
            'route' => $medication->route,
            'frequency' => $medication->frequency,
            'start_reference' => $medication->start_reference,
            'stop_reference' => $medication->stop_reference,
            'status' => $medication->status?->value,
            'prn_indication' => $medication->prn_indication,
            'notes' => $medication->notes,
            'lock_version' => $medication->lock_version,
            'updated_at' => $medication->updated_at->toIso8601String(),
        ];
    }
}
