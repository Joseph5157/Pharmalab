<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SyncCaseDraftNoteRequest;
use App\Models\AuditEvent;
use App\Models\CaseDraftNote;
use App\Models\SyncOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CaseDraftNoteController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $note = CaseDraftNote::query()->firstOrCreate(
            ['student_id' => $request->user()->id],
            [
                'case_id' => (string) Str::ulid(),
                'institution_id' => $request->user()->institution_id,
                'content' => '',
            ],
        );

        return redirect()->route('student.sync-spike.show', $note);
    }

    public function show(CaseDraftNote $caseDraftNote): Response
    {
        Gate::authorize('view', $caseDraftNote);

        return Inertia::render('student/CaseDraftNote', [
            'note' => $this->notePayload($caseDraftNote),
        ]);
    }

    public function sync(SyncCaseDraftNoteRequest $request, CaseDraftNote $caseDraftNote): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $caseDraftNote, $data): JsonResponse {
            $note = CaseDraftNote::query()->lockForUpdate()->findOrFail($caseDraftNote->id);
            $existing = SyncOperation::query()
                ->where('user_id', $request->user()->id)
                ->where('client_operation_id', $data['client_operation_id'])
                ->first();

            if ($existing !== null) {
                abort_unless($existing->case_draft_note_id === $note->id, 409, 'Operation ID already belongs to another note.');

                return $this->operationResponse($existing, $note);
            }

            $resolution = $data['resolution'] ?? null;

            if ($resolution === 'use_server' || $resolution === 'keep_local_copy') {
                $status = $resolution === 'use_server' ? 'resolved_server' : 'resolved_device_copy';
                $operation = $this->recordOperation($request, $note, $data, $status);
                $this->auditResolution($request, $note, $resolution, $data['base_lock_version']);

                return $this->operationResponse($operation, $note);
            }

            if ((int) $data['base_lock_version'] !== $note->lock_version) {
                $operation = $this->recordOperation($request, $note, $data, 'conflict');

                return $this->operationResponse($operation, $note);
            }

            if ($resolution === 'replace_server' && ! ($data['confirmed'] ?? false)) {
                abort(422, 'Replacing the server version requires explicit confirmation.');
            }

            $note->forceFill([
                'content' => $data['content'] ?? '',
                'lock_version' => $note->lock_version + 1,
            ])->save();

            $status = $resolution === 'replace_server' ? 'resolved_replaced' : 'saved';
            $operation = $this->recordOperation($request, $note, $data, $status);

            if ($resolution === 'replace_server') {
                $this->auditResolution($request, $note, $resolution, $data['base_lock_version']);
            }

            return $this->operationResponse($operation, $note);
        });
    }

    /** @param array<string, mixed> $data */
    private function recordOperation(Request $request, CaseDraftNote $note, array $data, string $status): SyncOperation
    {
        return SyncOperation::query()->create([
            'institution_id' => $request->user()->institution_id,
            'user_id' => $request->user()->id,
            'case_draft_note_id' => $note->id,
            'client_operation_id' => $data['client_operation_id'],
            'section_key' => 'case_draft_note',
            'base_lock_version' => $data['base_lock_version'],
            'result_status' => $status,
            'server_version' => $note->lock_version,
        ]);
    }

    private function operationResponse(SyncOperation $operation, CaseDraftNote $note): JsonResponse
    {
        $note->refresh();

        $payload = [
            'operation' => [
                'client_operation_id' => $operation->client_operation_id,
                'status' => $operation->result_status,
            ],
            'note' => $this->notePayload($note),
        ];

        return response()->json($payload, $operation->result_status === 'conflict' ? 409 : 200);
    }

    private function auditResolution(Request $request, CaseDraftNote $note, string $resolution, int $baseVersion): void
    {
        AuditEvent::query()->create([
            'institution_id' => $request->user()->institution_id,
            'actor_id' => $request->user()->id,
            'actor_role' => UserRole::Student->value,
            'event_type' => 'case_draft_note.conflict_resolved',
            'auditable_type' => CaseDraftNote::class,
            'auditable_id' => $note->id,
            'metadata' => [
                'section_key' => 'case_draft_note',
                'resolution' => $resolution,
                'base_lock_version' => $baseVersion,
                'server_lock_version' => $note->lock_version,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function notePayload(CaseDraftNote $note): array
    {
        return [
            'id' => $note->id,
            'case_id' => $note->case_id,
            'content' => $note->content,
            'lock_version' => $note->lock_version,
            'updated_at' => $note->updated_at->toIso8601String(),
        ];
    }
}
