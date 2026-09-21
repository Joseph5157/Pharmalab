<?php

namespace Tests\Feature\Sync;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\CaseDraftNote;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseDraftNoteSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_with_the_current_version(): void
    {
        [$student, $note] = $this->studentAndNote();

        $response = $this->actingAs($student)->putJson($this->syncUrl($note), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'content' => 'De-identified observation.',
        ]);

        $response->assertOk()
            ->assertJsonPath('operation.status', 'saved')
            ->assertJsonPath('note.lock_version', 1)
            ->assertJsonPath('note.content', 'De-identified observation.');

        $this->assertDatabaseHas('case_draft_notes', [
            'id' => $note->id,
            'content' => 'De-identified observation.',
            'lock_version' => 1,
        ]);
    }

    public function test_student_can_clear_the_note_content(): void
    {
        [$student, $note] = $this->studentAndNote([
            'content' => 'Text to clear.',
            'lock_version' => 1,
        ]);

        $this->actingAs($student)->putJson($this->syncUrl($note), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 1,
            'content' => '',
        ])->assertOk()
            ->assertJsonPath('note.content', '')
            ->assertJsonPath('note.lock_version', 2);
    }

    public function test_retrying_an_operation_is_idempotent(): void
    {
        [$student, $note] = $this->studentAndNote();
        $operationId = (string) Str::uuid();
        $payload = [
            'client_operation_id' => $operationId,
            'base_lock_version' => 0,
            'content' => 'One logical save.',
        ];

        $this->actingAs($student)->putJson($this->syncUrl($note), $payload)->assertOk();
        $this->putJson($this->syncUrl($note), $payload)
            ->assertOk()
            ->assertJsonPath('note.lock_version', 1);

        $this->assertSame(1, SyncOperation::query()->where('client_operation_id', $operationId)->count());
        $this->assertSame(1, $note->fresh()->lock_version);
    }

    public function test_stale_save_returns_both_versions_without_overwriting_server(): void
    {
        [$student, $note] = $this->studentAndNote([
            'content' => 'Newer server observation.',
            'lock_version' => 3,
        ]);

        $this->actingAs($student)->putJson($this->syncUrl($note), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 2,
            'content' => 'Offline device observation.',
        ])->assertConflict()
            ->assertJsonPath('operation.status', 'conflict')
            ->assertJsonPath('note.content', 'Newer server observation.')
            ->assertJsonPath('note.lock_version', 3);

        $this->assertSame('Newer server observation.', $note->fresh()->content);
    }

    public function test_explicit_replace_resolves_conflict_and_is_audited(): void
    {
        [$student, $note] = $this->studentAndNote([
            'content' => 'Server copy.',
            'lock_version' => 4,
        ]);

        $this->actingAs($student)->putJson($this->syncUrl($note), [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 4,
            'content' => 'Confirmed device copy.',
            'resolution' => 'replace_server',
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('operation.status', 'resolved_replaced')
            ->assertJsonPath('note.lock_version', 5);

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $student->id,
            'auditable_id' => $note->id,
            'event_type' => 'case_draft_note.conflict_resolved',
        ]);
    }

    public function test_server_and_device_copy_choices_do_not_mutate_server_content(): void
    {
        [$student, $note] = $this->studentAndNote([
            'content' => 'Authoritative server copy.',
            'lock_version' => 2,
        ]);

        foreach (['use_server', 'keep_local_copy'] as $resolution) {
            $this->actingAs($student)->putJson($this->syncUrl($note), [
                'client_operation_id' => (string) Str::uuid(),
                'base_lock_version' => 1,
                'content' => 'Stale local copy.',
                'resolution' => $resolution,
            ])->assertOk();
        }

        $this->assertSame('Authoritative server copy.', $note->fresh()->content);
        $this->assertSame(2, $note->fresh()->lock_version);
        $this->assertSame(2, AuditEvent::query()->count());
    }

    public function test_another_student_and_institution_cannot_access_the_note(): void
    {
        [$student, $note] = $this->studentAndNote();
        $sameInstitutionStudent = User::factory()->create([
            'institution_id' => $student->institution_id,
            'role' => UserRole::Student,
        ]);
        $otherInstitutionStudent = User::factory()->create([
            'institution_id' => Institution::factory()->create()->id,
            'role' => UserRole::Student,
        ]);

        $this->actingAs($sameInstitutionStudent)
            ->get(route('student.sync-spike.show', $note))
            ->assertForbidden();

        $this->actingAs($otherInstitutionStudent)
            ->get(route('student.sync-spike.show', $note))
            ->assertNotFound();
    }

    /** @param array<string, mixed> $noteAttributes
     * @return array{User, CaseDraftNote}
     */
    private function studentAndNote(array $noteAttributes = []): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->create([
            'institution_id' => $institution->id,
            'role' => UserRole::Student,
        ]);
        $note = CaseDraftNote::query()->withoutGlobalScopes()->create([
            'case_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'content' => '',
            'lock_version' => 0,
            ...$noteAttributes,
        ]);

        return [$student, $note];
    }

    private function syncUrl(CaseDraftNote $note): string
    {
        return route('student.sync-spike.sync', $note);
    }
}
