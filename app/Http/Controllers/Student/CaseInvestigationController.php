<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreCaseInvestigationRequest;
use App\Http\Requests\Student\UpdateCaseInvestigationRequest;
use App\Http\Requests\Student\UpdateInvestigationsAvailabilityRequest;
use App\Models\CaseInvestigation;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CaseInvestigationController extends Controller
{
    public function store(StoreCaseInvestigationRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $data = $request->validated();
        $clientOperationId = $data['client_operation_id'];
        unset($data['client_operation_id']);

        $result = $sync->create(
            function () use ($case, $data, $request): CaseInvestigation {
                if ($case->investigations_status !== 'recorded') {
                    $case->forceFill([
                        'investigations_status' => 'recorded',
                        'investigations_unavailable_reason' => null,
                        'investigations_availability_lock_version' => $case->investigations_availability_lock_version + 1,
                    ])->save();
                }

                return CaseInvestigation::query()->create([
                    ...$data,
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'recorded_by' => $request->user()->id,
                ]);
            },
            $request->user(),
            'investigations',
            $clientOperationId,
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseInvestigation, 500, 'Unexpected model type returned from sync.');

        return response()->json(['investigation' => $this->payload($model)], $result['httpStatus']);
    }

    public function sync(UpdateCaseInvestigationRequest $request, ClinicalCase $case, CaseInvestigation $investigation, SectionSyncService $sync): JsonResponse
    {
        abort_unless($investigation->clinical_case_id === $case->id, 404);

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $investigation,
            $request->user(),
            'investigations',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof CaseInvestigation, 500, 'Unexpected model type returned from sync.');

        return response()->json(['investigation' => $this->payload($model)], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseInvestigation $investigation, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $investigation);
        abort_unless($investigation->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($investigation, $request->user(), 'investigations', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            $model = $result['model'];
            abort_unless($model instanceof CaseInvestigation, 500, 'Unexpected model type returned from sync.');

            return response()->json(['investigation' => $this->payload($model)], 409);
        }

        return response()->noContent();
    }

    public function syncAvailability(UpdateInvestigationsAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        if (array_key_exists('investigations_status', $data) && $data['investigations_status'] === 'recorded') {
            $data['investigations_unavailable_reason'] = null;
        }

        $result = $sync->sync(
            $case,
            $request->user(),
            'investigations_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        $model = $result['model'];
        abort_unless($model instanceof ClinicalCase, 500, 'Unexpected model type returned from sync.');

        return response()->json(['section' => [
            'investigations_status' => $model->investigations_status,
            'investigations_unavailable_reason' => $model->investigations_unavailable_reason,
            'lock_version' => $model->investigations_availability_lock_version,
            'updated_at' => $model->updated_at->toIso8601String(),
        ]], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(CaseInvestigation $investigation): array
    {
        return [
            'id' => $investigation->id,
            'test_name' => $investigation->test_name,
            'result_type' => $investigation->result_type,
            'result_value' => $investigation->result_value,
            'unit' => $investigation->unit,
            'unit_not_stated' => $investigation->unit_not_stated,
            'reference_range' => $investigation->reference_range,
            'reference_range_not_provided' => $investigation->reference_range_not_provided,
            'reported_flag' => $investigation->reported_flag,
            'observed_on' => $investigation->observed_on?->toDateString(),
            'observed_at_time' => $investigation->observed_at_time,
            'interpretation' => $investigation->interpretation,
            'lock_version' => $investigation->lock_version,
            'updated_at' => $investigation->updated_at->toIso8601String(),
        ];
    }
}
