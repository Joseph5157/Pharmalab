# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2C (SOAP Integration, Conditional Clinical Activities, Full Editor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution, chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking. **Depends on Slice 2A and Slice 2B being merged first** — this plan reuses every shared piece they built: `Syncable`, `SyncsWithLockVersion`, `SectionSyncService` (including `create()` from 2B), `HasSyncEnvelope`, `RejectsUnknownFields`, `outboxStore.ts`, `useSectionSync.ts`, `useRepeatableRowCreate.ts` (2B), `DeidentificationNotice.vue`, and `CaseEditor.vue`.
>
> **Revision note (post-review):** This plan was reviewed and returned with blocking corrections before any implementation began. This revision fixes: a plan that broke the live SOAP page in Task 1 and only repaired it in Task 6 (now the new endpoint is added additively and the old page is retired atomically in one task), silently dropped SOAP audit events (now preserved), a singleton `firstOrCreate` race for ADR/Counselling with no database-level guard (now a partial unique index plus a row lock), a partial-update bug that overwrote the whole `details` JSON object instead of merging into it, and UI that exposed only a handful of each conditional activity's accepted fields. See each task's **Revision:** note.

**Goal:** Complete the six-section mobile case editor by folding SOAP into the same offline-sync engine as every other section (without an intermediate broken state), adding the four Conditional Clinical Activities (Pharmacist intervention, Suspected ADR, Patient counselling, Monitoring follow-up) with their full accepted field sets and offline-capable row creation for the repeatable pair, and running full end-to-end device verification across all six sections.

**Architecture:** The field catalogue's four "Conditional Clinical Activities" split into two genuinely different shapes, and the implementation follows that split rather than treating all four uniformly: **Suspected ADR** and **Patient counselling** are each a single case-level answer with conditional detail fields — these reuse the exact lazy-singleton pattern Slice 2A built for `CaseClinicalProfile`, via two dedicated routes (`.../clinical-activities/adr`, `.../clinical-activities/counselling`), now hardened against concurrent double-creation with a partial unique database index plus a parent-row lock (see Task 3). **Pharmacist intervention** and **Monitoring follow-up** are genuinely repeatable and reuse Slice 2B's repeatable-row pattern in full, including offline-capable creation via `useRepeatableRowCreate`. Both flavors share one `CaseClinicalActivityController` and one underlying table (`case_clinical_activities`, already created in Slice 1 with an `activity_type` discriminator), but the generic `{activity}` route explicitly refuses to serve the two singleton types so a request can never reach a row through the wrong door. SOAP folds into the same engine as any other section: `SoapNote` becomes `Syncable`, and a **new, additive** sync endpoint is introduced in Task 1 alongside the existing Inertia page and its `update()` action (both keep working, unmodified, until Task 6 retires them in the same commit that switches the in-editor SOAP section on) — the app is never left with a broken SOAP page between tasks.

Every `case_clinical_activities.details` JSON write in this slice **merges** the request's present keys into whatever `details` already holds, rather than replacing the column outright — a partial update to one nested key (e.g. just `details.outcome` on an intervention) must not silently erase sibling keys another request already saved. The one exception is a deliberate state exit (ADR status leaving `yes`, or Counselling status leaving `performed`/`planned`): moving out of the state that makes the detail fields visible in the UI also clears the JSON blob those fields no longer show, mirroring the same "hidden conditional data must not silently persist" fix Slice 2A applied to allergy fields.

**Tech Stack:** Same as Slices 2A/2B — Laravel 13, Eloquent, PHPUnit, SQLite (`:memory:`)/PostgreSQL; Inertia.js + Vue 3 + TypeScript, native `fetch` + IndexedDB.

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.6–4.7 and §5, Slice 2A plan at [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md), Slice 2B plan at [`2026-09-25-direct-documentation-impl-01-slice-2b.md`](2026-09-25-direct-documentation-impl-01-slice-2b.md).

## Global Constraints

All constraints from Slices 2A and 2B apply unchanged. In addition:

- Route registration order matters: `PUT student/cases/{case}/clinical-activities/adr` and `PUT student/cases/{case}/clinical-activities/counselling` **must** be registered before `PUT student/cases/{case}/clinical-activities/{activity}` in `routes/web.php`, or Laravel will attempt to resolve the literal segments `adr`/`counselling` as a `{activity}` ULID route-model-binding and fail with a 404/500 instead of reaching the intended singleton controller method.
- The generic `{activity}` `sync`/`destroy` actions must refuse to operate on an `adr` or `counselling` row (`abort_unless(in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true), 404)`) even though route ordering already prevents normal traffic from reaching them that way — this is defense in depth against a client that discovers a singleton row's ULID (e.g. from an earlier JSON response) and tries to hit the generic row route directly.
- `SoapNote`'s `#[Fillable([...])]` currently lists `'lock_version'` (a Slice 1 regression, same shape as the `CaseClinicalProfile` one Slice 2A fixed). Task 1 removes it.
- The standalone SOAP page (`resources/js/pages/student/SoapEditor.vue`, the `student.cases.soap` GET route, `SoapController::show`/`update`) keeps working, completely unmodified, through Tasks 1–5. Task 1 adds a **new**, separate `PUT student/cases/{case}/soap-sync` endpoint that nothing in the old page calls. Only Task 6 touches the old page/routes, retiring them in the same commit that wires the in-editor SOAP section on and renames the sync endpoint back to the canonical `student/cases/{case}/soap` URI — there is no commit in this plan after which the app has no working way to save a SOAP note.
- Every `case_clinical_activities.details` write merges into the existing JSON rather than replacing it (see Architecture) — this applies to `syncAdr`, `syncCounselling`, and the generic repeatable-row `sync()` alike.
- ADR and Counselling singleton rows are protected against concurrent double-creation by both a partial unique database index (`case_clinical_activities_singleton_unique`, scoped to `activity_type IN ('adr', 'counselling')`) and an application-level lock on the parent `ClinicalCase` row before the `firstOrCreate` call, added in Task 3 and reused by Task 4 — do not rely on `firstOrCreate()` alone, which is not race-free under concurrent requests.
- Every sync/store request added in this slice uses `HasSyncEnvelope` + `RejectsUnknownFields`, per the Global Constraints established in Slice 2A/2B.
- `RejectsUnknownFields` rejects only **top-level** unknown keys; it does not descend into array/object values. Because `case_clinical_activities.details` is a JSON object whose keys are validated separately with dot-notation rules (`details.event`, `details.topics`, …), every request that accepts `details` also restricts its keys with Laravel's `array:key1,key2,…` rule listing exactly the allowed nested keys (Tasks 3, 4 and 5). Without that, an unknown nested key such as `details.patient_name` would sail through the top-level trait check and be silently persisted into the JSON column — the exact "nested details keys can pass through" gap the second review flagged.

## Review Focus

- **Wrong-door access to a singleton activity row.** A request to `PUT /student/cases/{case}/clinical-activities/{activity}` where `{activity}` is actually the case's ADR or Counselling row must be rejected (404), not silently accepted by the generic row endpoint and left inconsistent with what the singleton endpoint's `firstOrCreate` logic expects. Task 5's tests exercise this directly.
- **SOAP `lock_version` mass-assignment regression.** Same shape as the `CaseClinicalProfile` bug Slice 2A fixed: `SoapNote`'s Fillable must not include `'lock_version'`, or a client could set an arbitrary starting lock version. Task 1's test asserts a client-supplied `lock_version` in a create payload is ignored.
- **Singleton double-creation under concurrent first-sync requests.** Two near-simultaneous first syncs of ADR (or Counselling) for the same case must not produce two rows. Task 3's tests cover both the schema-level guard (a raw duplicate insert throws) and the application-level guard (two sequential controller calls reuse the same row), with an explicit note on why true parallel concurrency isn't exercised in single-process PHPUnit.
- **`details.*` partial-update overwriting sibling keys, or leaking stale detail data after a status change.** A one-field update to `details` on an already-populated ADR/Counselling/Intervention/Monitoring row must preserve every previously-saved sibling key; a status change that hides the detail fields in the UI must also clear them server-side. Tasks 3, 4 and 6's tests cover both directions.
- **An unknown nested `details` key silently persisting into the JSON column.** The `RejectsUnknownFields` trait only rejects top-level keys, so an unknown key *inside* the `details` object (e.g. `details.patient_name`) would otherwise be accepted and written to the JSON column. Every `details`-accepting request restricts its keys with `array:key1,key2,…`, and Tasks 3, 4 and 5 each include both a top-level and a nested unknown-field 422 test to pin this down.
- **Losing the old SOAP page's data path, or a dead link to it.** Task 1's tests prove the new sync endpoint round-trips all SOAP fields (including the new monitoring-plan fields) without touching the old page; Task 6's tests prove the old route is gone and `CaseShow.vue` no longer links anywhere dead, and that the SOAP audit events (`soap_note.created`/`soap_note.updated`) still fire through the new path exactly as the old controller recorded them.
- **Six-section progress/navigation regressions.** Adding the fifth and sixth section to `CaseEditor.vue`'s `sections` array must not break Previous/Next boundary logic or the nav-pill `aria-current` state for the sections Slices 2A/2B already shipped. Task 7's test and Task 9's manual pass both re-verify all six sections, not just the two new ones.

---

## Task 1: Add a new, additive SOAP sync endpoint (old page untouched)

**Revision:** The original draft replaced `SoapController::update()` in this task and only reconnected the frontend in Task 6, leaving the live `SoapEditor.vue` page broken (it POSTs via Inertia expecting a redirect; the new controller returns JSON) for every task in between. This task now adds a **separate** route/action (`PUT .../soap-sync` → `SoapController::sync()`) and leaves `show()`/`update()`/the existing `GET .../soap` and `PUT .../soap` routes completely alone. It also preserves the old controller's `soap_note.created`/`soap_note.updated` audit events (dropped in the original draft's replacement) and adds the SOAP Plan's structured monitoring-plan fields the field catalogue requires (§4.6 Plan: "monitoring parameter and interval, or justified not applicable").

**Files:**
- Create: `database/migrations/2026_09_30_000000_add_monitoring_plan_fields_to_soap_notes.php`
- Modify: `app/Models/SoapNote.php` (remove `'lock_version'` from Fillable, implement `Syncable`, add monitoring-plan fields to Fillable)
- Create: `app/Http/Requests/Student/UpdateSoapNoteRequest.php`
- Modify: `app/Http/Controllers/Student/SoapController.php` (add `sync()` alongside the existing `show()`/`update()`, preserving audit events)
- Modify: `routes/web.php` (add the new route; existing SOAP routes untouched)
- Test: `tests/Feature/SoapNoteSyncTest.php`

**Interfaces:**
- Produces: `PUT /student/cases/{case}/soap-sync` (new route, new name `student.cases.soap.sync`) returning `{ "section": {subjective, objective, assessment, plan, drug_related_problem_status, drug_related_problem_categories, monitoring_plan, monitoring_plan_not_applicable_reason, lock_version, updated_at} }`. Lazily creates revision 1 of the `SoapNote` on first sync, exactly as `CaseClinicalProfileController` lazily creates the profile (Slice 2A Task 5) — never on page render. The existing `GET /student/cases/{case}/soap` and `PUT /student/cases/{case}/soap` routes, `SoapController::show()`/`update()`, and `SoapEditor.vue` are all unchanged and still fully functional after this task.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoapNoteSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_soap_page_and_its_update_route_still_work_unmodified(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->get("/student/cases/{$case->id}/soap")->assertOk();

        $response = $this->put("/student/cases/{$case->id}/soap", ['subjective' => 'Via the old page.']);

        $response->assertRedirect();
        $this->assertSame('Via the old page.', $case->fresh()->currentSoap->subjective);
    }

    public function test_first_sync_lazily_creates_the_soap_note_and_persists_all_fields_including_monitoring_plan(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'subjective' => 'Patient reports headache for 3 days.',
            'drug_related_problem_status' => 'identified',
            'drug_related_problem_categories' => ['dose_too_low', 'monitoring_required'],
            'monitoring_plan' => 'Blood pressure daily for 3 days.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.subjective', 'Patient reports headache for 3 days.');
        $this->assertNotNull($case->fresh()->currentSoap);
        $this->assertSame(['dose_too_low', 'monitoring_required'], $case->fresh()->currentSoap->drug_related_problem_categories);
        $this->assertSame('Blood pressure daily for 3 days.', $case->fresh()->currentSoap->monitoring_plan);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => SoapNote::class,
            'event_type' => 'soap_note.created',
        ]);
    }

    public function test_a_second_sync_records_an_updated_audit_event_not_another_created_event(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'First.',
        ])->assertOk();
        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'objective' => 'BP 120/80.',
        ])->assertOk();

        $this->assertSame(1, AuditEvent::query()->where('event_type', 'soap_note.created')->count());
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'soap_note.updated')->count());
    }

    public function test_monitoring_plan_not_applicable_reason_can_be_recorded_instead_of_a_plan(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'monitoring_plan_not_applicable_reason' => 'Single-dose administration; no ongoing monitoring indicated.',
        ]);

        $response->assertOk();
        $this->assertSame('Single-dose administration; no ongoing monitoring indicated.', $case->fresh()->currentSoap->monitoring_plan_not_applicable_reason);
    }

    public function test_a_single_field_edit_does_not_erase_other_saved_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'subjective' => 'Headache.', 'objective' => 'BP 140/90.',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'assessment' => 'Tension-type headache.',
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->currentSoap;
        $this->assertSame('Headache.', $fresh->subjective);
        $this->assertSame('BP 140/90.', $fresh->objective);
        $this->assertSame('Tension-type headache.', $fresh->assessment);
    }

    public function test_lock_version_is_not_mass_assignable(): void
    {
        [, $student, $case] = $this->makeCase();
        $soap = SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'revision_number' => 1, 'author_id' => $student->id, 'last_saved_by' => $student->id,
            'lock_version' => 99,
        ]);

        $this->assertSame(0, $soap->fresh()->lock_version);
    }

    public function test_a_stale_base_lock_version_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'First',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'Conflicting',
        ])->assertStatus(409);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'subjective' => 'x', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_a_different_student_cannot_sync_the_soap_note(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/soap-sync", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'x',
        ])->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=SoapNoteSyncTest`
Expected: the first test (old page) PASSES already (nothing changed yet); every other test FAILS — route not found / columns missing.

- [ ] **Step 3: Write the monitoring-plan fields migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->text('monitoring_plan')->nullable()->after('plan');
            $table->text('monitoring_plan_not_applicable_reason')->nullable()->after('monitoring_plan');
        });
    }

    public function down(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->dropColumn(['monitoring_plan', 'monitoring_plan_not_applicable_reason']);
        });
    }
};
```

- [ ] **Step 4: Fix `SoapNote`'s Fillable and implement `Syncable`**

In `app/Models/SoapNote.php`, remove `'lock_version',` from the `#[Fillable([...])]` array and add `'monitoring_plan', 'monitoring_plan_not_applicable_reason',` after `'plan',`. Add imports and apply the trait/interface:

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

```php
class SoapNote extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

This model backs exactly one section, so it does not override `lockVersionColumn()`.

- [ ] **Step 5: Write `UpdateSoapNoteRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSoapNoteRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'subjective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'monitoring_plan' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'monitoring_plan_not_applicable_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'drug_related_problem_status' => ['sometimes', 'nullable', 'in:none_identified,identified,unable_to_assess'],
            'drug_related_problem_categories' => ['sometimes', 'nullable', 'array'],
            'drug_related_problem_categories.*' => ['in:untreated_indication,medicine_without_indication,ineffective_medicine,dose_too_low,dose_too_high,adr,interaction,non_adherence,duplication,administration_problem,monitoring_required,other'],
        ];
    }
}
```

- [ ] **Step 6: Add `sync()` to `SoapController`, preserving audit events**

In `app/Http/Controllers/Student/SoapController.php`, keep `show()` and `update()` exactly as they are today (do not modify them in this task) and add the imports and method below:

```php
use App\Http\Requests\Student\UpdateSoapNoteRequest;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;
```

```php
    public function sync(UpdateSoapNoteRequest $request, ClinicalCase $case, SectionSyncService $sync, AuditTrail $audit): JsonResponse
    {
        $wasNew = $case->currentSoap === null;
        $soap = $case->currentSoap ?? SoapNote::query()->create([
            'institution_id' => $case->institution_id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $request->user()->id,
            'last_saved_by' => $request->user()->id,
        ]);

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $soap,
            $request->user(),
            'soap',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$request->sectionData(), 'last_saved_by' => $request->user()->id],
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        // Preserves the audit trail the pre-existing SoapController::update()
        // recorded (soap_note.created / soap_note.updated) — SectionSyncService
        // only ever fires a conflict-resolution audit event, so this
        // controller fires the creation/update events itself rather than
        // silently dropping them when the endpoint was rebuilt on the shared
        // sync engine.
        if (in_array($result['status'], ['saved', 'resolved_replaced'], true)) {
            $audit->record($request->user(), $result['model'], $wasNew ? 'soap_note.created' : 'soap_note.updated', [
                'case_id' => $case->id,
                'revision_number' => $result['model']->revision_number,
            ]);
        }

        return response()->json(['section' => $this->payload($result['model'])], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(SoapNote $soap): array
    {
        return [
            'subjective' => $soap->subjective,
            'objective' => $soap->objective,
            'assessment' => $soap->assessment,
            'plan' => $soap->plan,
            'monitoring_plan' => $soap->monitoring_plan,
            'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
            'drug_related_problem_status' => $soap->drug_related_problem_status,
            'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            'lock_version' => $soap->lock_version,
            'updated_at' => $soap->updated_at->toIso8601String(),
        ];
    }
```

`AuditTrail` and `SoapNote` are already imported by the existing controller. `ClinicalCase` too.

- [ ] **Step 7: Add the new route — existing SOAP routes untouched**

In `routes/web.php`, add this new line immediately after the existing `student.cases.soap.update` route (do **not** modify either existing SOAP route):

```php
        Route::put('student/cases/{case}/soap-sync', [SoapController::class, 'sync'])->name('student.cases.soap.sync');
```

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=SoapNoteSyncTest`
Expected: PASS (9 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 220 previous passed (Slice 2A+2B total) + 9 new — 229 passed / 2 skipped (231 total).

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_09_30_000000_add_monitoring_plan_fields_to_soap_notes.php app/Models/SoapNote.php app/Http/Requests/Student/UpdateSoapNoteRequest.php app/Http/Controllers/Student/SoapController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/SoapNoteSyncTest.php
git commit -m "feat: add an additive SOAP sync endpoint with monitoring-plan fields, preserving the existing page and its audit events"
```

---

## Task 2: `lock_version` and `Syncable` for `CaseClinicalActivity`

Identical shape to Slice 2B Task 1, applied to the one remaining repeatable-schema table that didn't get `lock_version` there because it wasn't in scope yet.

**Files:**
- Create: `database/migrations/2026_09_30_000001_add_lock_version_to_case_clinical_activities.php`
- Modify: `app/Models/CaseClinicalActivity.php` (implement `Syncable`)
- Test: `tests/Feature/CaseClinicalActivityLockVersionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Contracts\Syncable;
use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseClinicalActivityLockVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_clinical_activities_has_a_lock_version_column(): void
    {
        $this->assertTrue(Schema::hasColumn('case_clinical_activities', 'lock_version'));
    }

    public function test_the_model_implements_syncable(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => CaseStatus::Draft,
        ]);
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Intervention->value, 'recorded_by' => $student->id,
        ]);

        $this->assertInstanceOf(Syncable::class, $activity);
        $this->assertSame(0, $activity->getLockVersion('clinical_activities'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseClinicalActivityLockVersionTest`
Expected: FAIL.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_clinical_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('case_clinical_activities', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
```

- [ ] **Step 4: Update `CaseClinicalActivity`**

Add imports and apply the trait/interface, same shape as every other model in this series (this model does not override `lockVersionColumn()`):

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

```php
class CaseClinicalActivity extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

- [ ] **Step 5: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseClinicalActivityLockVersionTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 229 previous passed + 2 new — 231 passed / 2 skipped (233 total).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_30_000001_add_lock_version_to_case_clinical_activities.php app/Models/CaseClinicalActivity.php tests/Feature/CaseClinicalActivityLockVersionTest.php
git commit -m "feat: add lock_version and Syncable to CaseClinicalActivity"
```

---

## Task 3: Suspected ADR (singleton) backend — concurrency-safe, full field set, `details` merge-not-replace

**Revision:** The original draft's `firstOrCreate()` alone cannot stop two concurrent first-sync requests from both inserting an ADR row for the same case. This task adds a partial unique database index (`case_clinical_activities_singleton_unique`, scoped to `adr`/`counselling` rows only — Intervention/Monitoring stay repeatable) plus a `lockForUpdate()` on the parent case before the `firstOrCreate` call. It also fixes the partial-update-overwrites-the-whole-JSON-object bug (`details` is now merged, not replaced) and clears `details` when `status` leaves `yes`.

**Files:**
- Create: `database/migrations/2026_09_30_000002_add_singleton_unique_index_to_case_clinical_activities.php`
- Create: `app/Http/Requests/Student/UpdateAdrActivityRequest.php`
- Create: `app/Http/Controllers/Student/CaseClinicalActivityController.php`
- Modify: `app/Services/SectionSyncService.php` (extend `SECTION_MODELS`)
- Modify: `routes/web.php`
- Test: `tests/Feature/AdrActivitySyncTest.php`

**Interfaces:**
- Produces: `PUT /student/cases/{case}/clinical-activities/adr` returning `{ "activity": {status, details, lock_version, updated_at} }`. Lazily creates the ADR row on first sync, race-safe.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdrActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_adr_row_exists_until_the_first_deliberate_sync(): void
    {
        [, , $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
    }

    public function test_a_raw_duplicate_singleton_insert_is_rejected_by_the_database(): void
    {
        [, $student, $case] = $this->makeCase();
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value, 'recorded_by' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value, 'recorded_by' => $student->id,
        ]);
    }

    public function test_two_sequential_syncs_reuse_the_same_row_rather_than_creating_a_second_one(): void
    {
        // True parallel-request concurrency isn't reproducible in single-process
        // PHPUnit; this test plus the raw-duplicate-insert test above are the
        // intended coverage — the first proves the app-level firstOrCreate path
        // is idempotent under normal sequential use, the second proves the
        // database itself refuses a duplicate if two requests ever did race
        // past the application-level lockForUpdate.
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $first = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ]);
        $second = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'unable_to_assess',
        ]);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('activity.id') ?? true, $first->json('activity.id') ?? true);
        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_answering_no_does_not_require_adr_details(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'no',
        ]);

        $response->assertOk();
        $response->assertJsonPath('activity.status', 'no');
    }

    public function test_answering_yes_requires_event_and_suspected_medicine(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'yes',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['details.event', 'details.suspected_medicine']);
    }

    public function test_answering_yes_with_details_persists_them(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin', 'seriousness' => 'non_serious'],
        ]);

        $response->assertOk();
        $this->assertSame('Rash', $case->fresh()->clinicalActivities->first()->details['event']);
    }

    public function test_a_single_detail_field_edit_after_yes_preserves_previously_saved_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin'],
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['action_taken' => 'Medicine withdrawn.'],
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Rash', $fresh->details['event']);
        $this->assertSame('Amoxicillin', $fresh->details['suspected_medicine']);
        $this->assertSame('Medicine withdrawn.', $fresh->details['action_taken']);
    }

    public function test_changing_status_away_from_yes_clears_the_hidden_detail_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'no',
        ])->assertOk();

        $this->assertNull($case->fresh()->clinicalActivities->first()->details);
    }

    public function test_a_different_student_cannot_sync_the_adr_activity(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ])->assertForbidden();
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'yes',
            'details' => ['event' => 'Rash', 'suspected_medicine' => 'Amoxicillin', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=AdrActivitySyncTest`
Expected: FAIL — route not found; index doesn't exist yet.

- [ ] **Step 3: Write the singleton partial-unique-index migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX case_clinical_activities_singleton_unique ON case_clinical_activities (clinical_case_id, activity_type) WHERE activity_type IN ('adr', 'counselling')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS case_clinical_activities_singleton_unique');
    }
};
```

Note: Laravel's `Blueprint` has no partial-index helper, so this is raw `DB::statement()` — not `Schema::table()->change()`, so it needs no `dbal`. Both PostgreSQL and SQLite support `CREATE UNIQUE INDEX ... WHERE ...`; confirm `ClinicalActivityType::Adr->value` and `::Counselling->value` are exactly `'adr'`/`'counselling'` by checking `app/Enums/ClinicalActivityType.php` before running this migration — if the enum's stored values differ, use those exact strings in the `WHERE` clause instead.

- [ ] **Step 4: Extend `SectionSyncService::SECTION_MODELS`**

In `app/Services/SectionSyncService.php`, add the import `use App\Models\CaseClinicalActivity;` and this entry to `SECTION_MODELS`:

```php
        'clinical_activity_adr' => CaseClinicalActivity::class,
```

- [ ] **Step 5: Write `UpdateAdrActivityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdrActivityRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'status' => ['sometimes', 'required', Rule::in(['yes', 'no', 'unable_to_assess'])],
            'details' => ['sometimes', 'nullable', 'array:event,onset_reference,stop_reference,suspected_medicine,dose_route_frequency,concomitant_medicines,relevant_tests,action_taken,seriousness,outcome,dechallenge,rechallenge'],
            'details.event' => ['required_if:status,yes', 'nullable', 'string', 'max:1000'],
            'details.onset_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'details.stop_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'details.suspected_medicine' => ['required_if:status,yes', 'nullable', 'string', 'max:255'],
            'details.dose_route_frequency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.concomitant_medicines' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.relevant_tests' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.action_taken' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.seriousness' => ['sometimes', 'nullable', Rule::in(['serious', 'non_serious'])],
            'details.outcome' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.dechallenge' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.rechallenge' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 6: Write `CaseClinicalActivityController` (ADR method only — Tasks 4–5 extend this same class)**

```php
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

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
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
```

- [ ] **Step 7: Add the route**

In `routes/web.php`, add inside the `role:student` group, immediately after the Medication Chart routes. **This must come before Task 5's `{activity}` route** (see Global Constraints):

```php
        Route::put('student/cases/{case}/clinical-activities/adr', [CaseClinicalActivityController::class, 'syncAdr'])->name('student.cases.clinical-activities.adr.sync');
```

Add the import: `use App\Http\Controllers\Student\CaseClinicalActivityController;`

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=AdrActivitySyncTest`
Expected: PASS (11 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 231 previous passed + 11 new — 242 passed / 2 skipped (244 total).

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_09_30_000002_add_singleton_unique_index_to_case_clinical_activities.php app/Http/Requests/Student/UpdateAdrActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php app/Services/SectionSyncService.php routes/web.php resources/js/actions resources/js/routes tests/Feature/AdrActivitySyncTest.php
git commit -m "feat: add the concurrency-safe Suspected ADR singleton backend with details-merge semantics"
```

---

## Task 4: Patient counselling (singleton) backend — full field set

**Revision:** Reuses Task 3's concurrency guard (`findOrCreateSingleton()`) and `details`-merge helper. The original draft's UI only ever showed `topics`; the request already validated the full catalogue field set (`medicine_purpose`, `administration`, `adherence`, `precautions`, `adverse_effects`, `storage`, `lifestyle_follow_up`, `understanding_checked`) but nothing rendered them — Task 7 of this plan fixes the UI side; this task's job is making sure the backend clears them correctly when status leaves `performed`/`planned`.

**Files:**
- Create: `app/Http/Requests/Student/UpdateCounsellingActivityRequest.php`
- Modify: `app/Http/Controllers/Student/CaseClinicalActivityController.php` (add `syncCounselling`)
- Modify: `app/Services/SectionSyncService.php` (extend `SECTION_MODELS`)
- Modify: `routes/web.php`
- Test: `tests/Feature/CounsellingActivitySyncTest.php`

**Interfaces:**
- Produces: `PUT /student/cases/{case}/clinical-activities/counselling` returning `{ "activity": {...} }`, identical shape to Task 3.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CounsellingActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_indicated_does_not_require_counselling_details(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated',
        ]);

        $response->assertOk();
    }

    public function test_performed_requires_topics(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details.topics');
    }

    public function test_planned_requires_topics(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'planned',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details.topics');
    }

    public function test_performed_with_the_full_field_set_persists_them(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => [
                'topics' => 'Medicine purpose and dosing schedule.',
                'medicine_purpose' => 'Blood pressure control.',
                'administration' => 'One tablet every morning with water.',
                'adherence' => 'Use a daily reminder.',
                'precautions' => 'Avoid grapefruit juice.',
                'adverse_effects' => 'Dizziness on standing.',
                'storage' => 'Store below 25C, away from moisture.',
                'lifestyle_follow_up' => 'Reduce dietary salt.',
                'understanding_checked' => true,
            ],
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Medicine purpose and dosing schedule.', $fresh->details['topics']);
        $this->assertSame('Blood pressure control.', $fresh->details['medicine_purpose']);
        $this->assertTrue($fresh->details['understanding_checked']);
    }

    public function test_a_single_detail_field_edit_preserves_previously_saved_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.', 'storage' => 'Store below 25C.'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['understanding_checked' => true],
        ])->assertOk();

        $fresh = $case->fresh()->clinicalActivities->first();
        $this->assertSame('Dosing schedule.', $fresh->details['topics']);
        $this->assertSame('Store below 25C.', $fresh->details['storage']);
        $this->assertTrue($fresh->details['understanding_checked']);
    }

    public function test_changing_status_away_from_performed_clears_the_hidden_detail_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.'],
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1, 'status' => 'not_indicated',
        ])->assertOk();

        $this->assertNull($case->fresh()->clinicalActivities->first()->details);
    }

    public function test_a_different_student_cannot_sync_the_counselling_activity(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated',
        ])->assertForbidden();
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Dosing schedule.', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=CounsellingActivitySyncTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Write `UpdateCounsellingActivityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCounsellingActivityRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'status' => ['sometimes', 'required', Rule::in(['performed', 'planned', 'not_indicated', 'unable_to_perform'])],
            'details' => ['sometimes', 'nullable', 'array:topics,medicine_purpose,administration,adherence,precautions,adverse_effects,storage,lifestyle_follow_up,understanding_checked'],
            'details.topics' => ['required_if:status,performed', 'required_if:status,planned', 'nullable', 'string', 'max:2000'],
            'details.medicine_purpose' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.administration' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.adherence' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.precautions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.adverse_effects' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.storage' => ['sometimes', 'nullable', 'string', 'max:500'],
            'details.lifestyle_follow_up' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.understanding_checked' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Add `syncCounselling` to `CaseClinicalActivityController`**

Add the import `use App\Http\Requests\Student\UpdateCounsellingActivityRequest;` and this method (after `syncAdr`), reusing `findOrCreateSingleton()` and `mergeOrClearDetails()` from Task 3:

```php
    public function syncCounselling(UpdateCounsellingActivityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $activity = $this->findOrCreateSingleton($case, ClinicalActivityType::Counselling, $request->user()->id);

        $envelope = $request->syncEnvelope();
        $data = $this->mergeOrClearDetails($activity, $request->sectionData(), leavesConditionalState: fn (array $data): bool => array_key_exists('status', $data) && ! in_array($data['status'], ['performed', 'planned'], true));

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activity_counselling',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
    }
```

- [ ] **Step 5: Extend `SectionSyncService::SECTION_MODELS`**

Add `'clinical_activity_counselling' => CaseClinicalActivity::class,` to the constant.

- [ ] **Step 6: Add the route**

Immediately after the ADR route, still before Task 5's `{activity}` route:

```php
        Route::put('student/cases/{case}/clinical-activities/counselling', [CaseClinicalActivityController::class, 'syncCounselling'])->name('student.cases.clinical-activities.counselling.sync');
```

- [ ] **Step 7: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 8: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CounsellingActivitySyncTest`
Expected: PASS (9 tests).

- [ ] **Step 9: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 242 previous passed + 9 new — 251 passed / 2 skipped (253 total).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Student/UpdateCounsellingActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php app/Services/SectionSyncService.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CounsellingActivitySyncTest.php
git commit -m "feat: add the Patient counselling singleton section backend with the full field set and details-merge semantics"
```

---

## Task 5: Pharmacist intervention and Monitoring follow-up (repeatable rows) backend — offline-capable creation, full field set

**Revision:** Reuses Slice 2B's `SectionSyncService::create()` (with the class-check hardening) so intervention/monitoring rows get the same offline-capable creation as vitals/investigations/medications. `details` merges instead of replacing here too.

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php` (restrict `activity_type`, add `client_operation_id`)
- Create: `app/Http/Requests/Student/UpdateCaseClinicalActivityRequest.php`
- Modify: `app/Http/Controllers/Student/CaseClinicalActivityController.php` (add `store`, `sync`, `destroy`)
- Modify: `app/Services/SectionSyncService.php` (extend `SECTION_MODELS`)
- Modify: `routes/web.php`
- Test: `tests/Feature/RepeatableClinicalActivitySyncTest.php`

**Interfaces:**
- Produces: `POST /student/cases/{case}/clinical-activities`, `PUT /student/cases/{case}/clinical-activities/{activity}`, `DELETE /student/cases/{case}/clinical-activities/{activity}` — restricted to `activity_type` of `intervention` or `monitoring`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepeatableClinicalActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(),
            'activity_type' => ClinicalActivityType::Intervention->value,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_creating_an_adr_or_counselling_row_through_the_generic_endpoint_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(),
            'activity_type' => ClinicalActivityType::Adr->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('activity_type');
    }

    public function test_editing_an_adr_row_through_the_generic_row_endpoint_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no',
        ])->assertOk();
        $adr = $case->fresh()->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$adr->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'details' => ['problem' => 'x'],
        ]);

        $response->assertNotFound();
    }

    public function test_a_single_detail_field_edit_on_an_intervention_row_preserves_sibling_keys(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'details' => ['problem' => 'Dose too low', 'recommendation' => 'Increase to 1g TID'],
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'details' => ['outcome' => 'accepted'],
        ]);

        $response->assertOk();
        $fresh = $activity->fresh();
        $this->assertSame('Dose too low', $fresh->details['problem']);
        $this->assertSame('Increase to 1g TID', $fresh->details['recommendation']);
        $this->assertSame('accepted', $fresh->details['outcome']);
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'activity_type' => ClinicalActivityType::Monitoring->value];

        $this->postJson("/student/cases/{$case->id}/clinical-activities", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->clinicalActivities);
    }

    public function test_owning_student_can_delete_a_monitoring_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Monitoring);

        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'clinical_activities.deleted']);
    }

    public function test_deleting_a_monitoring_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Monitoring);
        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'details' => ['notes' => 'Bumps the lock version.'],
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->clinicalActivities);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'clinical_activities.deleted']);
    }

    public function test_an_unknown_top_level_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_nested_details_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'details' => ['problem' => 'Dose too low', 'patient_name' => 'Should be rejected'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('details');
    }

    public function test_a_different_student_cannot_create_edit_or_delete_an_activity_row(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Intervention);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/clinical-activities", [
            'client_operation_id' => (string) Str::uuid(), 'activity_type' => ClinicalActivityType::Intervention->value,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}")->assertForbidden();
    }

    private function makeActivity(ClinicalCase $case, User $student, ClinicalActivityType $type): CaseClinicalActivity
    {
        return CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => $type->value, 'recorded_by' => $student->id,
        ]);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=RepeatableClinicalActivitySyncTest`
Expected: FAIL.

- [ ] **Step 3: Fix `StoreCaseClinicalActivityRequest`**

Replace the full contents of `app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseClinicalActivityRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'activity_type' => ['required', Rule::in([ClinicalActivityType::Intervention->value, ClinicalActivityType::Monitoring->value])],
            'status' => ['nullable', 'string', 'max:30'],
            'details' => ['nullable', 'array:problem,recommendation,recipient,communication_method,case_date,outcome,follow_up,parameter,result,observed_on,notes'],
        ];
    }
}
```

- [ ] **Step 4: Write `UpdateCaseClinicalActivityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseClinicalActivityRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('activity')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            ...$this->syncEnvelopeRules(),
            'status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];

        if ($this->route('activity')?->activity_type === ClinicalActivityType::Intervention) {
            return [
                ...$rules,
                'details' => ['sometimes', 'nullable', 'array:problem,recommendation,recipient,communication_method,case_date,outcome,follow_up'],
                'details.problem' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'details.recommendation' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'details.recipient' => ['sometimes', 'nullable', 'string', 'max:120'],
                'details.communication_method' => ['sometimes', 'nullable', 'string', 'max:60'],
                'details.case_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
                'details.outcome' => ['sometimes', 'nullable', Rule::in(['accepted', 'partially_accepted', 'not_accepted', 'pending', 'not_communicated'])],
                'details.follow_up' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ];
        }

        return [
            ...$rules,
            'details' => ['sometimes', 'nullable', 'array:parameter,result,observed_on,notes'],
            'details.parameter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.result' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'details.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Add `store`, `sync`, `destroy` to `CaseClinicalActivityController`, using `create()`'s class-check and merging `details`**

Add imports:

```php
use App\Http\Requests\Student\StoreCaseClinicalActivityRequest;
use App\Http\Requests\Student\UpdateCaseClinicalActivityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
```

Add the methods (after `syncCounselling`):

```php
    public function store(StoreCaseClinicalActivityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $data = $request->validated();
        $clientOperationId = $data['client_operation_id'];
        unset($data['client_operation_id']);

        $result = $sync->create(
            fn () => CaseClinicalActivity::query()->create([
                ...$data,
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'recorded_by' => $request->user()->id,
            ]),
            $request->user(),
            'clinical_activities',
            $clientOperationId,
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function sync(UpdateCaseClinicalActivityRequest $request, ClinicalCase $case, CaseClinicalActivity $activity, SectionSyncService $sync): JsonResponse
    {
        abort_unless($activity->clinical_case_id === $case->id, 404);
        abort_unless(in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true), 404);

        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();
        if (array_key_exists('details', $data)) {
            $data['details'] = [...($activity->details ?? []), ...$data['details']];
        }

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activities',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $data,
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseClinicalActivity $activity, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $activity);
        abort_unless($activity->clinical_case_id === $case->id, 404);
        abort_unless(in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true), 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($activity, $request->user(), 'clinical_activities', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            return response()->json(['activity' => $this->payload($result['model'])], 409);
        }

        return response()->noContent();
    }
```

- [ ] **Step 6: Extend `SectionSyncService::SECTION_MODELS`**

Add `'clinical_activities' => CaseClinicalActivity::class,` (already imported by Task 3).

- [ ] **Step 7: Add routes**

In `routes/web.php`, add **after** the ADR and Counselling routes (ordering matters — see Global Constraints):

```php
        Route::post('student/cases/{case}/clinical-activities', [CaseClinicalActivityController::class, 'store'])->name('student.cases.clinical-activities.store');
        Route::put('student/cases/{case}/clinical-activities/{activity}', [CaseClinicalActivityController::class, 'sync'])->name('student.cases.clinical-activities.sync');
        Route::delete('student/cases/{case}/clinical-activities/{activity}', [CaseClinicalActivityController::class, 'destroy'])->name('student.cases.clinical-activities.destroy');
```

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=RepeatableClinicalActivitySyncTest`
Expected: PASS (10 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 251 previous passed + 10 new — 261 passed / 2 skipped (263 total).

- [ ] **Step 11: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php app/Http/Requests/Student/UpdateCaseClinicalActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php app/Services/SectionSyncService.php routes/web.php resources/js/actions resources/js/routes tests/Feature/RepeatableClinicalActivitySyncTest.php
git commit -m "feat: add Pharmacist intervention and Monitoring follow-up repeatable-row backend with offline-capable creation and details-merge semantics"
```

---

## Task 6: Atomically retire the standalone SOAP page and wire the SOAP section into the editor

**Revision:** This is the one commit in this plan that touches the old SOAP page/routes — it removes them and switches the in-editor section on together, so there is never a state where the app has no working SOAP save path. The sync endpoint's URI/name is renamed from `soap-sync`/`student.cases.soap.sync` back to the canonical `soap`/`student.cases.soap.update` in this same commit, now that the old `PUT .../soap` action is gone and the name is free.

**Files:**
- Delete: `resources/js/pages/student/SoapEditor.vue`
- Create: `resources/js/pages/student/case-editor/SoapSection.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue`
- Modify: `resources/js/pages/student/CaseShow.vue` (remove the "Edit SOAP" button; repoint "Start editing" to `/edit`)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass `soap` prop)
- Modify: `app/Http/Controllers/Student/SoapController.php` (remove `show()`/`update()`, the old page's only callers)
- Modify: `routes/web.php` (remove the old GET/PUT `.../soap` routes; rename `.../soap-sync` back to `.../soap`)
- Test: `tests/Feature/CaseEditorPageTest.php` (extend)
- Test: `tests/Feature/SoapNoteSyncTest.php` (remove the now-obsolete "old page still works" test; update the endpoint URL in every other test from `soap-sync` to `soap`)

- [ ] **Step 1: Write the failing test (extend `CaseEditorPageTest`)**

Append to the class:

```php
    public function test_editor_page_includes_the_current_soap_note(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        \App\Models\SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id, 'revision_number' => 1,
            'subjective' => 'Headache.', 'author_id' => $student->id, 'last_saved_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page->where('soap.subjective', 'Headache.'));
    }

    public function test_the_old_soap_page_route_no_longer_exists(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->get("/student/cases/{$case->id}/soap")->assertNotFound();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: FAIL — `soap` prop missing; old route still resolves (404 test fails because the route still exists).

- [ ] **Step 3: Update `SoapNoteSyncTest`**

Remove the `test_the_old_soap_page_and_its_update_route_still_work_unmodified` method (the old page is gone as of this task) and replace every `/soap-sync` URL in the remaining tests with `/soap`.

- [ ] **Step 4: Extend `CaseEditorController`**

Add the import `use App\Models\SoapNote;`. Add a `soap` prop to the `Inertia::render(...)` array:

```php
            'soap' => $case->currentSoap === null ? null : [
                'subjective' => $case->currentSoap->subjective,
                'objective' => $case->currentSoap->objective,
                'assessment' => $case->currentSoap->assessment,
                'plan' => $case->currentSoap->plan,
                'monitoring_plan' => $case->currentSoap->monitoring_plan,
                'monitoring_plan_not_applicable_reason' => $case->currentSoap->monitoring_plan_not_applicable_reason,
                'drug_related_problem_status' => $case->currentSoap->drug_related_problem_status,
                'drug_related_problem_categories' => $case->currentSoap->drug_related_problem_categories,
                'lock_version' => $case->currentSoap->lock_version,
                'updated_at' => $case->currentSoap->updated_at->toIso8601String(),
            ],
```

- [ ] **Step 5: Remove `show()`/`update()` from `SoapController` and rename `sync()`'s route**

In `app/Http/Controllers/Student/SoapController.php`, delete the `show()` and `update()` methods entirely — `sync()` (added in Task 1) is now the controller's only action. Remove the now-unused `use Illuminate\Http\RedirectResponse;`, `use Illuminate\Support\Facades\DB;`, `use Inertia\Inertia;`, `use Inertia\Response;` imports if nothing else in the class still needs them.

In `routes/web.php`, remove these two lines entirely:

```php
        Route::get('student/cases/{case}/soap', [SoapController::class, 'show'])->name('student.cases.soap'),
        Route::put('student/cases/{case}/soap', [SoapController::class, 'update'])->name('student.cases.soap.update'),
```

Rename the route Task 1 added from this:

```php
        Route::put('student/cases/{case}/soap-sync', [SoapController::class, 'sync'])->name('student.cases.soap.sync');
```

to this:

```php
        Route::put('student/cases/{case}/soap', [SoapController::class, 'sync'])->name('student.cases.soap.update');
```

- [ ] **Step 6: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (6 tests).

Run (PowerShell): `php artisan test --filter=SoapNoteSyncTest`
Expected: PASS (8 tests — one fewer than Task 1 since the "old page still works" test was removed, and every remaining test now hits `/soap`).

- [ ] **Step 7: Write `SoapSection.vue`**

```vue
<script setup lang="ts">
import { RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

const DRUG_RELATED_PROBLEM_CATEGORIES = [
    'untreated_indication', 'medicine_without_indication', 'ineffective_medicine', 'dose_too_low',
    'dose_too_high', 'adr', 'interaction', 'non_adherence', 'duplication', 'administration_problem',
    'monitoring_required', 'other',
];

type SoapPayload = SyncedSection & {
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    monitoring_plan: string | null;
    monitoring_plan_not_applicable_reason: string | null;
    drug_related_problem_status: string | null;
    drug_related_problem_categories: string[] | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: SoapPayload }>();

const { payload, state, edit, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace } =
    useSectionSync<SoapPayload>({
        userId: props.userId,
        resourceId: props.caseId,
        sectionKey: 'soap',
        endpoint: `/student/cases/${props.caseId}/soap`,
        initialPayload: props.initial,
    });

const statusLabel = computed(() => ({
    saving: 'Saving…', server: 'Saved', device: 'Saved on this device', unsynced: 'Unsynced changes', failed: 'Sync failed', conflict: 'Conflict — review changes',
})[state.value]);

const monitoringNotApplicable = computed({
    get: () => payload.value.monitoring_plan_not_applicable_reason !== null,
    set: (checked: boolean) => {
        if (!checked) payload.value.monitoring_plan_not_applicable_reason = null;
        edit();
    },
});

function toggleCategory(category: string) {
    const current = payload.value.drug_related_problem_categories ?? [];
    payload.value.drug_related_problem_categories = current.includes(category)
        ? current.filter((c) => c !== category)
        : [...current, category];
    edit();
}
</script>

<template>
    <section aria-labelledby="soap-heading" class="space-y-5">
        <div class="flex items-center justify-between">
            <h2 id="soap-heading" class="font-display text-lg text-[#0b2942] dark:text-white">SOAP</h2>
            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-test="soap-status">
                <component :is="state === 'saving' ? RefreshCw : state === 'server' ? Check : state === 'device' ? CloudOff : state === 'unsynced' ? FileClock : AlertTriangle" class="size-3.5" :class="state === 'saving' ? 'animate-spin' : ''" />
                {{ statusLabel }}
            </span>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Subjective</span>
            <textarea v-model="payload.subjective" rows="4" maxlength="5000" data-test="soap-subjective" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.subjective" />
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Objective</span>
            <textarea v-model="payload.objective" rows="4" maxlength="5000" data-test="soap-objective" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.objective" />
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Assessment</span>
            <textarea v-model="payload.assessment" rows="4" maxlength="5000" data-test="soap-assessment" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.assessment" />
        </label>

        <fieldset>
            <legend class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Drug-related problem status</legend>
            <div class="flex flex-wrap gap-3 text-sm">
                <label v-for="option in ['none_identified', 'identified', 'unable_to_assess']" :key="option" class="flex items-center gap-1.5">
                    <input v-model="payload.drug_related_problem_status" type="radio" :value="option" :data-test="`drp-status-${option}`" @change="edit" />
                    {{ option.replace(/_/g, ' ') }}
                </label>
            </div>
        </fieldset>

        <div v-if="payload.drug_related_problem_status === 'identified'" class="flex flex-wrap gap-2">
            <button
                v-for="category in DRUG_RELATED_PROBLEM_CATEGORIES"
                :key="category"
                type="button"
                :data-test="`drp-category-${category}`"
                class="rounded-full border px-3 py-1 text-xs font-semibold"
                :class="(payload.drug_related_problem_categories ?? []).includes(category) ? 'border-[#0b2942] bg-[#0b2942] text-white' : 'border-slate-200 text-slate-500'"
                @click="toggleCategory(category)"
            >
                {{ category.replace(/_/g, ' ') }}
            </button>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Plan</span>
            <textarea v-model="payload.plan" rows="4" maxlength="5000" data-test="soap-plan" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.plan" />
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Monitoring plan</span>
            <textarea v-model="payload.monitoring_plan" rows="3" maxlength="2000" :disabled="monitoringNotApplicable" data-test="soap-monitoring-plan" class="w-full rounded-xl border border-slate-200 px-3 py-2 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input v-model="monitoringNotApplicable" type="checkbox" data-test="soap-monitoring-not-applicable" />
            Not applicable
        </label>
        <label v-if="monitoringNotApplicable" class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Reason monitoring is not applicable</span>
            <input v-model="payload.monitoring_plan_not_applicable_reason" type="text" maxlength="1000" data-test="soap-monitoring-not-applicable-reason" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <section v-if="conflict" data-test="soap-conflict" class="rounded-2xl border border-rose-200 bg-white p-4 dark:border-rose-900 dark:bg-slate-900">
            <p class="text-sm font-bold text-rose-700">The server changed after this device began editing.</p>
            <div class="mt-3 grid gap-2">
                <button type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-bold" @click="resolveWithServer">Use server version</button>
                <button type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-bold" @click="keepDeviceCopy">Keep local draft as a copy</button>
                <button v-if="!confirmingReplace" type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-left text-sm font-bold text-rose-700" @click="confirmingReplace = true">Replace server version</button>
                <button v-else type="button" class="rounded-xl bg-rose-700 px-3 py-2 text-sm font-bold text-white" @click="replaceServer">Yes, replace it</button>
            </div>
        </section>

        <button v-if="state === 'failed'" type="button" class="rounded-xl bg-[#0b2942] px-4 py-2.5 text-sm font-bold text-white" @click="retry">Retry</button>
    </section>
</template>
```

- [ ] **Step 8: Wire into `CaseEditor.vue`**

Add the import: `import SoapSection from './case-editor/SoapSection.vue';`

Extend the props type with `soap: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;`

Change the `soap` entry in `sections` to `available: true` and update `goTo()` — remove the special-case redirect (it's no longer needed once `soap` is a real in-editor section, and the page it redirected to no longer exists):

```typescript
function goTo(index: number) {
    if (index < 0 || index >= sections.length) return;
    if (!sections[index].available) return;
    activeIndex.value = index;
}
```

Add a default payload for when no SOAP note exists yet:

```typescript
const emptySoap = {
    subjective: null, objective: null, assessment: null, plan: null,
    monitoring_plan: null, monitoring_plan_not_applicable_reason: null,
    drug_related_problem_status: null, drug_related_problem_categories: null,
    lock_version: 0, updated_at: new Date().toISOString(),
};
```

Add the branch after `MedicationChartSection`:

```vue
            <SoapSection v-else-if="activeSection.id === 'soap'" :case-id="clinicalCase.id" :user-id="userId" :initial="(soap ?? emptySoap) as any" />
```

- [ ] **Step 9: Update `CaseShow.vue`**

Remove the "Edit SOAP" `<Button>` entirely (both instances — the header action button and the "Start editing" link under "No SOAP note yet."), since "Continue documentation" (added in Slice 2A Task 6) now covers the same destination through the unified editor, and the old destination no longer exists. Replace the "Start editing" `<Button variant="link">` with the same `router.get(`/student/cases/${clinicalCase.id}/edit`)` call `Continue documentation` already uses.

- [ ] **Step 10: Delete the standalone SOAP page**

Delete `resources/js/pages/student/SoapEditor.vue`.

- [ ] **Step 11: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors. Search the codebase for `SoapEditor` before this step to confirm nothing besides the now-removed route references it.

- [ ] **Step 12: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 261 previous passed, minus the one removed `SoapNoteSyncTest` test, plus the two new `CaseEditorPageTest` methods — 262 passed / 2 skipped (264 total).

- [ ] **Step 13: Commit**

```bash
git add resources/js/pages/student/case-editor/SoapSection.vue resources/js/pages/student/CaseEditor.vue resources/js/pages/student/CaseShow.vue app/Http/Controllers/Student/CaseEditorController.php app/Http/Controllers/Student/SoapController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseEditorPageTest.php tests/Feature/SoapNoteSyncTest.php
git rm resources/js/pages/student/SoapEditor.vue
git commit -m "feat: wire SOAP into the unified case editor and atomically retire the standalone SOAP page"
```

---

## Task 7: Frontend — Conditional Clinical Activities section with the full accepted field set

**Revision:** The original draft's `ConditionalClinicalActivitiesSection.vue`/`ActivityRow.vue` rendered only a handful of each activity's fields even though the backend (Tasks 3–5) validates the full catalogue set. This task renders every field, uses `useRepeatableRowCreate` (2B) for offline-capable intervention/monitoring row creation, and gives every row the same three-way conflict panel as every other syncable row in this series.

**Files:**
- Create: `resources/js/pages/student/case-editor/ActivityRow.vue`
- Create: `resources/js/pages/student/case-editor/ConditionalClinicalActivitiesSection.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue`
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass activity props)
- Test: `tests/Feature/CaseEditorPageTest.php` (extend)

- [ ] **Step 1: Write the failing test (extend `CaseEditorPageTest`)**

Append to the class:

```php
    public function test_editor_page_includes_clinical_activities_split_by_shape(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        \App\Models\CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => \App\Enums\ClinicalActivityType::Intervention->value, 'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->has('interventions', 1)
            ->has('monitoringFollowUps', 0)
            ->where('adr', null)
            ->where('counselling', null));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: FAIL — props missing.

- [ ] **Step 3: Extend `CaseEditorController`**

Add the imports `use App\Enums\ClinicalActivityType;` and `use App\Models\CaseClinicalActivity;` (if not already present). Add `$case->load('clinicalActivities');` near the top of `show()` so the payload helpers below don't issue four separate queries. Add these props to `Inertia::render(...)`:

```php
            'adr' => $this->singletonActivityPayload($case, ClinicalActivityType::Adr),
            'counselling' => $this->singletonActivityPayload($case, ClinicalActivityType::Counselling),
            'interventions' => $this->repeatableActivityPayload($case, ClinicalActivityType::Intervention),
            'monitoringFollowUps' => $this->repeatableActivityPayload($case, ClinicalActivityType::Monitoring),
```

Add these three private methods to the controller:

```php
    /** @return array<string, mixed>|null */
    private function singletonActivityPayload(ClinicalCase $case, ClinicalActivityType $type): ?array
    {
        $activity = $case->clinicalActivities->firstWhere('activity_type', $type);

        return $activity === null ? null : $this->activityPayload($activity);
    }

    /** @return array<int, array<string, mixed>> */
    private function repeatableActivityPayload(ClinicalCase $case, ClinicalActivityType $type): array
    {
        return $case->clinicalActivities
            ->where('activity_type', $type)
            ->map(fn (CaseClinicalActivity $activity): array => $this->activityPayload($activity))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function activityPayload(CaseClinicalActivity $activity): array
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Write `ActivityRow.vue` — full intervention/monitoring field sets, offline-aware, conflict panel**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import { LOCAL_ROW_PREFIX } from '@/composables/useRepeatableRowCreate';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type ActivityPayload = SyncedSection & {
    id: string;
    activity_type: 'intervention' | 'monitoring';
    status: string | null;
    details: Record<string, unknown> | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: ActivityPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, edit, online, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace } =
    useSectionSync<ActivityPayload>({
        userId: props.userId,
        resourceId: props.initial.id,
        sectionKey: 'clinical_activities',
        endpoint: `/student/cases/${props.caseId}/clinical-activities/${props.initial.id}`,
        initialPayload: props.initial,
    });

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

function detail(key: string): string {
    return (payload.value.details?.[key] as string) ?? '';
}
function updateDetail(key: string, value: unknown) {
    payload.value.details = { ...(payload.value.details ?? {}), [key]: value };
    edit();
}

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/clinical-activities/${props.initial.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
    });
    if (response.ok) emit('removed', props.initial.id);
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`activity-row-${initial.id}`">
        <div class="mb-2 flex items-center justify-end gap-2">
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove activity" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>

        <template v-if="initial.activity_type === 'intervention'">
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Problem</span>
                <textarea :value="detail('problem')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('problem', ($event.target as HTMLTextAreaElement).value)" />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Recommendation</span>
                <textarea :value="detail('recommendation')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('recommendation', ($event.target as HTMLTextAreaElement).value)" />
                <DeidentificationNotice :text="detail('recommendation')" />
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Recipient</span>
                    <input :value="detail('recipient')" type="text" maxlength="120" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('recipient', ($event.target as HTMLInputElement).value)" />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Communication method</span>
                    <input :value="detail('communication_method')" type="text" maxlength="60" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('communication_method', ($event.target as HTMLInputElement).value)" />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Case date</span>
                    <input :value="detail('case_date')" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('case_date', ($event.target as HTMLInputElement).value)" />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Outcome</span>
                    <select :value="detail('outcome')" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="updateDetail('outcome', ($event.target as HTMLSelectElement).value)">
                        <option value="">Outcome…</option>
                        <option value="accepted">Accepted</option>
                        <option value="partially_accepted">Partially accepted</option>
                        <option value="not_accepted">Not accepted</option>
                        <option value="pending">Pending</option>
                        <option value="not_communicated">Not communicated</option>
                    </select>
                </label>
            </div>
            <label class="mt-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Follow-up</span>
                <textarea :value="detail('follow_up')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('follow_up', ($event.target as HTMLTextAreaElement).value)" />
            </label>
        </template>

        <template v-else>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Parameter</span>
                <input :value="detail('parameter')" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('parameter', ($event.target as HTMLInputElement).value)" />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Case date</span>
                <input :value="detail('observed_on')" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('observed_on', ($event.target as HTMLInputElement).value)" />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Result</span>
                <textarea :value="detail('result')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('result', ($event.target as HTMLTextAreaElement).value)" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Notes</span>
                <textarea :value="detail('notes')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('notes', ($event.target as HTMLTextAreaElement).value)" />
                <DeidentificationNotice :text="detail('notes')" />
            </label>
        </template>

        <section v-if="conflict" data-test="activity-conflict" class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900">
            <p class="text-xs font-bold text-rose-700">The server changed after this device began editing.</p>
            <div class="mt-2 grid gap-1.5">
                <button type="button" class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold" @click="resolveWithServer">Use server version</button>
                <button type="button" class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold" @click="keepDeviceCopy">Keep local draft as a copy</button>
                <button v-if="!confirmingReplace" type="button" class="rounded-lg border border-rose-200 px-2 py-1.5 text-left text-xs font-bold text-rose-700" @click="confirmingReplace = true">Replace server version</button>
                <button v-else type="button" class="rounded-lg bg-rose-700 px-2 py-1.5 text-xs font-bold text-white" @click="replaceServer">Yes, replace it</button>
            </div>
        </section>
        <button v-if="state === 'failed'" type="button" class="mt-2 rounded-lg bg-[#0b2942] px-3 py-1.5 text-xs font-bold text-white" @click="retry">Retry</button>
    </div>
</template>
```

`LOCAL_ROW_PREFIX` is imported for symmetry with the parent section's pending-row check (Step 6) even though this file only reads `initial.id`, not the constant directly — this keeps both files agreeing on the exact same prefix string rather than each hand-rolling `'local:'`.

- [ ] **Step 6: Write `ConditionalClinicalActivitiesSection.vue` — full ADR/Counselling field sets, offline-capable Intervention/Monitoring creation**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import { LOCAL_ROW_PREFIX, useRepeatableRowCreate } from '@/composables/useRepeatableRowCreate';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';
import ActivityRow from './ActivityRow.vue';

type RowPayload = SyncedSection & { id: string; activity_type: 'intervention' | 'monitoring'; status: string | null; details: Record<string, unknown> | null };
type AdrPayload = SyncedSection & { status: string | null; details: Record<string, unknown> | null };
type CounsellingPayload = SyncedSection & { status: string | null; details: Record<string, unknown> | null };

const emptyAdr: AdrPayload = { status: null, details: null, lock_version: 0, updated_at: new Date().toISOString() };
const emptyCounselling: CounsellingPayload = { status: null, details: null, lock_version: 0, updated_at: new Date().toISOString() };

const props = defineProps<{
    caseId: string;
    userId: number;
    initialAdr: AdrPayload | null;
    initialCounselling: CounsellingPayload | null;
    initialInterventions: RowPayload[];
    initialMonitoringFollowUps: RowPayload[];
}>();

const interventions = ref([...props.initialInterventions]);
const monitoringFollowUps = ref([...props.initialMonitoringFollowUps]);

const adrSync = useSectionSync<AdrPayload>({
    userId: props.userId, resourceId: props.caseId, sectionKey: 'clinical_activity_adr',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/adr`,
    initialPayload: props.initialAdr ?? emptyAdr,
});
const counsellingSync = useSectionSync<CounsellingPayload>({
    userId: props.userId, resourceId: props.caseId, sectionKey: 'clinical_activity_counselling',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/counselling`,
    initialPayload: props.initialCounselling ?? emptyCounselling,
});

const interventionCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId, sectionKey: 'clinical_activities_intervention',
    endpoint: `/student/cases/${props.caseId}/clinical-activities`, responseKey: 'activity',
    emptyPayload: () => ({ activity_type: 'intervention', status: null, details: null }),
});
const monitoringCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId, sectionKey: 'clinical_activities_monitoring',
    endpoint: `/student/cases/${props.caseId}/clinical-activities`, responseKey: 'activity',
    emptyPayload: () => ({ activity_type: 'monitoring', status: null, details: null }),
});

async function addIntervention() {
    interventions.value = [await interventionCreate.queueCreate(), ...interventions.value];
}
async function addMonitoring() {
    monitoringFollowUps.value = [await monitoringCreate.queueCreate(), ...monitoringFollowUps.value];
}
function removeIntervention(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX)) void interventionCreate.cancelQueuedCreate(id);
    interventions.value = interventions.value.filter((r) => r.id !== id);
}
function removeMonitoring(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX)) void monitoringCreate.cancelQueuedCreate(id);
    monitoringFollowUps.value = monitoringFollowUps.value.filter((r) => r.id !== id);
}

function handleReconnect() {
    void interventionCreate.replayPending((localId, row) => {
        interventions.value = interventions.value.map((r) => (r.id === localId ? row : r));
    });
    void monitoringCreate.replayPending((localId, row) => {
        monitoringFollowUps.value = monitoringFollowUps.value.map((r) => (r.id === localId ? row : r));
    });
}
onMounted(() => {
    handleReconnect();
    window.addEventListener('online', handleReconnect);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', handleReconnect);
});

function updateAdrDetail(key: string, value: unknown) {
    adrSync.payload.value.details = { ...(adrSync.payload.value.details ?? {}), [key]: value };
    adrSync.edit();
}
function updateCounsellingDetail(key: string, value: unknown) {
    counsellingSync.payload.value.details = { ...(counsellingSync.payload.value.details ?? {}), [key]: value };
    counsellingSync.edit();
}
function adrDetail(key: string): string {
    return (adrSync.payload.value.details?.[key] as string) ?? '';
}
function counsellingDetail(key: string): string {
    return (counsellingSync.payload.value.details?.[key] as string) ?? '';
}
</script>

<template>
    <section aria-labelledby="clinical-activities-heading" class="space-y-8">
        <h2 id="clinical-activities-heading" class="font-display text-lg text-[#0b2942] dark:text-white">Conditional Clinical Activities</h2>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Pharmacist intervention</h3>
            <div class="space-y-3">
                <template v-for="row in interventions" :key="row.id">
                    <div v-if="row.id.startsWith(LOCAL_ROW_PREFIX)" class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600">Waiting to sync…</div>
                    <ActivityRow v-else :case-id="caseId" :user-id="userId" :initial="row" @removed="removeIntervention" />
                </template>
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addIntervention"><Plus class="size-4" /> Add intervention</button>
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Suspected ADR</h3>
            <fieldset class="mb-3">
                <legend class="sr-only">Suspected ADR</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label v-for="option in ['yes', 'no', 'unable_to_assess']" :key="option" class="flex items-center gap-1.5">
                        <input v-model="adrSync.payload.value.status" type="radio" :value="option" :data-test="`adr-status-${option}`" @change="adrSync.edit" />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div v-if="adrSync.payload.value.status === 'yes'" class="space-y-2">
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Event / reaction</span>
                    <input :value="adrDetail('event')" type="text" maxlength="1000" data-test="adr-event" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('event', ($event.target as HTMLInputElement).value)" />
                    <DeidentificationNotice :text="adrDetail('event')" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Suspected medicine</span>
                    <input :value="adrDetail('suspected_medicine')" type="text" maxlength="255" data-test="adr-suspected-medicine" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('suspected_medicine', ($event.target as HTMLInputElement).value)" />
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Onset</span>
                        <input :value="adrDetail('onset_reference')" type="text" maxlength="30" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('onset_reference', ($event.target as HTMLInputElement).value)" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Stop</span>
                        <input :value="adrDetail('stop_reference')" type="text" maxlength="30" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('stop_reference', ($event.target as HTMLInputElement).value)" />
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Dose/route/frequency</span>
                    <input :value="adrDetail('dose_route_frequency')" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('dose_route_frequency', ($event.target as HTMLInputElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Concomitant medicines</span>
                    <textarea :value="adrDetail('concomitant_medicines')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('concomitant_medicines', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Relevant tests</span>
                    <textarea :value="adrDetail('relevant_tests')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('relevant_tests', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Action taken</span>
                    <textarea :value="adrDetail('action_taken')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('action_taken', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Seriousness</span>
                        <select :value="adrDetail('seriousness')" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="updateAdrDetail('seriousness', ($event.target as HTMLSelectElement).value)">
                            <option value="">Select…</option>
                            <option value="serious">Serious</option>
                            <option value="non_serious">Non-serious</option>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Outcome</span>
                        <input :value="adrDetail('outcome')" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('outcome', ($event.target as HTMLInputElement).value)" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Dechallenge</span>
                        <input :value="adrDetail('dechallenge')" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('dechallenge', ($event.target as HTMLInputElement).value)" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500">Rechallenge</span>
                        <input :value="adrDetail('rechallenge')" type="text" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('rechallenge', ($event.target as HTMLInputElement).value)" />
                    </label>
                </div>
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Patient counselling</h3>
            <fieldset class="mb-3">
                <legend class="sr-only">Patient counselling</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label v-for="option in ['performed', 'planned', 'not_indicated', 'unable_to_perform']" :key="option" class="flex items-center gap-1.5">
                        <input v-model="counsellingSync.payload.value.status" type="radio" :value="option" :data-test="`counselling-status-${option}`" @change="counsellingSync.edit" />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div v-if="counsellingSync.payload.value.status === 'performed' || counsellingSync.payload.value.status === 'planned'" class="space-y-2">
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Topics covered</span>
                    <textarea :value="counsellingDetail('topics')" rows="3" maxlength="2000" data-test="counselling-topics" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('topics', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Medicine purpose</span>
                    <textarea :value="counsellingDetail('medicine_purpose')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('medicine_purpose', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Administration</span>
                    <textarea :value="counsellingDetail('administration')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('administration', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Adherence</span>
                    <textarea :value="counsellingDetail('adherence')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('adherence', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Precautions</span>
                    <textarea :value="counsellingDetail('precautions')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('precautions', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Important adverse effects</span>
                    <textarea :value="counsellingDetail('adverse_effects')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('adverse_effects', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Storage</span>
                    <input :value="counsellingDetail('storage')" type="text" maxlength="500" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('storage', ($event.target as HTMLInputElement).value)" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500">Lifestyle / follow-up</span>
                    <textarea :value="counsellingDetail('lifestyle_follow_up')" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('lifestyle_follow_up', ($event.target as HTMLTextAreaElement).value)" />
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        :checked="(counsellingSync.payload.value.details?.understanding_checked as boolean) ?? false"
                        type="checkbox"
                        data-test="counselling-understanding-checked"
                        @change="updateCounsellingDetail('understanding_checked', ($event.target as HTMLInputElement).checked)"
                    />
                    Understanding checked
                </label>
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Monitoring follow-up</h3>
            <div class="space-y-3">
                <template v-for="row in monitoringFollowUps" :key="row.id">
                    <div v-if="row.id.startsWith(LOCAL_ROW_PREFIX)" class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600">Waiting to sync…</div>
                    <ActivityRow v-else :case-id="caseId" :user-id="userId" :initial="row" @removed="removeMonitoring" />
                </template>
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addMonitoring"><Plus class="size-4" /> Add follow-up result</button>
            </div>
        </div>
    </section>
</template>
```

- [ ] **Step 7: Wire into `CaseEditor.vue`**

Add the import: `import ConditionalClinicalActivitiesSection from './case-editor/ConditionalClinicalActivitiesSection.vue';`

Extend the props type:

```typescript
    adr: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;
    counselling: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;
    interventions: (Record<string, unknown> & { id: string })[];
    monitoringFollowUps: (Record<string, unknown> & { id: string })[];
```

Change the `clinical_activities` entry in `sections` to `available: true`.

Add the final branch after `SoapSection`:

```vue
            <ConditionalClinicalActivitiesSection
                v-else-if="activeSection.id === 'clinical_activities'"
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial-adr="adr as any"
                :initial-counselling="counselling as any"
                :initial-interventions="interventions as any"
                :initial-monitoring-follow-ups="monitoringFollowUps as any"
            />
```

- [ ] **Step 8: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 9: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 262 previous passed + 1 new (the extended `CaseEditorPageTest` method) — 263 passed / 2 skipped (265 total).

- [ ] **Step 10: Commit**

```bash
git add resources/js/pages/student/case-editor/ActivityRow.vue resources/js/pages/student/case-editor/ConditionalClinicalActivitiesSection.vue resources/js/pages/student/CaseEditor.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git commit -m "feat: complete the six-section mobile case editor with the full Conditional Clinical Activities field set and offline-capable row creation"
```

---

## Task 8: Authorization and institution-isolation tests

**Files:**
- Create: `tests/Feature/ClinicalActivityAuthorizationTest.php`

**Interfaces:**
- Consumes: `SoapController` (Task 1/6), `CaseClinicalActivityController` (Tasks 3–5). Closes cross-institution/post-submission/faculty gaps for the final set of endpoints, mirroring Slice 2A Task 7 and Slice 2B Task 6.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_gets_404_on_every_activity_and_soap_endpoint(): void
    {
        [, $student, $case] = $this->makeAssignedCase();
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Intervention->value, 'recorded_by' => $student->id,
        ]);
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no'])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated'])->assertNotFound();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", ['client_operation_id' => (string) Str::uuid(), 'activity_type' => 'intervention'])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}")->assertNotFound();
    }

    public function test_the_owning_student_cannot_sync_soap_or_activities_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/adr", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'no'])->assertForbidden();
        $this->postJson("/student/cases/{$case->id}/clinical-activities", ['client_operation_id' => (string) Str::uuid(), 'activity_type' => 'intervention'])->assertForbidden();
    }

    public function test_assigned_faculty_cannot_sync_soap_or_any_clinical_activity(): void
    {
        [, , $case, $faculty] = $this->makeAssignedCase();
        $this->actingAs($faculty);

        $this->putJson("/student/cases/{$case->id}/soap", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'not_indicated'])->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase, User} */
    private function makeAssignedCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $faculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'rotation_id' => $rotation->id, 'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id, 'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id, 'case_number' => 1, 'status' => $status,
        ]);

        return [$institution, $student, $case, $faculty];
    }
}
```

- [ ] **Step 2: Run the tests**

Run (PowerShell): `php artisan test --filter=ClinicalActivityAuthorizationTest`
Expected: PASS (3 tests).

- [ ] **Step 3: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 263 previous passed + 3 new — 266 passed / 2 skipped (268 total).

- [ ] **Step 4: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/ClinicalActivityAuthorizationTest.php
git commit -m "test: cover cross-institution, post-submission and faculty-read-only access for SOAP and clinical activities"
```

---

## Task 9: Full end-to-end device verification across all six sections

**Revision:** Adds an offline row-creation pass for the two repeatable activity types and corrects the offline-refresh script the same way Slices 2A/2B were corrected.

**Files:** None (verification only).

**Interfaces:** None.

Same rationale and tooling as Slices 2A Task 8 and 2B Task 7. This is the comprehensive pass across the whole editor now that all six sections exist together.

- [ ] **Step 1: Start the app**

Run (PowerShell, background): `php artisan serve` and `npm run dev`.

- [ ] **Step 2: Phone viewport (390×844) — full create-to-portfolio-adjacent walkthrough**

As a student with an active rotation assignment:
1. Create a new case. Confirm it lands on the case show page, and "Continue documentation" opens the six-section editor.
2. Walk through all six sections in order via Next: Case Profile → History & Diagnosis → Vitals & Investigations → Medication Chart → SOAP → Conditional Clinical Activities. Confirm the nav pill for each becomes active (`aria-current="step"`) as you arrive, and Previous/Next disable correctly at both ends.
3. In SOAP, set "Drug-related problem status" to "Identified" and confirm the category chips appear; toggle a few and confirm they persist after a page refresh. Check "Not applicable" under Monitoring plan, confirm the plan textarea disables and a reason field appears.
4. In Conditional Clinical Activities: answer Suspected ADR "Yes" and confirm every field (event, onset/stop, suspected medicine, dose/route/frequency, concomitant medicines, relevant tests, action taken, seriousness, outcome, dechallenge, rechallenge) is visible and editable, and that a 10-digit number typed into "Event / reaction" shows the de-identification warning. Answer Patient counselling "Performed" and confirm every field (topics, medicine purpose, administration, adherence, precautions, adverse effects, storage, lifestyle/follow-up, understanding checked) is visible. Add one Pharmacist intervention row and confirm problem/recommendation/recipient/communication method/case date/outcome/follow-up are all present; add one Monitoring follow-up row and confirm parameter/case date/result/notes are present.
5. Toggle the device offline and add one more intervention row; confirm the "Waiting to sync…" placeholder appears immediately and becomes a real row once reconnected, matching the Vitals/Investigations/Medications behavior verified in Slice 2B.
6. Confirm the old `/student/cases/{id}/soap` URL now returns a 404 (route removed in Task 6) and that no visible link in the app points there anymore (check `CaseShow.vue`).

- [ ] **Step 3: Offline recovery and conflict — SOAP and one singleton activity**

Using DevTools offline toggle, edit a SOAP field offline and confirm "Saved on this device", then reconnect and confirm it syncs (do not test a full page hard-refresh while offline — per the correction applied in Slices 2A/2B, that hits the browser's own network-error page, not the app). Simulate a two-tab conflict against the ADR singleton's `status` field and confirm the same three-way conflict panel appears as every other section. This proves the sync engine generalizes correctly to its last two consumers, singleton and revisioned-note alike.

- [ ] **Step 4: Tablet (820×1180) and Desktop (1280×900, Chrome/Edge)**

Repeat Step 2's walkthrough at both viewports. Pay particular attention to the Conditional Clinical Activities section's four subsections stacking sensibly rather than feeling cramped at phone width or oddly sparse at desktop width — if visual polish issues are found here, record them as a known-limitations note for the pull request rather than scope-creeping a fix into this verification task.

- [ ] **Step 5: Full-suite regression and logout check**

Run (PowerShell): `php artisan test` one final time and confirm 266 passed / 2 skipped (268 total) (or the actual cumulative count if any earlier task's manual verification surfaced a fix). Log out from the fully-populated case editor and confirm (DevTools Application → IndexedDB) that `pharmalab-section-outbox` is empty afterward — this re-confirms Slice 2A Task 3's logout hook still works once every section in this series has written to the outbox at least once.

- [ ] **Step 6: Record the result and update `PROJECT_STATE.md`**

This is the point where Slice 2 as a whole (2A + 2B + 2C) is complete. Once this slice's PR is reviewed and merged, update `PROJECT_STATE.md`'s "Active work" section for `DIRECT-DOCUMENTATION-IMPL-01`, following the exact structure Slice 1's acceptance used (`ba58646` merge record): record the merge commit, the final test count from Task 8, the verification evidence from this task, any deferred minors found along the way (e.g. visual polish items from Step 4), and update "Exact next action" to point at Slice 3 (submission and completeness). Do not perform this `PROJECT_STATE.md` update as part of any earlier task — only once the whole gate is genuinely done, per the file's own maintenance rule (§9).

---

## Task 10: Retire the SYNC-SPIKE-01 `CaseDraftNote` experiment

**Revision (second review round):** The accepted SYNC-SPIKE-01 spike (`CaseDraftNoteController`, `/student/sync-spike`, `CaseDraftNote.vue`, `caseDraftStore.ts`) proved the offline-sync protocol this entire Slice 2 series generalized from it. It was deliberately left untouched through Tasks 1–9 of 2A/2B/2C so the accepted spike kept working while the real engine was built alongside it (Slice 2A's Architecture section says so explicitly). Now that the six-section editor covers everything the spike demonstrated — and more (structured multi-field sections, not one free-text note) — leaving the spike's route, page and controller live indefinitely is unfinished cleanup, not caution. This task retires it. It does **not** touch the `case_draft_notes` table or any historical `sync_operations` row that references it — Slice 2A Task 2's migration already made `sync_operations.case_draft_note_id` nullable and permanently blocks a rollback once generalized rows exist (see that task's `down()` guard); nothing about *this* task changes any stored data, only the UI/route surface that let a student reach the experiment.

**Files:**
- Modify: `routes/web.php` (remove the three `student.sync-spike*` routes and the `CaseDraftNoteController` import)
- Delete: `app/Http/Controllers/CaseDraftNoteController.php`
- Delete: `app/Http/Requests/SyncCaseDraftNoteRequest.php`
- Delete: `app/Policies/CaseDraftNotePolicy.php` (and its registration, if `AuthServiceProvider` or model-discovery config maps it explicitly — check `app/Providers/AuthServiceProvider.php` for a `CaseDraftNote::class => CaseDraftNotePolicy::class` entry)
- Delete: `resources/js/pages/student/CaseDraftNote.vue`
- Delete: `resources/js/lib/caseDraftStore.ts`
- Modify: `resources/js/pages/student/Dashboard.vue` (remove the "sync-spike" nav card/link)
- Modify: `resources/js/components/UserMenuContent.vue` (remove the now-dead `clearCaseDraftStorage()` import and call — `clearSectionOutbox()`, added in Slice 2A Task 3, remains and is now the only store logout clears)
- Delete: `tests/Feature/Sync/CaseDraftNoteSyncTest.php` (7 tests covering the spike directly — removed, not migrated, since every guarantee it tested is now covered by `SectionSyncServiceTest` and the per-section sync tests throughout 2A/2B/2C)
- Delete: `tests/Browser/sync_spike.py` (if present — a Playwright/Python script outside the PHPUnit suite; confirm nothing in CI configuration references it before deleting, and update that configuration in the same commit if it does)
- Test: `tests/Feature/CaseDraftNoteRetirementTest.php`

**Interfaces:** None produced — this task only removes surface area. No other task in this plan series depends on anything created here.

- [ ] **Step 1: Confirm feature parity before removing anything**

Cross-reference the spike's demonstrated capabilities against the shipped editor, and do not proceed until every row below is checked:

| SYNC-SPIKE-01 capability | Where it now lives |
| --- | --- |
| IndexedDB-backed offline draft | `outboxStore.ts` (Slice 2A Task 3), used by every section |
| Idempotent `client_operation_id` autosave | `SectionSyncService::sync()`/`create()` (Slice 2A/2B) |
| Optimistic locking via `lock_version` | `Syncable`/`SyncsWithLockVersion` (Slice 2A Task 2), per-section lock columns |
| Conflict resolution (use server / keep device copy / replace server) | `useSectionSync.ts`'s three-way panel, now on every section and every repeatable row |
| A single free-text note a student can edit | Superseded — the six-section structured editor is strictly more capable, not a like-for-like swap of one text field |

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDraftNoteRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sync_spike_routes_no_longer_exist(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $this->get('/student/sync-spike')->assertNotFound();
    }

    public function test_the_dashboard_still_renders_after_the_sync_spike_link_is_removed(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        // A broken render here is the regression this catches: removing the
        // sync-spike card/import from Dashboard.vue must not leave the page
        // referencing a now-deleted component or route helper. The route's
        // absence itself is proven by the first test.
        $this->get('/student/dashboard')->assertOk();
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseDraftNoteRetirementTest`
Expected: FAIL — `/student/sync-spike` still resolves.

- [ ] **Step 4: Remove the routes**

In `routes/web.php`, remove these three lines and the `use App\Http\Controllers\CaseDraftNoteController;` import:

```php
        Route::get('student/sync-spike', [CaseDraftNoteController::class, 'index'])->name('student.sync-spike');
        Route::get('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'show'])->name('student.sync-spike.show');
        Route::put('student/sync-spike/{caseDraftNote}', [CaseDraftNoteController::class, 'sync'])->name('student.sync-spike.sync');
```

- [ ] **Step 5: Delete the backend files**

Delete `app/Http/Controllers/CaseDraftNoteController.php`, `app/Http/Requests/SyncCaseDraftNoteRequest.php`, and `app/Policies/CaseDraftNotePolicy.php`. Search `app/Providers/AuthServiceProvider.php` for a `CaseDraftNote::class => CaseDraftNotePolicy::class` entry in its `$policies` array and remove it if present.

- [ ] **Step 6: Delete the frontend files and remove the dashboard link**

Delete `resources/js/pages/student/CaseDraftNote.vue` and `resources/js/lib/caseDraftStore.ts`.

In `resources/js/pages/student/Dashboard.vue`, remove the `<Link href="/student/sync-spike" ...>` card entirely (and its surrounding wrapper if the card was the only content of that wrapper).

- [ ] **Step 7: Remove the dead import from the logout handler**

In `resources/js/components/UserMenuContent.vue`, remove the `import { clearCaseDraftStorage } from '@/lib/caseDraftStore';` import and the `await clearCaseDraftStorage();` line from `handleLogout()`, leaving:

```typescript
const handleLogout = async () => {
    await clearSectionOutbox();
    router.flushAll();
    router.post(logout.url());
};
```

Note: this does not retroactively clear any `caseDraftStore` data already sitting in a browser's IndexedDB from before this task shipped — that database is now permanently orphaned (nothing reads or writes it again) rather than actively wiped. This is an acceptable, explicitly-stated scope boundary for a UI/route retirement, not a silent gap: the data was already device-local, non-syncing, and inaccessible through the app the moment `CaseDraftNote.vue`'s route stops resolving.

- [ ] **Step 8: Delete the spike's own tests**

Delete `tests/Feature/Sync/CaseDraftNoteSyncTest.php`. Delete `tests/Browser/sync_spike.py` if it exists and nothing else references it.

- [ ] **Step 9: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`
Removing routes changes generated files too — confirm `resources/js/actions/App/Http/Controllers/CaseDraftNoteController.ts` and `resources/js/routes/student/sync-spike/index.ts` are gone from `git status`, and commit their removal in this same commit.

- [ ] **Step 10: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseDraftNoteRetirementTest`
Expected: PASS (2 tests).

- [ ] **Step 11: Type-check and run the full suite**

Run (PowerShell): `npm run types:check`
Expected: no errors — confirms nothing still imports the deleted `.vue`/`.ts` files.

Run (PowerShell): `php artisan test`
Expected: 266 previous passed, minus 7 removed (`CaseDraftNoteSyncTest`), plus 2 new — 261 passed / 2 skipped (263 total).

- [ ] **Step 12: Manual check — confirm nothing links to the old route**

Search the rendered app (or `grep -r "sync-spike" resources/js`) and confirm zero remaining references outside of generated/build artifacts that will be regenerated away. Load the dashboard as a seeded student and visually confirm the sync-spike card is gone.

- [ ] **Step 13: Commit**

```bash
git add routes/web.php resources/js/actions resources/js/routes resources/js/pages/student/Dashboard.vue resources/js/components/UserMenuContent.vue tests/Feature/CaseDraftNoteRetirementTest.php
git rm app/Http/Controllers/CaseDraftNoteController.php app/Http/Requests/SyncCaseDraftNoteRequest.php app/Policies/CaseDraftNotePolicy.php resources/js/pages/student/CaseDraftNote.vue resources/js/lib/caseDraftStore.ts tests/Feature/Sync/CaseDraftNoteSyncTest.php
git commit -m "chore: retire the SYNC-SPIKE-01 CaseDraftNote experiment now that the six-section editor covers it"
```

---

## Self-Review Notes

- **Spec coverage:** Requirement 2 (mobile section editor) — completed here; all six sections now live in one `CaseEditor.vue`, and the transition never leaves the app without a working SOAP save path (Task 1 additive, Task 6 atomic retirement). Requirement 3 (repeatable rows) — Intervention and Monitoring follow-up added (Task 5) with the same offline-capable creation Slice 2B built, completing the full set. Requirement 5 (sync engine) — proven against its two remaining consumer shapes (a revisioned singleton with lazy creation for SOAP, and a concurrency-guarded lazy-singleton pattern for ADR/Counselling) without any new locking mechanism, and its idempotency/class-check hardening (Slice 2A/2B) is exercised by every new section key added here. Requirement 6 (conditional allergy and ADR fields) — ADR's Yes/No/Unable-to-assess gate with required-only-on-Yes details and the full accepted field set (Task 3, Task 7); counselling's parallel structure with its full field set (Task 4, Task 7). Requirement 7 (de-identification warnings) — extended to SOAP Subjective/Objective/Assessment/Plan, the ADR event field, intervention recommendation, and monitoring notes. Requirement 8 (authorization tests) — Task 8. Requirement 9 (device verification) — Task 9, covering the full six-section flow end to end including offline row creation, closing out Slice 2.
- **Placeholder scan:** No task defers real logic. The visual-polish deferrals in Task 9 Step 4 are explicitly named as recorded limitations, not silently skipped work. Task 3's concurrency test explicitly documents why true parallel-request racing isn't reproducible in single-process PHPUnit and names the two tests that together stand in for it, rather than silently omitting the coverage.
- **Type consistency:** `CaseClinicalActivityController::payload()`/`activityPayload()` return the same field set (`id`, `activity_type`, `status`, `details`, `lock_version`, `updated_at`) whether the row came from `store()`, `sync()`, `syncAdr()`, or `syncCounselling()`, so `ActivityRow.vue` and `ConditionalClinicalActivitiesSection.vue` consume one consistent shape regardless of which endpoint produced it. `SoapController::payload()` matches `UpdateSoapNoteRequest`'s validated field set exactly, mirroring the discipline established for every other section since Slice 2A. Every `details` write in this plan goes through the same merge-or-clear helper (`mergeOrClearDetails()` for the two singletons, an inline equivalent for the generic repeatable-row `sync()`), so the partial-update behavior is identical across all four conditional-activity shapes. Every `details`-accepting request also pairs `RejectsUnknownFields` (top-level) with an `array:key1,key2` rule (nested), and the generic repeatable-row `destroy()` goes through `SectionSyncService::delete()` exactly like the 2B row endpoints, so no write path here bypasses unknown-field rejection or optimistic concurrency.
- **Review Focus coverage:** wrong-door singleton access, the SOAP Fillable regression, singleton double-creation (schema + app level), `details.*` partial-update/clear-on-exit behavior, unknown top-level and nested `details` keys, repeatable-row deletion bypassing optimistic concurrency, the old-page transition and its audit trail, and six-section navigation regressions each have a named test in the task that owns the relevant code, plus Task 9's manual pass for what only a real browser proves.
- **Test count:** running totals in this plan are computed cumulatively from the Slice 2A baseline of 131 tests (129 passed / 2 skipped). After all of 2A/2B/2C including Task 10's retirement (removes 7 `CaseDraftNoteSyncTest` tests, adds 2), the full `php artisan test` suite is expected at **261 passed / 2 skipped (263 total)**.
