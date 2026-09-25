# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2B (Vitals, Investigations, Medication Chart) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution, chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking. **Depends on Slice 2A being merged first** — this plan reuses `App\Contracts\Syncable`, `App\Models\Concerns\SyncsWithLockVersion`, `App\Services\SectionSyncService`, `App\Http\Requests\Concerns\HasSyncEnvelope`, `App\Http\Requests\Concerns\RejectsUnknownFields`, `resources/js/lib/outboxStore.ts`, `resources/js/composables/useSectionSync.ts`, `resources/js/components/DeidentificationNotice.vue` and `resources/js/pages/student/CaseEditor.vue`, all built or revised in [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md).
>
> **Revision note (post-review):** This plan was reviewed and returned with blocking corrections before any implementation began. This revision fixes: row creation requiring connectivity and rejecting blank "add row" taps (now offline-capable, idempotent, and tolerant of empty rows), row conflicts showing an icon with no resolution path (now the same three-way panel every other section has), missing BP-pair/SpO2/reference-range controls the field catalogue requires, availability toggles that could silently coexist with real rows, and several fields present in the backend but never rendered in the UI. See each task's **Revision:** note.

**Goal:** Add the three repeatable-row sections — Vitals & Investigations (one combined mobile-journey step per the field catalogue) and Medication Chart — with per-row optimistic-locking autosave, offline-capable bounded add/remove, per-row conflict resolution, and the explicit "unavailable"/"no current medicines" toggles whose schema Slice 2A already added to `clinical_cases`.

**Architecture:** Each of the three repeatable resources (`CaseVital`, `CaseInvestigation`, `CaseMedication`) gets its own `lock_version` column and becomes `Syncable` exactly like `ClinicalCase` and `CaseClinicalProfile` did in Slice 2A (each is its own row, so the trait's single-column `lockVersionColumn()` default is correct, unlike `ClinicalCase`) — so **editing an existing row's fields reuses `SectionSyncService::sync()` and `useSectionSync` completely unchanged**, one composable instance per rendered row. What's new in this slice is row **creation**: `SectionSyncService` gains a `create()` method that gives idempotent-by-`client_operation_id` row creation the same replay-safety as `sync()`, verified against an explicit `section_key => class` allow-list (extending the one Slice 2A's `sync()` already checks) so a replayed operation ID can never resolve to a record from a different section. **Revised scope decision:** row *creation* is offline-capable — tapping "Add" writes a local draft to the IndexedDB outbox immediately, renders an optimistic row, and replays the queued create once the device is online (a new `useRepeatableRowCreate` composable, sibling to `useSectionSync`, handles this). Row *deletion* of an already-synced row still requires connectivity (removing a not-yet-synced local draft is a pure local operation and needs no connectivity at all). This still respects the accepted SYNC-SPIKE-01 boundary ("Background Sync, automatic field-level merging... are not included") — there is no server-side merge logic here, only client-side queuing of a single idempotent create request, the same primitive `useSectionSync` already uses for edits. The three "unavailable"/"none documented" toggle fields live on `clinical_cases` (added in Slice 2A Task 1, including their own independent lock columns) and are synced by reusing `ClinicalCase`'s existing `Syncable` implementation with a `section_key` per toggle — no new locking mechanism needed there, and (per Slice 2A's fix) toggling one availability flag can no longer false-conflict with a concurrent Case Profile edit or with another toggle.

Creating a row and toggling its section "unavailable" must never silently coexist: creating a vital/investigation/medicine automatically flips that section's status to `recorded`/`documented` (clearing any stale reason) inside the same transaction as the row create, and attempting to mark a section `unavailable`/`none_documented` while rows still exist is rejected with a validation error naming the rows that must be removed first — this is autosave-layer consistency, not Slice 3's submission-completeness policy (which independently re-checks the same invariant at submission time; this slice's job is to stop the two states from silently diverging while the student is actively editing).

**Tech Stack:** Same as Slice 2A — Laravel 13, Eloquent, PHPUnit, SQLite (`:memory:`)/PostgreSQL; Inertia.js + Vue 3 + TypeScript, native `fetch` + IndexedDB.

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.3–4.5 and §5, Slice 2A plan (prerequisite infrastructure) at [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md).

## Global Constraints

All constraints from Slice 2A's Global Constraints apply unchanged (PowerShell for PHP/Composer/npm; no `Schema::table()->change()`; regenerate and commit Wayfinder files with every route change; `lock_version` never mass-fillable; sync-capable models implement `Syncable` via `SyncsWithLockVersion` and are only ever mutated through `SectionSyncService`; every sync/store request uses `HasSyncEnvelope` + `RejectsUnknownFields`; no JS unit-test runner exists — frontend correctness is verified via `npm run types:check` plus the manual browser-verification task). In addition:

- A row's `clinical_case_id` must always be cross-checked against the `{case}` route segment before any read/write, even though `CaseVitalPolicy`/`CaseInvestigationPolicy`/`CaseMedicationPolicy` already scope by institution and ownership — those policies check that the row's **own** case is owned by the requesting student, not that it matches the specific case in the URL. A student could otherwise pass `case=A` in the URL while editing a row that actually belongs to their own `case=B`, silently mis-attributing the edit. Every row controller action calls `abort_unless($row->clinical_case_id === $case->id, 404)` immediately after loading the row.
- Row creation (`POST .../vitals`, `.../investigations`, `.../medications`) is idempotent by `client_operation_id` via `SectionSyncService::create()` (new in this slice), exactly as row/field edits are idempotent by `client_operation_id` via `SectionSyncService::sync()`. `create()` derives the expected model class internally from `modelClassForSection($sectionKey)` (Slice 2A Task 2) and checks it against a replayed operation's stored `syncable_type` — it does **not** accept an "expected class" argument from its caller, which would let a controller bug route around the section allow-list.
- Every `Store*Request` for a repeatable row makes every clinical field `nullable`/optional at creation time — a bare "Add row" tap with no data must succeed and produce an empty/draft row that the student fills in afterward. Field-specific conditional requirements (e.g. `stop_reference` required when `status` is `stopped`/`completed`) still apply once those specific fields are present in *any* request, create or later edit — but creation itself is never blocked by missing content. Strict completeness (every mandatory field filled in) is Slice 3's submission-gate job, not this slice's.
- Row-level `Update*Request` classes follow the same `'sometimes'`-partial pattern established in Slice 2A — a PUT to an existing row that only changes one field (e.g. just `note`) must not require or overwrite the others.
- Row creation is offline-capable via `useRepeatableRowCreate` (Task 5); row deletion of an already-*synced* row is not — the "Remove row" control is disabled while `navigator.onLine` is `false` for a row with a real server id, with a visible reason. Removing a row that is still a local, not-yet-synced draft (its id is the client-generated `local:<uuid>` placeholder) is a pure local operation and is always available, online or not — it simply cancels the queued create.
- Deleting a row is subject to the same optimistic-concurrency check as editing one: every `DELETE .../vitals/{vital}` (and the investigations/medications/activity equivalents) requires `base_lock_version` in the JSON request body and goes through `SectionSyncService::delete()` (Task 2), which locks the row, compares versions inside the same transaction, and — on a match — deletes and records an audit event (`"{$sectionKey}.deleted"`) before returning; a stale version returns 409 with the current row state and deletes nothing. A `destroy()` action that calls `Model::delete()` directly, without going through `SectionSyncService::delete()`, is a regression of this fix.
- Every repeatable-row Vue component (`VitalRow.vue`, `InvestigationRow.vue`, `MedicationRow.vue`) gets the identical three-way conflict panel (`Use server version` / `Keep local draft as a copy` / `Replace server version`) that Slice 2A's `CaseProfileSection.vue` already has — a conflict on a row is not merely displayed with an icon, it is resolvable through the same three options every other syncable section offers. Each row also sends its current `baseLockVersion` (exposed by `useSectionSync`, Slice 2A Task 3) in its delete request body.

## Review Focus

- **Cross-case row edit (IDOR-shaped correctness bug, not a tenant leak).** A request for `PUT /student/cases/{caseA}/vitals/{vitalBelongingToCaseB}` where both cases belong to the same authenticated student must be rejected with 404, not silently accepted because the ownership policy alone passes. Every row-editing task's test suite includes this exact cross-case scenario.
- **Duplicate row from a retried or replayed create.** Resubmitting the same `client_operation_id` for a row creation (simulating a flaky network retry, or the offline-queue replaying a create that already reached the server before the device went offline again) must return the **same** row, not create a second one — and reusing that same operation ID against a *different* section endpoint must be rejected outright. Each row-creation task's tests cover both.
- **Stale per-row `lock_version` overwriting a concurrent edit, unresolvable in the UI.** Two edits to the *same row* with the same stale `base_lock_version` must produce exactly one successful save and one 409 conflict, and the conflicting client must be able to resolve it through the same three-way panel Case Profile has — not just see an icon.
- **Deletion bypassing optimistic concurrency or leaving no audit trail.** A delete carrying a stale `base_lock_version` must 409 and delete nothing; a successful delete must record an audit event naming the deleted row's id and the lock version it was deleted at. Each row-deletion task's tests cover both the conflict path and the audit assertion.
- **Section status desynchronized from actual rows.** Creating a row must flip its section's status to `recorded`/`documented` and clear any stale unavailable-reason; attempting to mark a section unavailable while rows still exist must be rejected, not silently accepted alongside the rows. Each backend task's tests cover both directions.
- **Offline row creation lost, duplicated, or stuck.** Adding a row while offline must render immediately, survive a section switch, and replay exactly once when connectivity returns — never silently dropped, never duplicated by the same draft being replayed twice. Task 5's tests (documented as manual, per the no-JS-test-runner constraint) and Task 2/3/4's backend idempotency tests together cover this; Task 7's device verification exercises the full offline-to-online path interactively.

---

## Task 1: Per-row `lock_version`, BP/SpO2/reference-range fields, and `Syncable` for `CaseVital`, `CaseInvestigation`, `CaseMedication`

**Revision:** The original draft added only `lock_version`. The field catalogue (§4.3–§4.5, §5 Technical checks) requires blood pressure to capture systolic and diastolic together, SpO₂ to accept only 0–100, and investigations to distinguish "no unit recorded yet" from "Unit not stated" / "Reference range not provided" as deliberate answers rather than blank fields — none of which the schema supported. This task adds the columns those checks need, ahead of Task 2–4's validation rules.

**Files:**
- Create: `database/migrations/2026_09_29_000000_add_lock_version_and_pharmd_fields_to_repeatable_case_tables.php`
- Modify: `app/Models/CaseVital.php` (implement `Syncable`, add `lock_version`/`value_systolic`/`value_diastolic` to fillable/casts)
- Modify: `app/Models/CaseInvestigation.php` (implement `Syncable`, add `lock_version`/`unit_not_stated`/`reference_range_not_provided`)
- Modify: `app/Models/CaseMedication.php` (implement `Syncable`, add `lock_version`/`indication_unclear`)
- Test: `tests/Feature/PharmdRepeatableRowLockVersionTest.php`

**Interfaces:**
- Produces: `lock_version` (unsigned big integer, default 0) on `case_vitals`, `case_investigations`, `case_medications`. `value_systolic`/`value_diastolic` (unsigned small integer, nullable) on `case_vitals`. `unit_not_stated`/`reference_range_not_provided` (boolean, default false) on `case_investigations`. `indication_unclear` (boolean, default false) on `case_medications`. All three models implement `App\Contracts\Syncable` via `App\Models\Concerns\SyncsWithLockVersion` (from Slice 2A Task 2, single-column default — none of these three models override `lockVersionColumn()`), consumed by Tasks 2–4's controllers.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Contracts\Syncable;
use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdRepeatableRowLockVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_repeatable_tables_have_a_lock_version_column(): void
    {
        $this->assertTrue(Schema::hasColumn('case_vitals', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('case_investigations', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('case_medications', 'lock_version'));
    }

    public function test_case_vitals_has_systolic_and_diastolic_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('case_vitals', ['value_systolic', 'value_diastolic']));
    }

    public function test_case_investigations_has_not_stated_flags(): void
    {
        $this->assertTrue(Schema::hasColumns('case_investigations', ['unit_not_stated', 'reference_range_not_provided']));
    }

    public function test_case_medications_has_indication_unclear_flag(): void
    {
        $this->assertTrue(Schema::hasColumn('case_medications', 'indication_unclear'));
    }

    public function test_all_three_models_implement_syncable_and_bump_on_apply(): void
    {
        [, $student, $case] = $this->makeCase();

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140', 'recorded_by' => $student->id,
        ]);
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'generic_name' => 'Paracetamol', 'status' => MedicationStatus::Active->value, 'recorded_by' => $student->id,
        ]);

        foreach ([$vital, $investigation, $medication] as $row) {
            $this->assertInstanceOf(Syncable::class, $row);
            $this->assertSame(0, $row->getLockVersion('irrelevant-for-single-lock-models'));
            $row->applySyncedAttributes('irrelevant-for-single-lock-models', ['note' => null], 1);
        }
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => CaseStatus::Draft,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=PharmdRepeatableRowLockVersionTest`
Expected: FAIL — unknown columns on `case_vitals` etc.

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
        Schema::table('case_vitals', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->unsignedSmallInteger('value_systolic')->nullable()->after('value_numeric');
            $table->unsignedSmallInteger('value_diastolic')->nullable()->after('value_systolic');
        });

        Schema::table('case_investigations', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->boolean('unit_not_stated')->default(false)->after('unit');
            $table->boolean('reference_range_not_provided')->default(false)->after('reference_range');
        });

        Schema::table('case_medications', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->boolean('indication_unclear')->default(false)->after('indication');
        });
    }

    public function down(): void
    {
        Schema::table('case_vitals', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'value_systolic', 'value_diastolic']);
        });

        Schema::table('case_investigations', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'unit_not_stated', 'reference_range_not_provided']);
        });

        Schema::table('case_medications', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'indication_unclear']);
        });
    }
};
```

- [ ] **Step 4: Update the three models**

In each of `app/Models/CaseVital.php`, `app/Models/CaseInvestigation.php`, `app/Models/CaseMedication.php`, add the imports:

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

Change the class declaration (shown for `CaseVital`; apply the identical shape to the other two, keeping each model's existing `use BelongsToInstitution, HasUlids;` alongside the new trait — none of these three override `lockVersionColumn()`, unlike `ClinicalCase`):

```php
class CaseVital extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

Add the two new columns to `CaseVital`'s `#[Fillable([...])]` array, after `'value_numeric',`: `'value_systolic', 'value_diastolic',`.

Add the two new columns to `CaseInvestigation`'s `#[Fillable([...])]` array, after `'unit',` and `'reference_range',` respectively: `'unit_not_stated',` and `'reference_range_not_provided',`.

Add the new column to `CaseMedication`'s `#[Fillable([...])]` array, after `'indication',`: `'indication_unclear',`.

**Do not** add `'lock_version'` to any of the three Fillable arrays — leave it out entirely; `SyncsWithLockVersion::applySyncedAttributes()` bumps it via `forceFill()`, which bypasses mass-assignment protection by design.

- [ ] **Step 5: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdRepeatableRowLockVersionTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 169 previous passed (Slice 2A total) + 5 new — 174 passed / 2 skipped (176 total).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_29_000000_add_lock_version_and_pharmd_fields_to_repeatable_case_tables.php app/Models/CaseVital.php app/Models/CaseInvestigation.php app/Models/CaseMedication.php tests/Feature/PharmdRepeatableRowLockVersionTest.php
git commit -m "feat: add per-row lock_version, BP pairing, not-stated flags and Syncable to the repeatable Pharm.D case tables"
```

---

## Task 2: `SectionSyncService::create()`, offline-capable row creation, and the Vitals + vitals-availability backend

**Revision:** `create()` now takes the expected model class and checks it against a replayed operation's stored type (same guard as `sync()`, extending `SectionSyncService::SECTION_MODELS`). `StoreCaseVitalRequest` now makes every clinical field optional at creation time (an "Add" tap with nothing filled in must succeed) while still enforcing SpO₂'s 0–100 bound and BP's systolic/diastolic pairing whenever those specific values are present. Creating a vital automatically sets `vitals_status` to `recorded` and clears any stale reason; `UpdateVitalsAvailabilityRequest` now rejects switching to `unavailable` while vitals rows still exist.

**Files:**
- Modify: `app/Services/SectionSyncService.php` (add `create()` method, extend `SECTION_MODELS`)
- Modify: `app/Http/Requests/Student/StoreCaseVitalRequest.php` (add `client_operation_id`, make fields optional at creation, add SpO2/BP validation)
- Create: `app/Http/Requests/Student/UpdateCaseVitalRequest.php`
- Create: `app/Http/Requests/Student/UpdateVitalsAvailabilityRequest.php`
- Create: `app/Http/Controllers/Student/CaseVitalController.php`
- Modify: `routes/web.php` (add vitals routes)
- Test: `tests/Feature/CaseVitalSyncTest.php`

**Interfaces:**
- Produces: `SectionSyncService::create(\Closure $factory, User $user, string $sectionKey, string $clientOperationId): array{status: string, httpStatus: int, model: Model&Syncable}` and `SectionSyncService::delete(Model&Syncable $model, User $user, string $sectionKey, int $baseLockVersion): array{status: string, httpStatus: int, model: (Model&Syncable)|null}` (both reused by Tasks 3 and 4, and by Slice 2C's repeatable clinical-activity rows). `POST /student/cases/{case}/vitals`, `PUT /student/cases/{case}/vitals/{vital}`, `DELETE /student/cases/{case}/vitals/{vital}` (now requires `base_lock_version` in the JSON body), `PUT /student/cases/{case}/vitals-availability`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseVitalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_section_to_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->vitals);
        $this->assertSame('recorded', $case->fresh()->vitals_status);
    }

    public function test_owning_student_can_create_a_vital_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'blood_pressure',
            'value_systolic' => 120,
            'value_diastolic' => 80,
            'unit' => 'mmHg',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('vital.observation_type', 'blood_pressure');
    }

    public function test_blood_pressure_requires_both_systolic_and_diastolic_together(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'blood_pressure',
            'value_systolic' => 120,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('value_diastolic');
    }

    public function test_oxygen_saturation_rejects_a_value_outside_0_to_100(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'oxygen_saturation',
            'value_numeric' => 101,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('value_numeric');
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'observation_type' => 'pulse', 'value_numeric' => 80];

        $this->postJson("/student/cases/{$case->id}/vitals", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/vitals", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->vitals);
    }

    public function test_reusing_a_create_operation_id_against_a_different_section_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();

        $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => $operationId, 'observation_type' => 'pulse', 'value_numeric' => 80,
        ])->assertCreated();

        $response = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => $operationId, 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140',
        ]);

        $response->assertStatus(409);
        $this->assertCount(0, $case->fresh()->investigations);
    }

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'note' => 'Recorded at bedside.',
        ]);

        $response->assertOk();
        $fresh = $vital->fresh();
        $this->assertSame('pulse', $fresh->observation_type);
        $this->assertSame('Recorded at bedside.', $fresh->note);
    }

    public function test_a_stale_base_lock_version_on_a_row_edit_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'First',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Conflicting',
        ]);

        $response->assertStatus(409);
    }

    public function test_editing_a_vital_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 2, 'status' => CaseStatus::Draft,
        ]);
        $vital = $this->makeVital($case, $student);
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$otherCase->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Should not apply',
        ]);

        $response->assertNotFound();
    }

    public function test_owning_student_can_delete_a_vital_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->vitals);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'vitals.deleted',
        ]);
    }

    public function test_deleting_a_vital_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);
        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->vitals);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'vitals.deleted']);
    }

    public function test_owning_student_can_mark_vitals_unavailable_with_a_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Not examined at bedside during this review.',
        ]);

        $response->assertOk();
        $this->assertSame('unavailable', $case->fresh()->vitals_status);
    }

    public function test_marking_vitals_unavailable_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeVital($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Attempted despite existing rows.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('vitals_status');
    }

    public function test_switching_back_to_recorded_clears_the_unavailable_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'vitals_status' => 'unavailable', 'vitals_unavailable_reason' => 'Not examined.',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'vitals_status' => 'recorded',
        ])->assertOk();

        $this->assertNull($case->fresh()->vitals_unavailable_reason);
    }

    public function test_an_unknown_field_on_create_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(), 'observation_type' => 'pulse', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_an_unknown_field_on_the_availability_toggle_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/vitals-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'vitals_status' => 'recorded', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_a_different_student_cannot_create_edit_or_delete_a_vital(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $vital = $this->makeVital($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(), 'observation_type' => 'pulse', 'value_numeric' => 80,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'note' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}")->assertForbidden();
    }

    private function makeVital(ClinicalCase $case, User $student): CaseVital
    {
        return CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
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

Run (PowerShell): `php artisan test --filter=CaseVitalSyncTest`
Expected: FAIL — routes not found.

- [ ] **Step 3: Add `create()` and `delete()` to `SectionSyncService`, extend `SECTION_MODELS`**

**Revision (second review round):** `create()` originally took a caller-supplied `$expectedClass` argument — a controller bug could pass anything, routing around the whole point of the section allow-list. It now derives the expected class internally via `modelClassForSection()` (Slice 2A Task 2), the same resolver `sync()` uses, and asserts the model the factory actually produced matches. This step also adds `delete()`, giving row deletion the same optimistic-concurrency check and audit trail every other write already has (the original draft's `destroy()` endpoints deleted unconditionally with no lock check and no audit event).

In `app/Services/SectionSyncService.php`, add three entries to the `SECTION_MODELS` constant (add the imports `use App\Models\CaseVital;`, `use App\Models\CaseInvestigation;`, `use App\Models\CaseMedication;`):

```php
        'vitals' => CaseVital::class,
        'investigations' => CaseInvestigation::class,
        'medications' => CaseMedication::class,
```

Add these two public methods (after `sync()`):

```php
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
            abort_unless($model::class === $expectedClass, 500, "Model/section mismatch: {$sectionKey} expects {$expectedClass}, got {$model::class}.");

            $this->recordOperation($user, $model, $sectionKey, $clientOperationId, 0, 'saved');

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
        abort_unless($model::class === $expectedClass, 500, "Model/section mismatch: {$sectionKey} expects {$expectedClass}, got {$model::class}.");

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
```

`Model` and `User` are already imported from Task 2 of Slice 2A.

- [ ] **Step 4: Rewrite `StoreCaseVitalRequest` — optional at creation, with BP-pairing and SpO2 validation**

Replace the full contents of `app/Http/Requests/Student/StoreCaseVitalRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCaseVitalRequest extends FormRequest
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
            'observation_type' => ['nullable', 'string', 'max:40'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_text' => ['nullable', 'string', 'max:60'],
            'value_systolic' => ['nullable', 'integer', 'min:40', 'max:300'],
            'value_diastolic' => ['nullable', 'integer', 'min:20', 'max:200'],
            'unit' => ['nullable', 'string', 'max:20'],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'source' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            $type = $this->input('observation_type');

            // BP must be entered as a pair, per field catalogue §5 ("Systolic
            // and diastolic pressure are entered together") — the check is
            // symmetric so a partial pair fails on whichever side is missing.
            if ($type === 'blood_pressure') {
                if ($this->filled('value_systolic') && ! $this->filled('value_diastolic')) {
                    $validator->errors()->add('value_diastolic', 'Diastolic pressure is required when systolic pressure is recorded.');
                }
                if ($this->filled('value_diastolic') && ! $this->filled('value_systolic')) {
                    $validator->errors()->add('value_systolic', 'Systolic pressure is required when diastolic pressure is recorded.');
                }
            }

            // SpO2 accepts 0-100 only (field catalogue §5), independent of
            // the generic numeric-vital rule above which has no fixed range.
            if ($type === 'oxygen_saturation' && $this->filled('value_numeric')) {
                $value = (float) $this->input('value_numeric');
                if ($value < 0 || $value > 100) {
                    $validator->errors()->add('value_numeric', 'Oxygen saturation must be between 0 and 100.');
                }
            }
        });
    }
}
```

- [ ] **Step 5: Write `UpdateCaseVitalRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCaseVitalRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vital')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'observation_type' => ['sometimes', 'required', 'string', 'max:40'],
            'value_numeric' => ['sometimes', 'nullable', 'numeric'],
            'value_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'value_systolic' => ['sometimes', 'nullable', 'integer', 'min:40', 'max:300'],
            'value_diastolic' => ['sometimes', 'nullable', 'integer', 'min:20', 'max:200'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'source' => ['sometimes', 'nullable', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            $vital = $this->route('vital');
            $type = $this->has('observation_type') ? $this->input('observation_type') : $vital?->observation_type;

            if ($type === 'oxygen_saturation' && $this->filled('value_numeric')) {
                $value = (float) $this->input('value_numeric');
                if ($value < 0 || $value > 100) {
                    $validator->errors()->add('value_numeric', 'Oxygen saturation must be between 0 and 100.');
                }
            }
        });
    }
}
```

- [ ] **Step 6: Write `UpdateVitalsAvailabilityRequest` — rejects `unavailable` while rows exist**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVitalsAvailabilityRequest extends FormRequest
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
            'vitals_status' => ['sometimes', 'nullable', Rule::in(['recorded', 'unavailable'])],
            'vitals_unavailable_reason' => ['sometimes', 'nullable', 'required_if:vitals_status,unavailable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('vitals_status') !== 'unavailable') {
                return;
            }

            $case = $this->route('case');
            if ($case->vitals()->exists()) {
                $validator->errors()->add('vitals_status', 'Remove the recorded vitals before marking this section unavailable.');
            }
        });
    }
}
```

- [ ] **Step 7: Write `CaseVitalController` — auto-sets section status on create, clears reason on recorded**

```php
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

        return response()->json(['vital' => $this->payload($result['model'])], $result['httpStatus']);
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

        return response()->json(['vital' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseVital $vital, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $vital);
        abort_unless($vital->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($vital, $request->user(), 'vitals', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            return response()->json(['vital' => $this->payload($result['model'])], 409);
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

        return response()->json(['section' => [
            'vitals_status' => $result['model']->vitals_status,
            'vitals_unavailable_reason' => $result['model']->vitals_unavailable_reason,
            'lock_version' => $result['model']->vitals_availability_lock_version,
            'updated_at' => $result['model']->updated_at->toIso8601String(),
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
```

Note the `syncAvailability` response's `lock_version` key is deliberately `$result['model']->vitals_availability_lock_version`, not `->lock_version` — the frontend's `useSectionSync` treats whatever comes back under `lock_version` in the JSON body as the section's own lock, and this section's real lock column (per Slice 2A Task 2's `ClinicalCase::lockVersionColumn()`) is `vitals_availability_lock_version`. Getting this wrong would make the client believe it holds a valid `base_lock_version` for the next sync when it actually doesn't, causing every subsequent save to 409. `CaseInvestigationController`/`CaseMedicationController` (Tasks 3–4) follow the same pattern for their own availability lock columns.

- [ ] **Step 8: Add routes**

In `routes/web.php`, add inside the `role:student` group, immediately after `student.cases.edit`:

```php
        Route::post('student/cases/{case}/vitals', [CaseVitalController::class, 'store'])->name('student.cases.vitals.store');
        Route::put('student/cases/{case}/vitals/{vital}', [CaseVitalController::class, 'sync'])->name('student.cases.vitals.sync');
        Route::delete('student/cases/{case}/vitals/{vital}', [CaseVitalController::class, 'destroy'])->name('student.cases.vitals.destroy');
        Route::put('student/cases/{case}/vitals-availability', [CaseVitalController::class, 'syncAvailability'])->name('student.cases.vitals.availability.sync');
```

Add the import: `use App\Http\Controllers\Student\CaseVitalController;`

- [ ] **Step 9: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 10: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseVitalSyncTest`
Expected: PASS (17 tests).

- [ ] **Step 11: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 174 previous passed + 17 new — 191 passed / 2 skipped (193 total).

- [ ] **Step 12: Commit**

```bash
git add app/Services/SectionSyncService.php app/Http/Requests/Student/StoreCaseVitalRequest.php app/Http/Requests/Student/UpdateCaseVitalRequest.php app/Http/Requests/Student/UpdateVitalsAvailabilityRequest.php app/Http/Controllers/Student/CaseVitalController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseVitalSyncTest.php
git commit -m "feat: add idempotent row creation to SectionSyncService and wire up the Vitals backend with BP/SpO2 validation and status auto-sync"
```

---

## Task 3: Investigations backend (unit-not-stated / reference-range-not-provided, auto-status-sync)

**Revision:** Same shape as Task 2's revision — creation fields are optional, `unit_not_stated`/`reference_range_not_provided` are explicit answers (not just "leave it blank"), creating a row sets `investigations_status` to `recorded`, and the availability toggle rejects `unavailable` while rows exist.

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseInvestigationRequest.php` (add `client_operation_id`, make fields optional, add not-stated flags)
- Create: `app/Http/Requests/Student/UpdateCaseInvestigationRequest.php`
- Create: `app/Http/Requests/Student/UpdateInvestigationsAvailabilityRequest.php`
- Create: `app/Http/Controllers/Student/CaseInvestigationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CaseInvestigationSyncTest.php`

**Interfaces:**
- Consumes: `SectionSyncService::create()`/`sync()` (Task 2).
- Produces: `POST /student/cases/{case}/investigations`, `PUT /student/cases/{case}/investigations/{investigation}`, `DELETE /student/cases/{case}/investigations/{investigation}`, `PUT /student/cases/{case}/investigations-availability`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseInvestigation;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseInvestigationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_section_to_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame('recorded', $case->fresh()->investigations_status);
    }

    public function test_owning_student_can_create_an_investigation_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->investigations);
    }

    public function test_unit_not_stated_and_reference_range_not_provided_can_be_recorded(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Random glucose', 'result_type' => 'numeric', 'result_value' => '110',
            'unit_not_stated' => true, 'reference_range_not_provided' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('investigation.unit_not_stated', true);
        $response->assertJsonPath('investigation.reference_range_not_provided', true);
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140'];

        $this->postJson("/student/cases/{$case->id}/investigations", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/investigations", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->investigations);
    }

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'interpretation' => 'Within expected range for this ward context.',
        ]);

        $response->assertOk();
        $fresh = $investigation->fresh();
        $this->assertSame('Sodium', $fresh->test_name);
        $this->assertSame('Within expected range for this ward context.', $fresh->interpretation);
    }

    public function test_a_stale_base_lock_version_on_a_row_edit_returns_409(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'First',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Conflicting',
        ])->assertStatus(409);
    }

    public function test_editing_an_investigation_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 2, 'status' => CaseStatus::Draft,
        ]);
        $investigation = $this->makeInvestigation($case, $student);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$otherCase->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Should not apply',
        ])->assertNotFound();
    }

    public function test_owning_student_can_delete_an_investigation_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->investigations);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'investigations.deleted']);
    }

    public function test_deleting_an_investigation_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);
        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->investigations);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'investigations.deleted']);
    }

    public function test_marking_investigations_unavailable_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeInvestigation($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'investigations_status' => 'unavailable', 'investigations_unavailable_reason' => 'Attempted despite rows.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('investigations_status');
    }

    public function test_owning_student_can_mark_investigations_unavailable_with_a_reason(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/investigations-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'investigations_status' => 'unavailable',
            'investigations_unavailable_reason' => 'No investigations ordered for this review.',
        ]);

        $response->assertOk();
        $this->assertSame('unavailable', $case->fresh()->investigations_status);
    }

    public function test_a_different_student_cannot_create_edit_or_delete_an_investigation(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $investigation = $this->makeInvestigation($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/investigations", [
            'client_operation_id' => (string) Str::uuid(), 'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140',
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'interpretation' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}")->assertForbidden();
    }

    private function makeInvestigation(ClinicalCase $case, User $student): CaseInvestigation
    {
        return CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140', 'recorded_by' => $student->id,
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

Run (PowerShell): `php artisan test --filter=CaseInvestigationSyncTest`
Expected: FAIL — routes not found.

- [ ] **Step 3: Rewrite `StoreCaseInvestigationRequest` — optional at creation, with not-stated flags**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCaseInvestigationRequest extends FormRequest
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
            'test_name' => ['nullable', 'string', 'max:120'],
            'result_type' => ['nullable', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_not_stated' => ['sometimes', 'boolean'],
            'reference_range' => ['nullable', 'string', 'max:120'],
            'reference_range_not_provided' => ['sometimes', 'boolean'],
            'reported_flag' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'interpretation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 4: Write `UpdateCaseInvestigationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseInvestigationRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('investigation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'test_name' => ['sometimes', 'required', 'string', 'max:120'],
            'result_type' => ['sometimes', 'required', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'unit_not_stated' => ['sometimes', 'boolean'],
            'reference_range' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reference_range_not_provided' => ['sometimes', 'boolean'],
            'reported_flag' => ['sometimes', 'nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'interpretation' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 5: Write `UpdateInvestigationsAvailabilityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvestigationsAvailabilityRequest extends FormRequest
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
            'investigations_status' => ['sometimes', 'nullable', Rule::in(['recorded', 'unavailable'])],
            'investigations_unavailable_reason' => ['sometimes', 'nullable', 'required_if:investigations_status,unavailable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('investigations_status') !== 'unavailable') {
                return;
            }

            if ($this->route('case')->investigations()->exists()) {
                $validator->errors()->add('investigations_status', 'Remove the recorded investigations before marking this section unavailable.');
            }
        });
    }
}
```

- [ ] **Step 6: Write `CaseInvestigationController`**

```php
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

        return response()->json(['investigation' => $this->payload($result['model'])], $result['httpStatus']);
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

        return response()->json(['investigation' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseInvestigation $investigation, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $investigation);
        abort_unless($investigation->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($investigation, $request->user(), 'investigations', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            return response()->json(['investigation' => $this->payload($result['model'])], 409);
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

        return response()->json(['section' => [
            'investigations_status' => $result['model']->investigations_status,
            'investigations_unavailable_reason' => $result['model']->investigations_unavailable_reason,
            'lock_version' => $result['model']->investigations_availability_lock_version,
            'updated_at' => $result['model']->updated_at->toIso8601String(),
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
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, immediately after the Vitals routes:

```php
        Route::post('student/cases/{case}/investigations', [CaseInvestigationController::class, 'store'])->name('student.cases.investigations.store');
        Route::put('student/cases/{case}/investigations/{investigation}', [CaseInvestigationController::class, 'sync'])->name('student.cases.investigations.sync');
        Route::delete('student/cases/{case}/investigations/{investigation}', [CaseInvestigationController::class, 'destroy'])->name('student.cases.investigations.destroy');
        Route::put('student/cases/{case}/investigations-availability', [CaseInvestigationController::class, 'syncAvailability'])->name('student.cases.investigations.availability.sync');
```

Add the import: `use App\Http\Controllers\Student\CaseInvestigationController;`

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseInvestigationSyncTest`
Expected: PASS (12 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 191 previous passed + 12 new — 203 passed / 2 skipped (205 total).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseInvestigationRequest.php app/Http/Requests/Student/UpdateCaseInvestigationRequest.php app/Http/Requests/Student/UpdateInvestigationsAvailabilityRequest.php app/Http/Controllers/Student/CaseInvestigationController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseInvestigationSyncTest.php
git commit -m "feat: wire up the Investigations backend with unit/reference-range not-stated flags and status auto-sync"
```

---

## Task 4: Medication Chart backend (with the fixed `stop_reference` conditional, `indication_unclear`, and status auto-sync)

**Revision:** Same creation-is-optional and status-auto-sync pattern as Tasks 2–3, plus the `indication_unclear` flag (field catalogue §4.5 "Indication | M | Free text or Indication unclear") and the `medication_chart_none_reason` explanation field Slice 2A Task 1 added to `clinical_cases`.

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseMedicationRequest.php` (add `client_operation_id`, make fields optional at creation, add `indication_unclear`, fix the missing `stop_reference` conditional)
- Create: `app/Http/Requests/Student/UpdateCaseMedicationRequest.php`
- Create: `app/Http/Requests/Student/UpdateMedicationChartAvailabilityRequest.php`
- Create: `app/Http/Controllers/Student/CaseMedicationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CaseMedicationSyncTest.php`

**Interfaces:**
- Consumes: `SectionSyncService::create()`/`sync()` (Task 2).
- Produces: `POST /student/cases/{case}/medications`, `PUT /student/cases/{case}/medications/{medication}`, `DELETE /student/cases/{case}/medications/{medication}`, `PUT /student/cases/{case}/medication-chart-availability`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseMedicationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_add_row_tap_succeeds_and_sets_the_chart_to_documented(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
        ])->assertCreated();

        $this->assertSame('documented', $case->fresh()->medication_chart_status);
    }

    public function test_owning_student_can_create_a_medication_row_with_data(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Paracetamol',
            'status' => MedicationStatus::Active->value,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $case->fresh()->medications);
    }

    public function test_indication_unclear_can_be_recorded_instead_of_indication_text(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin', 'status' => MedicationStatus::Active->value,
            'indication_unclear' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('medication.indication_unclear', true);
    }

    public function test_stop_reference_is_required_when_status_is_stopped(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Stopped->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stop_reference');
    }

    public function test_stop_reference_is_required_when_status_is_completed(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Completed->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stop_reference');
    }

    public function test_replaying_the_same_create_operation_id_does_not_create_a_second_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();
        $payload = ['client_operation_id' => $operationId, 'generic_name' => 'Metformin', 'status' => MedicationStatus::Active->value];

        $this->postJson("/student/cases/{$case->id}/medications", $payload)->assertCreated();
        $this->postJson("/student/cases/{$case->id}/medications", $payload)->assertCreated();

        $this->assertCount(1, $case->fresh()->medications);
    }

    public function test_a_single_field_edit_does_not_require_or_erase_other_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'notes' => 'Reviewed with student.',
        ]);

        $response->assertOk();
        $fresh = $medication->fresh();
        $this->assertSame('Metformin', $fresh->generic_name);
        $this->assertSame('Reviewed with student.', $fresh->notes);
    }

    public function test_editing_a_medication_through_a_different_case_id_in_the_url_is_rejected(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 2, 'status' => CaseStatus::Draft,
        ]);
        $medication = $this->makeMedication($case, $student);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$otherCase->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'Should not apply',
        ])->assertNotFound();
    }

    public function test_owning_student_can_delete_a_medication_row_at_the_correct_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}", ['base_lock_version' => 0])->assertNoContent();

        $this->assertCount(0, $case->fresh()->medications);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'medications.deleted']);
    }

    public function test_deleting_a_medication_row_with_a_stale_base_lock_version_returns_409_and_does_not_delete(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);
        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'Bumps the lock version.',
        ])->assertOk();

        $response = $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}", ['base_lock_version' => 0]);

        $response->assertStatus(409);
        $this->assertCount(1, $case->fresh()->medications);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'medications.deleted']);
    }

    public function test_marking_no_current_medicines_is_rejected_while_rows_exist(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $this->makeMedication($case, $student);

        $response = $this->putJson("/student/cases/{$case->id}/medication-chart-availability", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'medication_chart_status' => 'none_documented',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('medication_chart_status');
    }

    public function test_owning_student_can_mark_no_current_medicines_documented_with_an_explanation(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/medication-chart-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'medication_chart_status' => 'none_documented',
            'medication_chart_none_reason' => 'No home or chart medicines reported by caregiver.',
        ]);

        $response->assertOk();
        $this->assertSame('none_documented', $case->fresh()->medication_chart_status);
        $this->assertSame('No home or chart medicines reported by caregiver.', $case->fresh()->medication_chart_none_reason);
    }

    public function test_a_different_student_cannot_create_edit_or_delete_a_medication(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $medication = $this->makeMedication($case, $student);
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/medications", [
            'client_operation_id' => (string) Str::uuid(), 'generic_name' => 'Ibuprofen', 'status' => MedicationStatus::Active->value,
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'notes' => 'x',
        ])->assertForbidden();

        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}")->assertForbidden();
    }

    private function makeMedication(ClinicalCase $case, User $student): CaseMedication
    {
        return CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin', 'status' => MedicationStatus::Active->value, 'recorded_by' => $student->id,
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

Run (PowerShell): `php artisan test --filter=CaseMedicationSyncTest`
Expected: FAIL — routes not found.

- [ ] **Step 3: Rewrite `StoreCaseMedicationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCaseMedicationRequest extends FormRequest
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
            'medication_context' => ['nullable', Rule::in(['chart', 'history'])],
            'generic_name' => ['nullable', 'string', 'max:120'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'indication' => ['nullable', 'required_if:indication_unclear,false', 'string', 'max:255'],
            'indication_unclear' => ['sometimes', 'boolean'],
            'dose_amount' => ['nullable', 'string', 'max:30'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'dosage_form' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency' => ['nullable', 'string', 'max:60'],
            'start_reference' => ['nullable', 'string', 'max:30'],
            'stop_reference' => ['nullable', 'required_if:status,stopped', 'required_if:status,completed', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(array_map(fn (MedicationStatus $s): string => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 4: Write `UpdateCaseMedicationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseMedicationRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('medication')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'medication_context' => ['sometimes', 'nullable', Rule::in(['chart', 'history'])],
            'generic_name' => ['sometimes', 'required', 'string', 'max:120'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'indication' => ['sometimes', 'nullable', 'required_if:indication_unclear,false', 'string', 'max:255'],
            'indication_unclear' => ['sometimes', 'boolean'],
            'dose_amount' => ['sometimes', 'nullable', 'string', 'max:30'],
            'dose_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dosage_form' => ['sometimes', 'nullable', 'string', 'max:30'],
            'route' => ['sometimes', 'nullable', 'string', 'max:30'],
            'frequency' => ['sometimes', 'nullable', 'string', 'max:60'],
            'start_reference' => ['sometimes', 'nullable', 'string', 'max:30'],
            'stop_reference' => ['sometimes', 'nullable', 'required_if:status,stopped', 'required_if:status,completed', 'string', 'max:30'],
            'status' => ['sometimes', 'required', Rule::in(array_map(fn (MedicationStatus $s): string => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['sometimes', 'nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 5: Write `UpdateMedicationChartAvailabilityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMedicationChartAvailabilityRequest extends FormRequest
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
            'medication_chart_status' => ['sometimes', 'nullable', Rule::in(['documented', 'none_documented'])],
            'medication_chart_none_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('medication_chart_status') !== 'none_documented') {
                return;
            }

            if ($this->route('case')->medications()->exists()) {
                $validator->errors()->add('medication_chart_status', 'Remove the recorded medicines before marking no current medicines documented.');
            }
        });
    }
}
```

- [ ] **Step 6: Write `CaseMedicationController`**

```php
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

        return response()->json(['medication' => $this->payload($result['model'])], $result['httpStatus']);
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

        return response()->json(['medication' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function destroy(Request $request, ClinicalCase $case, CaseMedication $medication, SectionSyncService $sync): JsonResponse|Response
    {
        Gate::authorize('update', $medication);
        abort_unless($medication->clinical_case_id === $case->id, 404);

        $data = $request->validate(['base_lock_version' => ['required', 'integer', 'min:0']]);

        $result = $sync->delete($medication, $request->user(), 'medications', $data['base_lock_version']);

        if ($result['status'] === 'conflict') {
            return response()->json(['medication' => $this->payload($result['model'])], 409);
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

        return response()->json(['section' => [
            'medication_chart_status' => $result['model']->medication_chart_status,
            'medication_chart_none_reason' => $result['model']->medication_chart_none_reason,
            'lock_version' => $result['model']->medication_chart_availability_lock_version,
            'updated_at' => $result['model']->updated_at->toIso8601String(),
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
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, immediately after the Investigations routes:

```php
        Route::post('student/cases/{case}/medications', [CaseMedicationController::class, 'store'])->name('student.cases.medications.store');
        Route::put('student/cases/{case}/medications/{medication}', [CaseMedicationController::class, 'sync'])->name('student.cases.medications.sync');
        Route::delete('student/cases/{case}/medications/{medication}', [CaseMedicationController::class, 'destroy'])->name('student.cases.medications.destroy');
        Route::put('student/cases/{case}/medication-chart-availability', [CaseMedicationController::class, 'syncAvailability'])->name('student.cases.medications.availability.sync');
```

Add the import: `use App\Http\Controllers\Student\CaseMedicationController;`

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseMedicationSyncTest`
Expected: PASS (13 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 203 previous passed + 13 new — 216 passed / 2 skipped (218 total).

- [ ] **Step 11: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseMedicationRequest.php app/Http/Requests/Student/UpdateCaseMedicationRequest.php app/Http/Requests/Student/UpdateMedicationChartAvailabilityRequest.php app/Http/Controllers/Student/CaseMedicationController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseMedicationSyncTest.php
git commit -m "feat: wire up the Medication Chart backend with indication_unclear, a none-documented explanation, and status auto-sync"
```

---

## Task 5: Frontend — offline-capable row creation, per-row conflict UI, and the full field set for Vitals, Investigations and Medication Chart

**Revision:** This is the task most substantially rewritten. The original draft's "Add" buttons called `fetch()` directly (requiring connectivity and failing on a bare tap because `observation_type`/`test_name`/`generic_name` were still `required`), and each row component showed a conflict only as an unresolvable icon. This revision adds a `useRepeatableRowCreate` composable (sibling to `useSectionSync`) that queues a create in the IndexedDB outbox and replays it on reconnect, gives every row component the same three-way conflict panel `CaseProfileSection.vue` already has, and renders every field the backend now validates (BP pair, SpO2, source/time/notes on vitals; reference range, not-stated flags, date/time on investigations; brand, indication, dosage form, frequency with expanded OD/BD/TDS/QID wording, start reference and medication context on medications) with real `<label>` elements instead of bare placeholders.

**Files:**
- Create: `resources/js/composables/useRepeatableRowCreate.ts`
- Create: `resources/js/pages/student/case-editor/VitalRow.vue`
- Create: `resources/js/pages/student/case-editor/InvestigationRow.vue`
- Create: `resources/js/pages/student/case-editor/VitalsInvestigationsSection.vue`
- Create: `resources/js/pages/student/case-editor/MedicationRow.vue`
- Create: `resources/js/pages/student/case-editor/MedicationChartSection.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue` (wire in both sections)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass vitals/investigations/medications + availability props, using each section's own lock column)
- Test: `tests/Feature/CaseEditorPageTest.php` (extend)

**Interfaces:**
- Consumes: `useSectionSync` (Slice 2A Task 3), `DeidentificationNotice.vue` (Slice 2A Task 5), the four controllers from Tasks 2–4.
- Produces: `useRepeatableRowCreate<T>(options): { online, queueCreate, replayPending, handleOnline, handleOffline }` — reused by Slice 2C's Pharmacist intervention / Monitoring follow-up rows.

- [ ] **Step 1: Write the failing test (extend `CaseEditorPageTest`)**

Append to `tests/Feature/CaseEditorPageTest.php`, inside the class, after the existing `test_editor_reflects_an_existing_profile` method:

```php
    public function test_editor_page_includes_vitals_investigations_and_medications_with_their_own_lock_columns(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        \App\Models\CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page
            ->has('vitals', 1)
            ->where('vitals.0.observation_type', 'pulse')
            ->has('investigations', 0)
            ->has('medications', 0)
            ->where('context.vitals_status', null)
            ->where('context.vitals_availability_lock_version', 0)
            ->where('context.investigations_availability_lock_version', 0)
            ->where('context.medication_chart_availability_lock_version', 0));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: FAIL — `vitals`/`investigations`/`medications` props missing.

- [ ] **Step 3: Extend `CaseEditorController`**

In `app/Http/Controllers/Student/CaseEditorController.php`, add imports:

```php
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
```

Add nine keys to the `context` array (after `'case_display' => [...],`) — three status fields, two reason fields (`vitals`/`investigations` share the "no rows" gate but medications additionally gets its explanation field), and the three lock columns Slice 2A Task 2 gave `ClinicalCase`:

```php
                'vitals_status' => $case->vitals_status,
                'vitals_unavailable_reason' => $case->vitals_unavailable_reason,
                'vitals_availability_lock_version' => $case->vitals_availability_lock_version,
                'investigations_status' => $case->investigations_status,
                'investigations_unavailable_reason' => $case->investigations_unavailable_reason,
                'investigations_availability_lock_version' => $case->investigations_availability_lock_version,
                'medication_chart_status' => $case->medication_chart_status,
                'medication_chart_none_reason' => $case->medication_chart_none_reason,
                'medication_chart_availability_lock_version' => $case->medication_chart_availability_lock_version,
```

Add three new top-level props to the `Inertia::render(...)` array (after `'clinicalProfile' => ...,`):

```php
            'vitals' => $case->vitals()->orderByDesc('created_at')->get()->map(fn (CaseVital $vital): array => [
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
            ])->all(),
            'investigations' => $case->investigations()->orderByDesc('created_at')->get()->map(fn (CaseInvestigation $investigation): array => [
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
            ])->all(),
            'medications' => $case->medications()->orderByDesc('created_at')->get()->map(fn (CaseMedication $medication): array => [
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
            ])->all(),
```

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Write `useRepeatableRowCreate.ts`**

```typescript
import { ref } from 'vue';
import { deleteSection, listAllSections, putSection, sectionKey as buildSectionKey, type StoredSection } from '@/lib/outboxStore';

export type RepeatableRowCreateOptions = {
    userId: number;
    sectionKey: string;
    endpoint: string;
    responseKey: string;
    emptyPayload: () => Record<string, unknown>;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * A "local:" prefix marks a row that only exists in this browser's IndexedDB
 * outbox and has never reached the server. Row components use this to decide
 * whether they can safely call useSectionSync (which needs a real server id
 * to build its endpoint) or must render a pending state instead.
 */
export const LOCAL_ROW_PREFIX = 'local:';

export function useRepeatableRowCreate<T extends { id: string }>(options: RepeatableRowCreateOptions) {
    const online = ref(navigator.onLine);
    const createSectionKey = `${options.sectionKey}:create`;

    async function flush(draft: StoredSection<Record<string, unknown>>): Promise<T | null> {
        try {
            const response = await fetch(options.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ client_operation_id: draft.clientOperationId, ...draft.payload }),
            });
            if (!response.ok) return null;
            const body = (await response.json()) as Record<string, T>;
            await deleteSection(draft.key);
            return body[options.responseKey];
        } catch {
            return null;
        }
    }

    /** Writes a local draft immediately and, if online, tries to sync it now. */
    async function queueCreate(): Promise<T> {
        const clientOperationId = crypto.randomUUID();
        const localId = `${LOCAL_ROW_PREFIX}${clientOperationId}`;
        const payload = options.emptyPayload();
        const draft: StoredSection<Record<string, unknown>> = {
            key: buildSectionKey(options.userId, createSectionKey, localId),
            sectionKey: createSectionKey,
            resourceId: localId,
            userId: options.userId,
            payload,
            baseLockVersion: 0,
            clientOperationId,
            updatedAt: new Date().toISOString(),
        };
        await putSection(draft);

        const optimisticRow = { ...payload, id: localId, lock_version: 0, updated_at: draft.updatedAt } as unknown as T;

        if (!online.value) return optimisticRow;

        return (await flush(draft)) ?? optimisticRow;
    }

    /** Cancels a queued create that never reached the server (pure local removal, works offline). */
    async function cancelQueuedCreate(localId: string) {
        await deleteSection(buildSectionKey(options.userId, createSectionKey, localId));
    }

    /** Finds every queued draft for this section and this user and retries each one. */
    async function replayPending(onReplaced: (localId: string, row: T) => void) {
        const all = await listAllSections<Record<string, unknown>>();
        const pending = all.filter((section) => section.sectionKey === createSectionKey && section.userId === options.userId);
        for (const draft of pending) {
            const synced = await flush(draft);
            if (synced) onReplaced(draft.resourceId, synced);
        }
    }

    function handleOnline() {
        online.value = true;
    }
    function handleOffline() {
        online.value = false;
    }

    return { online, queueCreate, cancelQueuedCreate, replayPending, handleOnline, handleOffline };
}
```

- [ ] **Step 6: Write `VitalRow.vue` — full field set, real labels, and the three-way conflict panel**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

type VitalPayload = SyncedSection & {
    id: string;
    observation_type: string | null;
    value_numeric: string | null;
    value_text: string | null;
    value_systolic: number | null;
    value_diastolic: number | null;
    unit: string | null;
    observed_on: string | null;
    observed_at_time: string | null;
    source: string | null;
    note: string | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: VitalPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, savedAt, edit, online, baseLockVersion, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace, adoptServerSnapshot } =
    useSectionSync<VitalPayload>({
        userId: props.userId,
        resourceId: props.initial.id,
        sectionKey: 'vitals',
        endpoint: `/student/cases/${props.caseId}/vitals/${props.initial.id}`,
        initialPayload: props.initial,
    });

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

const deleteConflict = ref(false);
watch(savedAt, () => { deleteConflict.value = false; });

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/vitals/${props.initial.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ base_lock_version: baseLockVersion.value }),
    });
    if (response.ok) {
        emit('removed', props.initial.id);
        return;
    }
    if (response.status === 409) {
        const body = (await response.json()) as { vital: VitalPayload };
        await adoptServerSnapshot(body.vital);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`vital-row-${initial.id}`">
        <p v-if="deleteConflict" data-test="vital-delete-conflict" class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            This row changed on the server after this device last saw it. The latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Observation type</span>
                <select v-model="payload.observation_type" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option :value="null">Select observation…</option>
                    <option value="blood_pressure">Blood pressure</option>
                    <option value="pulse">Pulse/heart rate</option>
                    <option value="respiratory_rate">Respiratory rate</option>
                    <option value="temperature">Temperature</option>
                    <option value="oxygen_saturation">Oxygen saturation (SpO2)</option>
                    <option value="weight">Weight</option>
                    <option value="height">Height</option>
                    <option value="blood_glucose">Blood glucose</option>
                </select>
            </label>
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove vital" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>

        <div v-if="payload.observation_type === 'blood_pressure'" class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Systolic</span>
                <input v-model.number="payload.value_systolic" type="number" min="40" max="300" data-test="vital-systolic" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Diastolic</span>
                <input v-model.number="payload.value_diastolic" type="number" min="20" max="200" data-test="vital-diastolic" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>
        <label v-else class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Value</span>
            <input v-model="payload.value_numeric" type="text" data-test="vital-value" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Unit</span>
                <input v-model="payload.unit" type="text" maxlength="20" data-test="vital-unit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Case date</span>
                <input v-model="payload.observed_on" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Time (optional)</span>
                <input v-model="payload.observed_at_time" type="time" data-test="vital-time" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Source</span>
                <input v-model="payload.source" type="text" maxlength="60" data-test="vital-source" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>
        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Note</span>
            <input v-model="payload.note" type="text" maxlength="1000" data-test="vital-note" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <section v-if="conflict" data-test="vital-conflict" class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900">
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

- [ ] **Step 7: Write `InvestigationRow.vue` — full field set including not-stated flags**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type InvestigationPayload = SyncedSection & {
    id: string;
    test_name: string | null;
    result_type: string | null;
    result_value: string | null;
    unit: string | null;
    unit_not_stated: boolean;
    reference_range: string | null;
    reference_range_not_provided: boolean;
    reported_flag: string | null;
    observed_on: string | null;
    observed_at_time: string | null;
    interpretation: string | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: InvestigationPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, savedAt, edit, online, baseLockVersion, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace, adoptServerSnapshot } =
    useSectionSync<InvestigationPayload>({
        userId: props.userId,
        resourceId: props.initial.id,
        sectionKey: 'investigations',
        endpoint: `/student/cases/${props.caseId}/investigations/${props.initial.id}`,
        initialPayload: props.initial,
    });

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

const deleteConflict = ref(false);
watch(savedAt, () => { deleteConflict.value = false; });

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/investigations/${props.initial.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ base_lock_version: baseLockVersion.value }),
    });
    if (response.ok) {
        emit('removed', props.initial.id);
        return;
    }
    if (response.status === 409) {
        const body = (await response.json()) as { investigation: InvestigationPayload };
        await adoptServerSnapshot(body.investigation);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`investigation-row-${initial.id}`">
        <p v-if="deleteConflict" data-test="investigation-delete-conflict" class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            This row changed on the server after this device last saw it. The latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Test name</span>
                <input v-model="payload.test_name" type="text" maxlength="120" placeholder="Test name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove investigation" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="sr-only">Result type</span>
                <select v-model="payload.result_type" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option value="numeric">Numeric</option>
                    <option value="qualitative">Qualitative</option>
                    <option value="narrative">Narrative/report</option>
                </select>
            </label>
            <label class="text-sm">
                <span class="sr-only">Result value</span>
                <input v-model="payload.result_value" type="text" maxlength="255" placeholder="Result" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="sr-only">Case date</span>
                <input v-model="payload.observed_on" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="sr-only">Time (optional)</span>
                <input v-model="payload.observed_at_time" type="time" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Unit</span>
                <input v-model="payload.unit" type="text" maxlength="20" :disabled="payload.unit_not_stated" data-test="investigation-unit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="flex items-center gap-1.5 self-end text-xs">
                <input v-model="payload.unit_not_stated" type="checkbox" data-test="investigation-unit-not-stated" @change="edit" />
                Unit not stated
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Reference range</span>
                <input v-model="payload.reference_range" type="text" maxlength="120" :disabled="payload.reference_range_not_provided" data-test="investigation-reference-range" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="flex items-center gap-1.5 self-end text-xs">
                <input v-model="payload.reference_range_not_provided" type="checkbox" data-test="investigation-reference-range-not-provided" @change="edit" />
                Reference range not provided
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Hospital-reported flag</span>
            <select v-model="payload.reported_flag" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                <option :value="null">Not stated</option>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </label>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Student clinical interpretation (optional)</span>
            <textarea v-model="payload.interpretation" rows="2" maxlength="2000" data-test="investigation-interpretation" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.interpretation" />
        </label>

        <section v-if="conflict" data-test="investigation-conflict" class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900">
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

- [ ] **Step 8: Write `VitalsInvestigationsSection.vue` — offline-capable add, pending-row handling**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import { LOCAL_ROW_PREFIX, useRepeatableRowCreate } from '@/composables/useRepeatableRowCreate';
import VitalRow from './VitalRow.vue';
import InvestigationRow from './InvestigationRow.vue';

type RowPayload = SyncedSection & { id: string; [key: string]: unknown };
type AvailabilityPayload = SyncedSection & { vitals_status: string | null; vitals_unavailable_reason: string | null };
type InvestigationsAvailabilityPayload = SyncedSection & { investigations_status: string | null; investigations_unavailable_reason: string | null };

const props = defineProps<{
    caseId: string;
    userId: number;
    initialVitals: RowPayload[];
    initialInvestigations: RowPayload[];
    initialVitalsAvailability: AvailabilityPayload;
    initialInvestigationsAvailability: InvestigationsAvailabilityPayload;
}>();

const vitals = ref([...props.initialVitals]);
const investigations = ref([...props.initialInvestigations]);

const vitalsSync = useSectionSync<AvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'vitals_availability',
    endpoint: `/student/cases/${props.caseId}/vitals-availability`,
    initialPayload: props.initialVitalsAvailability,
});
const investigationsSync = useSectionSync<InvestigationsAvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'investigations_availability',
    endpoint: `/student/cases/${props.caseId}/investigations-availability`,
    initialPayload: props.initialInvestigationsAvailability,
});

const vitalsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'vitals',
    endpoint: `/student/cases/${props.caseId}/vitals`,
    responseKey: 'vital',
    emptyPayload: () => ({
        observation_type: null, value_numeric: null, value_text: null, value_systolic: null, value_diastolic: null,
        unit: null, observed_on: null, observed_at_time: null, source: null, note: null,
    }),
});
const investigationsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'investigations',
    endpoint: `/student/cases/${props.caseId}/investigations`,
    responseKey: 'investigation',
    emptyPayload: () => ({
        test_name: null, result_type: 'numeric', result_value: null, unit: null, unit_not_stated: false,
        reference_range: null, reference_range_not_provided: false, reported_flag: null,
        observed_on: null, observed_at_time: null, interpretation: null,
    }),
});

async function addVital() {
    vitals.value = [await vitalsCreate.queueCreate(), ...vitals.value];
}
async function addInvestigation() {
    investigations.value = [await investigationsCreate.queueCreate(), ...investigations.value];
}

function removeVital(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX)) void vitalsCreate.cancelQueuedCreate(id);
    vitals.value = vitals.value.filter((v) => v.id !== id);
}
function removeInvestigation(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX)) void investigationsCreate.cancelQueuedCreate(id);
    investigations.value = investigations.value.filter((i) => i.id !== id);
}

function handleReconnect() {
    void vitalsCreate.replayPending((localId, row) => {
        vitals.value = vitals.value.map((v) => (v.id === localId ? row : v));
    });
    void investigationsCreate.replayPending((localId, row) => {
        investigations.value = investigations.value.map((i) => (i.id === localId ? row : i));
    });
}

onMounted(() => {
    handleReconnect();
    window.addEventListener('online', handleReconnect);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', handleReconnect);
});

const vitalsStatusLabel = computed(() => ({
    saving: 'Saving…', server: 'Saved', device: 'Saved on this device', unsynced: 'Unsynced', failed: 'Sync failed', conflict: 'Conflict',
})[vitalsSync.state.value]);
</script>

<template>
    <section aria-labelledby="vitals-investigations-heading" class="space-y-8">
        <h2 id="vitals-investigations-heading" class="font-display text-lg text-[#0b2942] dark:text-white">Vitals &amp; Investigations</h2>

        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Vitals</h3>
                <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-test="vitals-availability-status">
                    <component :is="vitalsSync.state.value === 'saving' ? RefreshCw : vitalsSync.state.value === 'server' ? Check : vitalsSync.state.value === 'device' ? CloudOff : vitalsSync.state.value === 'unsynced' ? FileClock : AlertTriangle" class="size-3.5" />
                    {{ vitalsStatusLabel }}
                </span>
            </div>

            <fieldset class="mb-3">
                <legend class="sr-only">Vitals availability</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input v-model="vitalsSync.payload.value.vitals_status" type="radio" value="recorded" data-test="vitals-status-recorded" @change="vitalsSync.edit" />
                        Recorded below
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input v-model="vitalsSync.payload.value.vitals_status" type="radio" value="unavailable" data-test="vitals-status-unavailable" @change="vitalsSync.edit" />
                        Unavailable / not clinically relevant
                    </label>
                </div>
                <label v-if="vitalsSync.payload.value.vitals_status === 'unavailable'" class="mt-2 block text-sm">
                    <span class="sr-only">Reason vitals are unavailable</span>
                    <input
                        v-model="vitalsSync.payload.value.vitals_unavailable_reason"
                        type="text"
                        maxlength="1000"
                        placeholder="Reason"
                        data-test="vitals-unavailable-reason"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="vitalsSync.edit"
                    />
                </label>
            </fieldset>

            <div v-if="vitalsSync.payload.value.vitals_status !== 'unavailable'" class="space-y-3">
                <template v-for="vital in vitals" :key="vital.id">
                    <div v-if="vital.id.startsWith('local:')" class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600" data-test="vital-row-pending">
                        Waiting to sync…
                    </div>
                    <VitalRow v-else :case-id="caseId" :user-id="userId" :initial="vital as any" @removed="removeVital" />
                </template>
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addVital"><Plus class="size-4" /> Add vital</button>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-bold text-slate-700 dark:text-slate-200">Investigations</h3>
            <fieldset class="mb-3">
                <legend class="sr-only">Investigations availability</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input v-model="investigationsSync.payload.value.investigations_status" type="radio" value="recorded" data-test="investigations-status-recorded" @change="investigationsSync.edit" />
                        Recorded below
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input v-model="investigationsSync.payload.value.investigations_status" type="radio" value="unavailable" data-test="investigations-status-unavailable" @change="investigationsSync.edit" />
                        Unavailable / not clinically relevant
                    </label>
                </div>
                <label v-if="investigationsSync.payload.value.investigations_status === 'unavailable'" class="mt-2 block text-sm">
                    <span class="sr-only">Reason investigations are unavailable</span>
                    <input
                        v-model="investigationsSync.payload.value.investigations_unavailable_reason"
                        type="text"
                        maxlength="1000"
                        placeholder="Reason"
                        data-test="investigations-unavailable-reason"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="investigationsSync.edit"
                    />
                </label>
            </fieldset>

            <div v-if="investigationsSync.payload.value.investigations_status !== 'unavailable'" class="space-y-3">
                <template v-for="investigation in investigations" :key="investigation.id">
                    <div v-if="investigation.id.startsWith('local:')" class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600" data-test="investigation-row-pending">
                        Waiting to sync…
                    </div>
                    <InvestigationRow v-else :case-id="caseId" :user-id="userId" :initial="investigation as any" @removed="removeInvestigation" />
                </template>
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addInvestigation"><Plus class="size-4" /> Add investigation</button>
            </div>
        </div>
    </section>
</template>
```

Note: an "Add" tap while offline still shows a "Waiting to sync…" placeholder immediately (`queueCreate()` always writes the optimistic row synchronously) — the placeholder is deliberately not editable until it becomes a real row after sync, which keeps this task's scope to "creation is offline-capable" without also building offline-capable editing of a not-yet-existing server record (a materially larger feature the field catalogue does not ask for). Once `replayPending()` swaps the placeholder for the real row (same array index, new `id`), Vue's `:key="vital.id"` change remounts the position as a real `VitalRow.vue`, which then behaves exactly like any other row.

- [ ] **Step 9: Write `MedicationRow.vue` — brand, indication (or unclear), dosage form, frequency with expanded OD/BD/TDS/QID wording, start reference, medication context**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type MedicationPayload = SyncedSection & {
    id: string;
    medication_context: string | null;
    generic_name: string | null;
    brand_name: string | null;
    indication: string | null;
    indication_unclear: boolean;
    dose_amount: string | null;
    dose_unit: string | null;
    dosage_form: string | null;
    route: string | null;
    frequency: string | null;
    start_reference: string | null;
    status: string | null;
    stop_reference: string | null;
    prn_indication: string | null;
    notes: string | null;
};

const FREQUENCY_OPTIONS: { value: string; label: string }[] = [
    { value: 'OD', label: 'Once daily (OD)' },
    { value: 'BD', label: 'Twice daily (BD)' },
    { value: 'TDS', label: 'Three times daily (TDS)' },
    { value: 'QID', label: 'Four times daily (QID)' },
    { value: 'HS', label: 'At bedtime (HS)' },
    { value: 'STAT', label: 'Immediately, once (STAT)' },
];

const props = defineProps<{ caseId: string; userId: number; initial: MedicationPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, savedAt, edit, online, baseLockVersion, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace, adoptServerSnapshot } =
    useSectionSync<MedicationPayload>({
        userId: props.userId,
        resourceId: props.initial.id,
        sectionKey: 'medications',
        endpoint: `/student/cases/${props.caseId}/medications/${props.initial.id}`,
        initialPayload: props.initial,
    });

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

const deleteConflict = ref(false);
watch(savedAt, () => { deleteConflict.value = false; });

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/medications/${props.initial.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ base_lock_version: baseLockVersion.value }),
    });
    if (response.ok) {
        emit('removed', props.initial.id);
        return;
    }
    if (response.status === 409) {
        const body = (await response.json()) as { medication: MedicationPayload };
        await adoptServerSnapshot(body.medication);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`medication-row-${initial.id}`">
        <p v-if="deleteConflict" data-test="medication-delete-conflict" class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
            This row changed on the server after this device last saw it. The latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Generic name</span>
                <input v-model="payload.generic_name" type="text" maxlength="120" placeholder="Generic name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove medication" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Brand name (optional)</span>
                <input v-model="payload.brand_name" type="text" maxlength="120" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Medication context</span>
                <select v-model="payload.medication_context" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option :value="null">Select…</option>
                    <option value="chart">Chart (current)</option>
                    <option value="history">History (prior)</option>
                </select>
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Indication</span>
            <input v-model="payload.indication" type="text" maxlength="255" :disabled="payload.indication_unclear" data-test="medication-indication" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>
        <label class="mt-1 flex items-center gap-1.5 text-xs">
            <input v-model="payload.indication_unclear" type="checkbox" data-test="medication-indication-unclear" @change="edit" />
            Indication unclear
        </label>

        <div class="mt-2 grid grid-cols-3 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Dose</span>
                <input v-model="payload.dose_amount" type="text" maxlength="30" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Dosage form</span>
                <input v-model="payload.dosage_form" type="text" maxlength="30" placeholder="Tablet, injection…" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Route</span>
                <input v-model="payload.route" type="text" maxlength="30" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Frequency</span>
            <select v-model="payload.frequency" data-test="medication-frequency" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                <option :value="null">Select…</option>
                <option v-for="option in FREQUENCY_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                <option value="OTHER">Other</option>
            </select>
        </label>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Start day/date (optional)</span>
                <input v-model="payload.start_reference" type="text" maxlength="30" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Status</span>
                <select v-model="payload.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option value="active">Active</option>
                    <option value="stopped">Stopped</option>
                    <option value="on_hold">On hold</option>
                    <option value="completed">Completed</option>
                    <option value="prn">PRN</option>
                </select>
            </label>
        </div>

        <label
            v-if="payload.status === 'stopped' || payload.status === 'completed'"
            class="mt-2 block text-sm"
        >
            <span class="mb-1 block text-xs text-slate-500">Stop day/date</span>
            <input v-model="payload.stop_reference" type="text" maxlength="30" data-test="medication-stop-reference" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>
        <label v-if="payload.status === 'prn'" class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">PRN indication</span>
            <input v-model="payload.prn_indication" type="text" maxlength="120" data-test="medication-prn-indication" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Administration instructions/notes</span>
            <textarea v-model="payload.notes" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.notes" />
        </label>

        <section v-if="conflict" data-test="medication-conflict" class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900">
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

- [ ] **Step 10: Write `MedicationChartSection.vue` — offline-capable add, "no current medicines" explanation field**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import { LOCAL_ROW_PREFIX, useRepeatableRowCreate } from '@/composables/useRepeatableRowCreate';
import MedicationRow from './MedicationRow.vue';

type RowPayload = SyncedSection & { id: string; [key: string]: unknown };
type AvailabilityPayload = SyncedSection & { medication_chart_status: string | null; medication_chart_none_reason: string | null };

const props = defineProps<{
    caseId: string;
    userId: number;
    initialMedications: RowPayload[];
    initialAvailability: AvailabilityPayload;
}>();

const medications = ref([...props.initialMedications]);

const availabilitySync = useSectionSync<AvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'medication_chart_availability',
    endpoint: `/student/cases/${props.caseId}/medication-chart-availability`,
    initialPayload: props.initialAvailability,
});

const medicationsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'medications',
    endpoint: `/student/cases/${props.caseId}/medications`,
    responseKey: 'medication',
    emptyPayload: () => ({
        medication_context: null, generic_name: null, brand_name: null, indication: null, indication_unclear: false,
        dose_amount: null, dose_unit: null, dosage_form: null, route: null, frequency: null,
        start_reference: null, status: 'active', stop_reference: null, prn_indication: null, notes: null,
    }),
});

async function addMedication() {
    medications.value = [await medicationsCreate.queueCreate(), ...medications.value];
}
function removeMedication(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX)) void medicationsCreate.cancelQueuedCreate(id);
    medications.value = medications.value.filter((m) => m.id !== id);
}

function handleReconnect() {
    void medicationsCreate.replayPending((localId, row) => {
        medications.value = medications.value.map((m) => (m.id === localId ? row : m));
    });
}
onMounted(() => {
    handleReconnect();
    window.addEventListener('online', handleReconnect);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', handleReconnect);
});

const statusLabel = computed(() => ({
    saving: 'Saving…', server: 'Saved', device: 'Saved on this device', unsynced: 'Unsynced', failed: 'Sync failed', conflict: 'Conflict',
})[availabilitySync.state.value]);
</script>

<template>
    <section aria-labelledby="medication-chart-heading" class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 id="medication-chart-heading" class="font-display text-lg text-[#0b2942] dark:text-white">Medication Chart</h2>
            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-test="medication-chart-status">
                <component :is="availabilitySync.state.value === 'saving' ? RefreshCw : availabilitySync.state.value === 'server' ? Check : availabilitySync.state.value === 'device' ? CloudOff : availabilitySync.state.value === 'unsynced' ? FileClock : AlertTriangle" class="size-3.5" />
                {{ statusLabel }}
            </span>
        </div>

        <fieldset>
            <legend class="sr-only">Medication chart availability</legend>
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex items-center gap-1.5">
                    <input v-model="availabilitySync.payload.value.medication_chart_status" type="radio" value="documented" data-test="medication-chart-status-documented" @change="availabilitySync.edit" />
                    Medicines documented below
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="availabilitySync.payload.value.medication_chart_status" type="radio" value="none_documented" data-test="medication-chart-status-none" @change="availabilitySync.edit" />
                    No current medicines documented
                </label>
            </div>
            <label v-if="availabilitySync.payload.value.medication_chart_status === 'none_documented'" class="mt-2 block text-sm">
                <span class="sr-only">Explanation (optional)</span>
                <input
                    v-model="availabilitySync.payload.value.medication_chart_none_reason"
                    type="text"
                    maxlength="1000"
                    placeholder="Explanation (optional)"
                    data-test="medication-chart-none-reason"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="availabilitySync.edit"
                />
            </label>
        </fieldset>

        <div v-if="availabilitySync.payload.value.medication_chart_status !== 'none_documented'" class="space-y-3">
            <template v-for="medication in medications" :key="medication.id">
                <div v-if="medication.id.startsWith('local:')" class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600" data-test="medication-row-pending">
                    Waiting to sync…
                </div>
                <MedicationRow v-else :case-id="caseId" :user-id="userId" :initial="medication as any" @removed="removeMedication" />
            </template>
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addMedication"><Plus class="size-4" /> Add medicine</button>
        </div>
    </section>
</template>
```

- [ ] **Step 11: Wire both sections into `CaseEditor.vue`**

In `resources/js/pages/student/CaseEditor.vue`:

Add imports:

```typescript
import VitalsInvestigationsSection from './case-editor/VitalsInvestigationsSection.vue';
import MedicationChartSection from './case-editor/MedicationChartSection.vue';
```

Extend the props type:

```typescript
const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    userId: number;
    context: Record<string, unknown> & { lock_version: number; updated_at: string };
    clinicalProfile: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;
    vitals: (Record<string, unknown> & { id: string })[];
    investigations: (Record<string, unknown> & { id: string })[];
    medications: (Record<string, unknown> & { id: string })[];
}>();
```

Update the `sections` array's third and fourth entries to `available: true`:

```typescript
    { id: 'vitals_investigations', label: 'Vitals & Investigations', available: true },
    { id: 'medication_chart', label: 'Medication Chart', available: true },
```

Add the two new branches inside the section-content `<div>`, after the `HistoryDiagnosisSection` branch — each availability toggle now reads its **own** lock column (`context.vitals_availability_lock_version` etc., added by Task 5 Step 3 above) rather than `context.lock_version`, which per Slice 2A Task 2 is Case Profile's lock alone:

```vue
            <VitalsInvestigationsSection
                v-else-if="activeSection.id === 'vitals_investigations'"
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial-vitals="vitals as any"
                :initial-investigations="investigations as any"
                :initial-vitals-availability="{
                    vitals_status: context.vitals_status,
                    vitals_unavailable_reason: context.vitals_unavailable_reason,
                    lock_version: context.vitals_availability_lock_version,
                    updated_at: context.updated_at,
                } as any"
                :initial-investigations-availability="{
                    investigations_status: context.investigations_status,
                    investigations_unavailable_reason: context.investigations_unavailable_reason,
                    lock_version: context.investigations_availability_lock_version,
                    updated_at: context.updated_at,
                } as any"
            />
            <MedicationChartSection
                v-else-if="activeSection.id === 'medication_chart'"
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial-medications="medications as any"
                :initial-availability="{
                    medication_chart_status: context.medication_chart_status,
                    medication_chart_none_reason: context.medication_chart_none_reason,
                    lock_version: context.medication_chart_availability_lock_version,
                    updated_at: context.updated_at,
                } as any"
            />
```

Because each toggle now has its own independent lock column (Slice 2A Task 2), editing Case Profile and toggling "vitals unavailable" in quick succession no longer produces a false 409 the way it would have against a single shared `context.lock_version` — each `useSectionSync` instance tracks the lock column that actually belongs to its own section.

- [ ] **Step 12: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 13: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 216 previous passed + 1 new (the extended `CaseEditorPageTest` method) — 217 passed / 2 skipped (219 total).

- [ ] **Step 14: Commit**

```bash
git add resources/js/composables/useRepeatableRowCreate.ts resources/js/pages/student/case-editor/VitalRow.vue resources/js/pages/student/case-editor/InvestigationRow.vue resources/js/pages/student/case-editor/VitalsInvestigationsSection.vue resources/js/pages/student/case-editor/MedicationRow.vue resources/js/pages/student/case-editor/MedicationChartSection.vue resources/js/pages/student/CaseEditor.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git commit -m "feat: wire Vitals & Investigations and Medication Chart into the editor with offline-capable row creation and per-row conflict resolution"
```

---

## Task 6: Authorization and institution-isolation tests

**Files:**
- Create: `tests/Feature/RepeatableRowAuthorizationTest.php`

**Interfaces:**
- Consumes: `CaseVitalController`, `CaseInvestigationController`, `CaseMedicationController` (Tasks 2–4). Closes the cross-institution and post-submission gaps not already covered by each task's own tests, mirroring Slice 2A Task 7's pattern.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\MedicationStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
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

class RepeatableRowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_gets_404_not_403_on_every_row_endpoint(): void
    {
        [, $student, $case] = $this->makeAssignedCase();
        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->postJson("/student/cases/{$case->id}/vitals", ['client_operation_id' => (string) Str::uuid()])->assertNotFound();
        $this->putJson("/student/cases/{$case->id}/vitals/{$vital->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertNotFound();
        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}")->assertNotFound();
    }

    public function test_the_owning_student_cannot_create_edit_or_delete_rows_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'test_name' => 'Sodium', 'result_type' => 'numeric', 'result_value' => '140', 'recorded_by' => $student->id,
        ]);
        $this->actingAs($student);

        $this->postJson("/student/cases/{$case->id}/investigations", ['client_operation_id' => (string) Str::uuid()])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/investigations/{$investigation->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}")->assertForbidden();
    }

    public function test_assigned_faculty_cannot_create_edit_or_delete_medication_rows(): void
    {
        [, $student, $case, $faculty] = $this->makeAssignedCase();
        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin', 'status' => MedicationStatus::Active->value, 'recorded_by' => $student->id,
        ]);
        $this->actingAs($faculty);

        $this->postJson("/student/cases/{$case->id}/medications", ['client_operation_id' => (string) Str::uuid(), 'generic_name' => 'x', 'status' => MedicationStatus::Active->value])->assertForbidden();
        $this->putJson("/student/cases/{$case->id}/medications/{$medication->id}", ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0])->assertForbidden();
        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}")->assertForbidden();
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

Run (PowerShell): `php artisan test --filter=RepeatableRowAuthorizationTest`
Expected: PASS (3 tests).

- [ ] **Step 3: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 217 previous passed + 3 new — 220 passed / 2 skipped (222 total).

- [ ] **Step 4: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/RepeatableRowAuthorizationTest.php
git commit -m "test: cover cross-institution, post-submission and faculty-read-only access for repeatable rows"
```

---

## Task 7: Manual device verification (phone / tablet / desktop)

**Revision:** Adds an explicit offline-row-creation pass (the feature that changed most in this revision) and corrects the offline-refresh script the same way Slice 2A Task 8 was corrected — verifying reconnect-recovery, not offline hard-refresh.

**Files:** None (verification only).

**Interfaces:** None.

Same rationale and tooling as Slice 2A Task 8 (no committed Playwright specs for authenticated flows in this repo; manual/interactive browser verification against the local dev server).

- [ ] **Step 1: Phone viewport (390×844)**

Open the case editor, switch to "Vitals & Investigations". Confirm:
- Tapping "Add vital" with the device online immediately shows a "Waiting to sync…" placeholder that becomes a real, editable row within about a second.
- Selecting "Blood pressure" as the observation type shows paired Systolic/Diastolic fields instead of a single value field; saving with only one of the two filled in shows a validation failure surfaced as the row's status turning to "Sync failed".
- Selecting "Oxygen saturation" and entering a value over 100 fails to save.
- Marking Vitals "Unavailable" while a vital row still exists is rejected (the toggle's status shows "Sync failed" or the request visibly fails) — remove the row first, then the toggle succeeds.
- Toggling the device offline (DevTools) and tapping "Add investigation" still shows the placeholder immediately (queued locally); toggling back online causes the placeholder to become a real row without the student doing anything else.
- Removing an already-synced row while offline shows the disabled state with the "Reconnect to remove this row" tooltip/title; removing a still-pending ("Waiting to sync…") row works offline (it's a local cancellation, not a server call).

Switch to "Medication Chart". Confirm:
- Selecting a medication's status as "Stopped" or "Completed" reveals the stop-date field; selecting "PRN" reveals the PRN-indication field.
- The Frequency dropdown shows expanded wording ("Once daily (OD)", etc.), not bare abbreviations.
- Checking "Indication unclear" disables and clears the requirement on the Indication field.
- Typing a 10-digit number into a medication's Notes field shows the de-identification warning.
- "No current medicines documented" is rejected while medication rows exist; once accepted (after removing rows), an optional explanation field appears.

- [ ] **Step 2: Offline recovery and conflict — row edits and row creation**

Using DevTools offline toggle:
1. Edit an existing vital's unit field while offline; confirm "Saved on this device", then reconnect and confirm it syncs (this is the same field-edit path Slice 2A verified, now against a per-row resource).
2. Add a new investigation while offline; confirm the "Waiting to sync…" placeholder persists across a section switch (back to Vitals and back to Investigations) while still offline, then reconnect and confirm it becomes a real, editable row without a page reload.
3. Simulate a two-tab conflict on the same medication row (edit dose in Tab A and let it save, then edit route in Tab B using the stale `lock_version`) and confirm the conflict panel appears **inside that row** with the three resolution options, exactly as in Slice 2A Task 8 Step 3 — not merely an icon.

- [ ] **Step 3: Tablet (820×1180) and Desktop (1280×900, Chrome/Edge)**

Repeat Step 1's pass criteria at both viewports.

- [ ] **Step 4: Record the result**

Note the verification outcome in the pull-request description when 2B is opened for review (do not update `PROJECT_STATE.md` yet — Slice 2 as a whole is still active until 2C also lands, per the Slice 1 precedent of only updating `PROJECT_STATE.md` on full-gate acceptance).

---

## Self-Review Notes

- **Spec coverage:** Requirement 1 (explicit unavailable states) — UI lands here (Tasks 2–5), now enforced two-directionally against actual row existence, not just visually plausible. Requirement 2 (mobile section editor) — extended in Task 5. Requirement 3 (repeatable rows) — Tasks 2–5, all three resource types, now offline-capable at creation. Requirement 4 (partial autosave) — every `Update*Request` in Tasks 2–4 uses `'sometimes'`; every `Store*Request` makes every clinical field optional so a bare "Add" never fails. Requirement 5 (outbox/idempotency/locking/conflict) — reused from Slice 2A for row *edits*; row *creation* gets its own idempotency via `SectionSyncService::create()` (Task 2, now class-checked against replay) and its own offline queue via `useRepeatableRowCreate` (Task 5); every row gets the same three-way conflict panel Case Profile has. Requirement 8 (authorization tests) — Task 6. Requirement 9 (device verification) — Task 7, including the offline row-creation path and the corrected offline-refresh script. Requirement 6 (conditional allergy/ADR) and requirement 7 (de-identification, already delivered in 2A) — the Medication Chart's and Investigations' `DeidentificationNotice` reuse in Task 5 extends requirement 7's coverage to this slice's free-text fields; ADR proper remains Slice 2C.
- **Placeholder scan:** No task defers real logic; the section-status-vs-rows consistency rule is now actually enforced (both directions), not merely documented as a boundary. The "pending row" placeholder in the frontend is a real, minimal, explicitly-scoped UI state (not editable until synced), with the scope boundary stated directly in Task 5.
- **Type consistency:** `SectionSyncService::create()`'s and `delete()`'s return shapes both match `sync()`'s (`array{status, httpStatus, model}`); none of the three accept a caller-supplied model class, all three derive it from `modelClassForSection()`. Every row payload method (`CaseVitalController::payload()`, etc.) returns the same field set the corresponding `Update*Request` accepts, so a synced row and a freshly created row are interchangeable on the frontend. Every `syncAvailability()` method returns its section's *own* lock column under the JSON key `lock_version`, matching the `SyncedSection` type `useSectionSync` expects — verified explicitly in Task 2's controller note and re-used identically in Tasks 3 and 4. Every request class that defines `withValidator()` calls `$this->rejectUnknownFields($validator)` as its first statement — verified per class in Tasks 2–4 rather than assumed from the trait alone.
- **Review Focus coverage:** cross-case row edit, duplicate/misdirected replay, stale-lock-with-no-resolution-path, deletion bypassing concurrency/audit, status/row desynchronization, and offline-creation loss/duplication each have a named test in the task that owns the relevant code, plus a manual-verification step in Task 7 for the parts only a real browser can exercise.
