# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2B (Vitals, Investigations, Medication Chart) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution, chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking. **Depends on Slice 2A being merged first** — this plan reuses `App\Contracts\Syncable`, `App\Models\Concerns\SyncsWithLockVersion`, `App\Services\SectionSyncService`, `App\Http\Requests\Concerns\HasSyncEnvelope`, `resources/js/lib/outboxStore.ts`, `resources/js/composables/useSectionSync.ts`, `resources/js/components/DeidentificationNotice.vue` and `resources/js/pages/student/CaseEditor.vue`, all built in [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md).

**Goal:** Add the three repeatable-row sections — Vitals & Investigations (one combined mobile-journey step per the field catalogue) and Medication Chart — with per-row optimistic-locking autosave, bounded add/remove, and the explicit "unavailable"/"no current medicines" toggles whose schema Slice 2A already added to `clinical_cases`.

**Architecture:** Each of the three repeatable resources (`CaseVital`, `CaseInvestigation`, `CaseMedication`) gets its own `lock_version` column and becomes `Syncable` exactly like `ClinicalCase` and `CaseClinicalProfile` did in Slice 2A — so **editing an existing row's fields reuses `SectionSyncService::sync()` and `useSectionSync` completely unchanged**, one composable instance per rendered row. What's new in this slice is row **creation** and **deletion**, which the single-resource model from 2A didn't need: `SectionSyncService` gains a `create()` method that gives idempotent-by-`client_operation_id` row creation the same replay-safety as `sync()`, and deletion is a plain authorized `DELETE` (a student can only delete a row belonging to a Draft/Returned case they own — no concurrent-edit conflict is possible for a delete). **Scope decision:** adding or removing a row requires connectivity; only *editing an existing row's fields* is offline-capable through the IndexedDB outbox. This matches the accepted SYNC-SPIKE-01 boundary recorded in `docs/SYNC_SPIKE_01_FINDINGS.md` ("Background Sync, automatic field-level merging... are not included" — this was never a fully offline-first app, only resilient autosave-with-recovery) and keeps this slice's scope to what the field catalogue actually requires ("bounded repeatable rows", "no optional child record until deliberate save") rather than inventing an offline row-creation queue nothing asked for. The three "unavailable"/"none documented" toggle fields live on `clinical_cases` (added in Slice 2A Task 1) and are synced by reusing `ClinicalCase`'s existing `Syncable` implementation with a new `section_key` per toggle — no new locking mechanism needed there either.

**Tech Stack:** Same as Slice 2A — Laravel 13, Eloquent, PHPUnit, SQLite (`:memory:`)/PostgreSQL; Inertia.js + Vue 3 + TypeScript, native `fetch` + IndexedDB.

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.3–4.5, Slice 2A plan (prerequisite infrastructure) at [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md).

## Global Constraints

All constraints from Slice 2A's Global Constraints apply unchanged (PowerShell for PHP/Composer/npm; no `Schema::table()->change()` — `doctrine/dbal` is not installed; regenerate and commit Wayfinder files with every route change; `lock_version` never mass-fillable; sync-capable models implement `Syncable` via `SyncsWithLockVersion` and are only ever mutated through `SectionSyncService`; no JS unit-test runner exists — frontend correctness is verified via `npm run types:check` plus the manual browser-verification task). In addition:

- A row's `clinical_case_id` must always be cross-checked against the `{case}` route segment before any read/write, even though `CaseVitalPolicy`/`CaseInvestigationPolicy`/`CaseMedicationPolicy` already scope by institution and ownership — those policies check that the row's **own** case is owned by the requesting student, not that it matches the specific case in the URL. A student could otherwise pass `case=A` in the URL while editing a row that actually belongs to their own `case=B`, silently mis-attributing the edit. Every row controller action calls `abort_unless($row->clinical_case_id === $case->id, 404)` immediately after loading the row.
- Row creation (`POST .../vitals`, `.../investigations`, `.../medications`) is idempotent by `client_operation_id` via `SectionSyncService::create()` (new in this slice), exactly as row/field edits are idempotent by `client_operation_id` via `SectionSyncService::sync()`. A double-submitted "Add row" tap (double-tap or a retried request) must never create two rows.
- Row-level `Update*Request` classes follow the same `'sometimes'`-partial pattern established in Slice 2A — a PUT to an existing row that only changes one field (e.g. just `note`) must not require or overwrite the others.
- Deleting a row is not offline-capable in this slice (see Architecture). The "Remove row" control in each row component must be disabled while `navigator.onLine` is `false`, with a visible reason, not silently queued.

## Review Focus

- **Cross-case row edit (IDOR-shaped correctness bug, not a tenant leak).** A request for `PUT /student/cases/{caseA}/vitals/{vitalBelongingToCaseB}` where both cases belong to the same authenticated student must be rejected with 404, not silently accepted because the ownership policy alone passes. Every row-editing task's test suite includes this exact cross-case scenario.
- **Duplicate row from a retried create.** Resubmitting the same `client_operation_id` for a row creation (simulating a flaky network retry) must return the **same** row, not create a second one. Each row-creation task's test replays the create request and asserts the row count.
- **Stale per-row `lock_version` overwriting a concurrent edit.** Two edits to the *same row* with the same stale `base_lock_version` must produce exactly one successful save and one 409 conflict, mirroring Slice 2A's `SectionSyncServiceTest` coverage but now proven against a *repeatable* resource rather than a singleton.
- **`medication_chart_status`/`vitals_status`/`investigations_status` desynchronized from actual rows.** These are independent boolean-ish toggles on `clinical_cases`, not derived from row counts — nothing in this slice's code enforces "if you say 'no current medicines' you cannot also have medication rows," because that consistency check is explicitly Slice 3's submission-completeness policy, not this slice's autosave layer. State this ownership boundary in each toggle's test docblock rather than silently adding unrequested enforcement now (which would duplicate/contradict Slice 3's owned validation policy).
- **Deleting a row while offline.** The remove button must be disabled offline with a visible reason rather than silently failing or silently queuing something this slice doesn't implement. Task 5's UI includes this, and Task 7's manual verification exercises it.

---

## Task 1: Per-row `lock_version` and `Syncable` for `CaseVital`, `CaseInvestigation`, `CaseMedication`

**Files:**
- Create: `database/migrations/2026_09_29_000000_add_lock_version_to_pharmd_repeatable_case_tables.php`
- Modify: `app/Models/CaseVital.php` (implement `Syncable`, add `lock_version` to fillable/casts)
- Modify: `app/Models/CaseInvestigation.php` (same)
- Modify: `app/Models/CaseMedication.php` (same)
- Test: `tests/Feature/PharmdRepeatableRowLockVersionTest.php`

**Interfaces:**
- Produces: `lock_version` (unsigned big integer, default 0) on `case_vitals`, `case_investigations`, `case_medications`. All three models implement `App\Contracts\Syncable` via `App\Models\Concerns\SyncsWithLockVersion` (from Slice 2A Task 2), consumed by Tasks 2–4's controllers.

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
            $this->assertSame(0, $row->getLockVersion());
            $row->applySyncedAttributes(['note' ?? 'notes' => null], 1);
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
Expected: FAIL — unknown column `lock_version` on `case_vitals`.

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
        foreach (['case_vitals', 'case_investigations', 'case_medications'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            });
        }
    }

    public function down(): void
    {
        foreach (['case_vitals', 'case_investigations', 'case_medications'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('lock_version');
            });
        }
    }
};
```

- [ ] **Step 4: Update the three models**

In each of `app/Models/CaseVital.php`, `app/Models/CaseInvestigation.php`, `app/Models/CaseMedication.php`, add the imports:

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

Change the class declaration (shown for `CaseVital`; apply the identical shape to the other two, keeping each model's existing `use BelongsToInstitution, HasUlids;` alongside the new trait):

```php
class CaseVital extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

Add `'lock_version'` to each model's `#[Fillable([...])]` array — **do not** do this; `lock_version` must stay out of every Fillable list (Slice 2A Global Constraint). Instead, leave the Fillable arrays exactly as Slice 1 left them; `SyncsWithLockVersion::applySyncedAttributes()` bumps it via `forceFill()`, which bypasses mass-assignment protection entirely by design.

- [ ] **Step 5: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdRepeatableRowLockVersionTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all Slice 2A tests (157 passed / 2 skipped) plus 2 new ones — 159 passed / 2 skipped.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_29_000000_add_lock_version_to_pharmd_repeatable_case_tables.php app/Models/CaseVital.php app/Models/CaseInvestigation.php app/Models/CaseMedication.php tests/Feature/PharmdRepeatableRowLockVersionTest.php
git commit -m "feat: add per-row lock_version and Syncable to the repeatable Pharm.D case tables"
```

---

## Task 2: `SectionSyncService::create()` and the Vitals + vitals-availability backend

**Files:**
- Modify: `app/Services/SectionSyncService.php` (add `create()` method)
- Modify: `app/Http/Requests/Student/StoreCaseVitalRequest.php` (add `client_operation_id`)
- Create: `app/Http/Requests/Student/UpdateCaseVitalRequest.php`
- Create: `app/Http/Requests/Student/UpdateVitalsAvailabilityRequest.php`
- Create: `app/Http/Controllers/Student/CaseVitalController.php`
- Modify: `routes/web.php` (add vitals routes)
- Test: `tests/Feature/CaseVitalSyncTest.php`

**Interfaces:**
- Produces: `SectionSyncService::create(\Closure $factory, User $user, string $sectionKey, string $clientOperationId): array{status: string, httpStatus: int, model: Model&Syncable}` (reused by Tasks 3 and 4). `POST /student/cases/{case}/vitals`, `PUT /student/cases/{case}/vitals/{vital}`, `DELETE /student/cases/{case}/vitals/{vital}`, `PUT /student/cases/{case}/vitals-availability`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseVitalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_can_create_a_vital_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->postJson("/student/cases/{$case->id}/vitals", [
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'blood_pressure',
            'value_text' => '120/80',
            'unit' => 'mmHg',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('vital.observation_type', 'blood_pressure');
        $this->assertCount(1, $case->fresh()->vitals);
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

    public function test_owning_student_can_delete_a_vital_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $vital = $this->makeVital($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/vitals/{$vital->id}")->assertNoContent();

        $this->assertCount(0, $case->fresh()->vitals);
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

- [ ] **Step 3: Add `create()` to `SectionSyncService`**

In `app/Services/SectionSyncService.php`, add this public method (after `sync()`):

```php
    /**
     * @param  \Closure(): (Model&Syncable)  $factory
     * @return array{status: string, httpStatus: int, model: Model&Syncable}
     */
    public function create(\Closure $factory, User $user, string $sectionKey, string $clientOperationId): array
    {
        return DB::transaction(function () use ($factory, $user, $sectionKey, $clientOperationId): array {
            $existing = SyncOperation::query()
                ->where('user_id', $user->id)
                ->where('client_operation_id', $clientOperationId)
                ->first();

            if ($existing !== null) {
                /** @var Model&Syncable $model */
                $model = ($existing->syncable_type)::query()->withoutGlobalScopes()->findOrFail($existing->syncable_id);

                return ['status' => $existing->result_status, 'httpStatus' => 201, 'model' => $model];
            }

            /** @var Model&Syncable $model */
            $model = $factory();

            $this->recordOperation($user, $model, $sectionKey, $clientOperationId, 0, 'saved');

            return ['status' => 'saved', 'httpStatus' => 201, 'model' => $model];
        });
    }
```

Add `use App\Models\User;` and `use Illuminate\Database\Eloquent\Model;` if not already imported (both are already imported by Task 2 of Slice 2A).

- [ ] **Step 4: Add `client_operation_id` to `StoreCaseVitalRequest`**

In `app/Http/Requests/Student/StoreCaseVitalRequest.php`, add as the first entry of the `rules()` array:

```php
            'client_operation_id' => ['required', 'uuid'],
```

- [ ] **Step 5: Write `UpdateCaseVitalRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseVitalRequest extends FormRequest
{
    use HasSyncEnvelope;

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
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'source' => ['sometimes', 'nullable', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 6: Write `UpdateVitalsAvailabilityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVitalsAvailabilityRequest extends FormRequest
{
    use HasSyncEnvelope;

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
}
```

- [ ] **Step 7: Write `CaseVitalController`**

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
            fn () => CaseVital::query()->create([
                ...$data,
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'recorded_by' => $request->user()->id,
            ]),
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

    public function destroy(ClinicalCase $case, CaseVital $vital): Response
    {
        Gate::authorize('update', $vital);
        abort_unless($vital->clinical_case_id === $case->id, 404);

        $vital->delete();

        return response()->noContent();
    }

    public function syncAvailability(UpdateVitalsAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $case,
            $request->user(),
            'vitals_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['section' => [
            'vitals_status' => $result['model']->vitals_status,
            'vitals_unavailable_reason' => $result['model']->vitals_unavailable_reason,
            'lock_version' => $result['model']->lock_version,
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
Expected: PASS (8 tests).

- [ ] **Step 11: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 8 new tests pass (167 passed / 2 skipped).

- [ ] **Step 12: Commit**

```bash
git add app/Services/SectionSyncService.php app/Http/Requests/Student/StoreCaseVitalRequest.php app/Http/Requests/Student/UpdateCaseVitalRequest.php app/Http/Requests/Student/UpdateVitalsAvailabilityRequest.php app/Http/Controllers/Student/CaseVitalController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseVitalSyncTest.php
git commit -m "feat: add idempotent row creation to SectionSyncService and wire up the Vitals backend"
```

---

## Task 3: Investigations backend

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseInvestigationRequest.php` (add `client_operation_id`)
- Create: `app/Http/Requests/Student/UpdateCaseInvestigationRequest.php`
- Create: `app/Http/Requests/Student/UpdateInvestigationsAvailabilityRequest.php`
- Create: `app/Http/Controllers/Student/CaseInvestigationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CaseInvestigationSyncTest.php`

**Interfaces:**
- Consumes: `SectionSyncService::create()`/`sync()` (Task 2). Identical shape to Task 2's Vitals backend — see that task for the full rationale; this task is deliberately terse since the pattern is now established.
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

    public function test_owning_student_can_create_an_investigation_row(): void
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

    public function test_owning_student_can_delete_an_investigation_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $investigation = $this->makeInvestigation($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/investigations/{$investigation->id}")->assertNoContent();

        $this->assertCount(0, $case->fresh()->investigations);
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

- [ ] **Step 3: Add `client_operation_id` to `StoreCaseInvestigationRequest`**

Add as the first `rules()` entry: `'client_operation_id' => ['required', 'uuid'],`

- [ ] **Step 4: Write `UpdateCaseInvestigationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseInvestigationRequest extends FormRequest
{
    use HasSyncEnvelope;

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
            'reference_range' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reported_flag' => ['sometimes', 'nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'interpretation' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 5: Write `UpdateInvestigationsAvailabilityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestigationsAvailabilityRequest extends FormRequest
{
    use HasSyncEnvelope;

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
            fn () => CaseInvestigation::query()->create([
                ...$data,
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'recorded_by' => $request->user()->id,
            ]),
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

    public function destroy(ClinicalCase $case, CaseInvestigation $investigation): Response
    {
        Gate::authorize('update', $investigation);
        abort_unless($investigation->clinical_case_id === $case->id, 404);

        $investigation->delete();

        return response()->noContent();
    }

    public function syncAvailability(UpdateInvestigationsAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $case,
            $request->user(),
            'investigations_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['section' => [
            'investigations_status' => $result['model']->investigations_status,
            'investigations_unavailable_reason' => $result['model']->investigations_unavailable_reason,
            'lock_version' => $result['model']->lock_version,
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
            'reference_range' => $investigation->reference_range,
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
Expected: PASS (8 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 8 new tests pass (175 passed / 2 skipped).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseInvestigationRequest.php app/Http/Requests/Student/UpdateCaseInvestigationRequest.php app/Http/Requests/Student/UpdateInvestigationsAvailabilityRequest.php app/Http/Controllers/Student/CaseInvestigationController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseInvestigationSyncTest.php
git commit -m "feat: wire up the Investigations backend"
```

---

## Task 4: Medication Chart backend (with conditional stop-date and PRN validation)

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseMedicationRequest.php` (add `client_operation_id`, fix the missing `stop_reference` conditional)
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

    public function test_owning_student_can_create_a_medication_row(): void
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

    public function test_owning_student_can_delete_a_medication_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $medication = $this->makeMedication($case, $student);

        $this->deleteJson("/student/cases/{$case->id}/medications/{$medication->id}")->assertNoContent();

        $this->assertCount(0, $case->fresh()->medications);
    }

    public function test_owning_student_can_mark_no_current_medicines_documented(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/medication-chart-availability", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'medication_chart_status' => 'none_documented',
        ]);

        $response->assertOk();
        $this->assertSame('none_documented', $case->fresh()->medication_chart_status);
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
Expected: FAIL — routes not found; the two `stop_reference` tests will also fail for the wrong reason (Slice 1's `StoreCaseMedicationRequest` never validates it) until Step 3.

- [ ] **Step 3: Fix `StoreCaseMedicationRequest`**

Replace the full contents of `app/Http/Requests/Student/StoreCaseMedicationRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseMedicationRequest extends FormRequest
{
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
            'generic_name' => ['required', 'string', 'max:120'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'indication' => ['nullable', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'string', 'max:30'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'dosage_form' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency' => ['nullable', 'string', 'max:60'],
            'start_reference' => ['nullable', 'string', 'max:30'],
            'stop_reference' => ['nullable', 'required_if:status,stopped', 'required_if:status,completed', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_map(fn (MedicationStatus $s): string => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 4: Write `UpdateCaseMedicationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\MedicationStatus;
use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseMedicationRequest extends FormRequest
{
    use HasSyncEnvelope;

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
            'indication' => ['sometimes', 'nullable', 'string', 'max:255'],
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
}
```

- [ ] **Step 5: Write `UpdateMedicationChartAvailabilityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicationChartAvailabilityRequest extends FormRequest
{
    use HasSyncEnvelope;

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
        ];
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
            fn () => CaseMedication::query()->create([
                ...$data,
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'recorded_by' => $request->user()->id,
            ]),
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

    public function destroy(ClinicalCase $case, CaseMedication $medication): Response
    {
        Gate::authorize('update', $medication);
        abort_unless($medication->clinical_case_id === $case->id, 404);

        $medication->delete();

        return response()->noContent();
    }

    public function syncAvailability(UpdateMedicationChartAvailabilityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $case,
            $request->user(),
            'medication_chart_availability',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['section' => [
            'medication_chart_status' => $result['model']->medication_chart_status,
            'lock_version' => $result['model']->lock_version,
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
Expected: PASS (9 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 9 new tests pass (184 passed / 2 skipped).

- [ ] **Step 11: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseMedicationRequest.php app/Http/Requests/Student/UpdateCaseMedicationRequest.php app/Http/Requests/Student/UpdateMedicationChartAvailabilityRequest.php app/Http/Controllers/Student/CaseMedicationController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CaseMedicationSyncTest.php
git commit -m "feat: wire up the Medication Chart backend and fix the missing stop_reference conditional"
```

---

## Task 5: Frontend — Vitals & Investigations and Medication Chart sections

**Files:**
- Create: `resources/js/pages/student/case-editor/VitalRow.vue`
- Create: `resources/js/pages/student/case-editor/InvestigationRow.vue`
- Create: `resources/js/pages/student/case-editor/VitalsInvestigationsSection.vue`
- Create: `resources/js/pages/student/case-editor/MedicationRow.vue`
- Create: `resources/js/pages/student/case-editor/MedicationChartSection.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue` (wire in both sections)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass vitals/investigations/medications + availability props)
- Test: `tests/Feature/CaseEditorPageTest.php` (extend)

**Interfaces:**
- Consumes: `useSectionSync` (Slice 2A Task 3), `DeidentificationNotice.vue` (Slice 2A Task 5), the four controllers from Tasks 2–4.

- [ ] **Step 1: Write the failing test (extend `CaseEditorPageTest`)**

Append to `tests/Feature/CaseEditorPageTest.php`, inside the class, after the existing `test_editor_reflects_an_existing_profile` method:

```php
    public function test_editor_page_includes_vitals_investigations_and_medications(): void
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
            ->where('context.vitals_status', null));
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

Add five keys to the `context` array (after `'pregnancy_lactation_status' => $case->pregnancy_lactation_status,`):

```php
                'vitals_status' => $case->vitals_status,
                'vitals_unavailable_reason' => $case->vitals_unavailable_reason,
                'investigations_status' => $case->investigations_status,
                'investigations_unavailable_reason' => $case->investigations_unavailable_reason,
                'medication_chart_status' => $case->medication_chart_status,
```

Add three new top-level props to the `Inertia::render(...)` array (after `'clinicalProfile' => ...,`):

```php
            'vitals' => $case->vitals()->orderByDesc('created_at')->get()->map(fn (CaseVital $vital): array => [
                'id' => $vital->id,
                'observation_type' => $vital->observation_type,
                'value_numeric' => $vital->value_numeric,
                'value_text' => $vital->value_text,
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
                'reference_range' => $investigation->reference_range,
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

- [ ] **Step 5: Write `VitalRow.vue`**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

type VitalPayload = SyncedSection & {
    id: string;
    observation_type: string;
    value_numeric: string | null;
    value_text: string | null;
    unit: string | null;
    observed_on: string | null;
    note: string | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: VitalPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, edit, online } = useSectionSync<VitalPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'vitals',
    endpoint: `/student/cases/${props.caseId}/vitals/${props.initial.id}`,
    initialPayload: props.initial,
});

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/vitals/${props.initial.id}`, {
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
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`vital-row-${initial.id}`">
        <div class="flex items-center justify-between gap-2">
            <input v-model="payload.observation_type" type="text" maxlength="40" placeholder="Observation type" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove vital" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            <input v-model="payload.value_numeric" type="text" placeholder="Value" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <input v-model="payload.unit" type="text" maxlength="20" placeholder="Unit" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <input v-model="payload.observed_on" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </div>
    </div>
</template>
```

- [ ] **Step 6: Write `InvestigationRow.vue`**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

type InvestigationPayload = SyncedSection & {
    id: string;
    test_name: string;
    result_type: string;
    result_value: string;
    unit: string | null;
    reference_range: string | null;
    reported_flag: string | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: InvestigationPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, edit, online } = useSectionSync<InvestigationPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'investigations',
    endpoint: `/student/cases/${props.caseId}/investigations/${props.initial.id}`,
    initialPayload: props.initial,
});

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/investigations/${props.initial.id}`, {
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
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`investigation-row-${initial.id}`">
        <div class="flex items-center justify-between gap-2">
            <input v-model="payload.test_name" type="text" maxlength="120" placeholder="Test name" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove investigation" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <select v-model="payload.result_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                <option value="numeric">Numeric</option>
                <option value="qualitative">Qualitative</option>
                <option value="narrative">Narrative</option>
            </select>
            <input v-model="payload.result_value" type="text" maxlength="255" placeholder="Result" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <input v-model="payload.unit" type="text" maxlength="20" placeholder="Unit (or leave blank for 'not stated')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <select v-model="payload.reported_flag" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                <option :value="null">Not stated</option>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
    </div>
</template>
```

- [ ] **Step 7: Write `VitalsInvestigationsSection.vue`**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
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

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function addVital() {
    const response = await fetch(`/student/cases/${props.caseId}/vitals`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ client_operation_id: crypto.randomUUID(), observation_type: '' }),
    });
    if (response.ok) {
        const body = (await response.json()) as { vital: RowPayload };
        vitals.value = [body.vital, ...vitals.value];
    }
}

async function addInvestigation() {
    const response = await fetch(`/student/cases/${props.caseId}/investigations`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ client_operation_id: crypto.randomUUID(), test_name: '', result_type: 'numeric', result_value: '' }),
    });
    if (response.ok) {
        const body = (await response.json()) as { investigation: RowPayload };
        investigations.value = [body.investigation, ...investigations.value];
    }
}

function removeVital(id: string) {
    vitals.value = vitals.value.filter((v) => v.id !== id);
}
function removeInvestigation(id: string) {
    investigations.value = investigations.value.filter((i) => i.id !== id);
}

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
                <input
                    v-if="vitalsSync.payload.value.vitals_status === 'unavailable'"
                    v-model="vitalsSync.payload.value.vitals_unavailable_reason"
                    type="text"
                    maxlength="1000"
                    placeholder="Reason"
                    data-test="vitals-unavailable-reason"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="vitalsSync.edit"
                />
            </fieldset>

            <div v-if="vitalsSync.payload.value.vitals_status !== 'unavailable'" class="space-y-3">
                <VitalRow v-for="vital in vitals" :key="vital.id" :case-id="caseId" :user-id="userId" :initial="vital as any" @removed="removeVital" />
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addVital"><Plus class="size-4" /> Add vital</button>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-bold text-slate-700 dark:text-slate-200">Investigations</h3>
            <fieldset class="mb-3">
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
                <input
                    v-if="investigationsSync.payload.value.investigations_status === 'unavailable'"
                    v-model="investigationsSync.payload.value.investigations_unavailable_reason"
                    type="text"
                    maxlength="1000"
                    placeholder="Reason"
                    data-test="investigations-unavailable-reason"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="investigationsSync.edit"
                />
            </fieldset>

            <div v-if="investigationsSync.payload.value.investigations_status !== 'unavailable'" class="space-y-3">
                <InvestigationRow v-for="investigation in investigations" :key="investigation.id" :case-id="caseId" :user-id="userId" :initial="investigation as any" @removed="removeInvestigation" />
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addInvestigation"><Plus class="size-4" /> Add investigation</button>
            </div>
        </div>
    </section>
</template>
```

- [ ] **Step 8: Write `MedicationRow.vue`**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type MedicationPayload = SyncedSection & {
    id: string;
    generic_name: string;
    brand_name: string | null;
    dose_amount: string | null;
    dose_unit: string | null;
    route: string | null;
    frequency: string | null;
    status: string;
    stop_reference: string | null;
    prn_indication: string | null;
    notes: string | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: MedicationPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, edit, online } = useSectionSync<MedicationPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'medications',
    endpoint: `/student/cases/${props.caseId}/medications/${props.initial.id}`,
    initialPayload: props.initial,
});

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

async function remove() {
    const response = await fetch(`/student/cases/${props.caseId}/medications/${props.initial.id}`, {
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
    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" :data-test="`medication-row-${initial.id}`">
        <div class="flex items-center justify-between gap-2">
            <input v-model="payload.generic_name" type="text" maxlength="120" placeholder="Generic name" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <component :is="statusIcon" class="size-4 shrink-0 text-slate-400" :class="state === 'saving' ? 'animate-spin' : ''" />
            <button type="button" aria-label="Remove medication" :disabled="!online" class="rounded-xl border px-2 py-2 disabled:opacity-40" :title="!online ? 'Reconnect to remove this row' : ''" @click="remove">
                <Trash2 class="size-4" />
            </button>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            <input v-model="payload.dose_amount" type="text" maxlength="30" placeholder="Dose" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <input v-model="payload.route" type="text" maxlength="30" placeholder="Route" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <select v-model="payload.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="edit">
                <option value="active">Active</option>
                <option value="stopped">Stopped</option>
                <option value="on_hold">On hold</option>
                <option value="completed">Completed</option>
                <option value="prn">PRN</option>
            </select>
        </div>
        <input
            v-if="payload.status === 'stopped' || payload.status === 'completed'"
            v-model="payload.stop_reference"
            type="text"
            maxlength="30"
            placeholder="Stop day/date"
            data-test="medication-stop-reference"
            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
            @input="edit"
        />
        <input
            v-if="payload.status === 'prn'"
            v-model="payload.prn_indication"
            type="text"
            maxlength="120"
            placeholder="PRN indication"
            data-test="medication-prn-indication"
            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
            @input="edit"
        />
        <textarea v-model="payload.notes" rows="2" maxlength="1000" placeholder="Notes" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        <DeidentificationNotice :text="payload.notes" />
    </div>
</template>
```

- [ ] **Step 9: Write `MedicationChartSection.vue`**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import MedicationRow from './MedicationRow.vue';

type RowPayload = SyncedSection & { id: string; [key: string]: unknown };
type AvailabilityPayload = SyncedSection & { medication_chart_status: string | null };

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

async function addMedication() {
    const response = await fetch(`/student/cases/${props.caseId}/medications`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ client_operation_id: crypto.randomUUID(), generic_name: '', status: 'active' }),
    });
    if (response.ok) {
        const body = (await response.json()) as { medication: RowPayload };
        medications.value = [body.medication, ...medications.value];
    }
}

function removeMedication(id: string) {
    medications.value = medications.value.filter((m) => m.id !== id);
}

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
        </fieldset>

        <div v-if="availabilitySync.payload.value.medication_chart_status !== 'none_documented'" class="space-y-3">
            <MedicationRow v-for="medication in medications" :key="medication.id" :case-id="caseId" :user-id="userId" :initial="medication as any" @removed="removeMedication" />
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addMedication"><Plus class="size-4" /> Add medicine</button>
        </div>
    </section>
</template>
```

- [ ] **Step 10: Wire both sections into `CaseEditor.vue`**

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

Add the two new branches inside the section-content `<div>`, after the `HistoryDiagnosisSection` branch:

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
                    lock_version: context.lock_version,
                    updated_at: context.updated_at,
                } as any"
                :initial-investigations-availability="{
                    investigations_status: context.investigations_status,
                    investigations_unavailable_reason: context.investigations_unavailable_reason,
                    lock_version: context.lock_version,
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
                    lock_version: context.lock_version,
                    updated_at: context.updated_at,
                } as any"
            />
```

Note: the vitals/investigations/medication-chart availability toggles each start from `context.lock_version` (the `ClinicalCase` row's own lock version at page-load time) since they all sync against the same `ClinicalCase` row via three different `section_key`s — this is intentional (see Slice 2B's Architecture section) and mirrors exactly how `CaseProfileSection.vue` already uses `context.lock_version` in Slice 2A. If a student edits Case Profile and a vitals-availability toggle in quick succession, the second sync's `base_lock_version` will already be stale by design (the first sync bumped the shared row's `lock_version`) — `useSectionSync`'s existing conflict handling (Slice 2A) covers this exactly as it covers any other concurrent edit to the same row, so no new logic is needed, only awareness that these three toggles and Case Profile share one underlying optimistic-lock counter.

- [ ] **Step 11: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 12: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 1 new assertion-extended test pass (184 passed / 2 skipped, same count as Task 4 since Step 1 extended an existing test file rather than adding a new one).

- [ ] **Step 13: Commit**

```bash
git add resources/js/pages/student/case-editor/VitalRow.vue resources/js/pages/student/case-editor/InvestigationRow.vue resources/js/pages/student/case-editor/VitalsInvestigationsSection.vue resources/js/pages/student/case-editor/MedicationRow.vue resources/js/pages/student/case-editor/MedicationChartSection.vue resources/js/pages/student/CaseEditor.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git commit -m "feat: wire Vitals & Investigations and Medication Chart into the mobile case editor"
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

        $this->postJson("/student/cases/{$case->id}/vitals", ['client_operation_id' => (string) Str::uuid(), 'observation_type' => 'pulse'])->assertNotFound();
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

        $this->postJson("/student/cases/{$case->id}/investigations", ['client_operation_id' => (string) Str::uuid(), 'test_name' => 'x', 'result_type' => 'numeric', 'result_value' => '1'])->assertForbidden();
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
Expected: all previous + 3 new tests pass (187 passed / 2 skipped).

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

**Files:** None (verification only).

**Interfaces:** None.

Same rationale and tooling as Slice 2A Task 8 (no committed Playwright specs for authenticated flows in this repo; manual/interactive browser verification against the local dev server).

- [ ] **Step 1: Phone viewport (390×844)**

Open the case editor, switch to "Vitals & Investigations". Confirm:
- Adding a vital/investigation appends a new row at the top and the row is immediately editable.
- Editing a row field shows the same "Saving…" → "Saved" cycle as Case Profile fields.
- Toggling "Unavailable / not clinically relevant" hides the row list and reveals the reason field; the reason field is required before the toggle change persists (submit without a reason and confirm the request is rejected client-visibly, e.g. via a 422 the composable surfaces as `failed` state — if it doesn't surface clearly, note this as a Slice 2C polish item rather than silently passing).
- Removing a row while online removes it immediately; toggling the device offline (DevTools) and attempting to remove a row shows the disabled state with the "Reconnect to remove this row" tooltip/title.

Switch to "Medication Chart". Confirm:
- Selecting a medication's status as "Stopped" or "Completed" reveals the stop-date field; selecting "PRN" reveals the PRN-indication field.
- Typing a 10-digit number into a medication's Notes field shows the de-identification warning.
- "No current medicines documented" hides the medication row list.

- [ ] **Step 2: Offline recovery and conflict — repeat for a row edit**

Using DevTools offline toggle: edit an existing vital's unit field while offline, confirm it shows "Saved on this device" and survives a refresh (recovered from `outboxStore.ts` via `useSectionSync`'s mount recovery — this is the same mechanism Slice 2A verified for Case Profile, now proven against a per-row resource). Reconnect and confirm it syncs.

Simulate a two-tab conflict on the same medication row (edit dose in Tab A and let it save, then edit route in Tab B using the stale `lock_version`) and confirm the conflict panel appears with the three resolution options, exactly as in Slice 2A Task 8 Step 3.

- [ ] **Step 3: Tablet (820×1180) and Desktop (1280×900, Chrome/Edge)**

Repeat Step 1's pass criteria at both viewports.

- [ ] **Step 4: Record the result**

Note the verification outcome in the pull-request description when 2B is opened for review (do not update `PROJECT_STATE.md` yet — Slice 2 as a whole is still active until 2C also lands, per the Slice 1 precedent of only updating `PROJECT_STATE.md` on full-gate acceptance).

---

## Self-Review Notes

- **Spec coverage:** Requirement 1 (explicit unavailable states) — UI lands here (Tasks 2–5), schema was Slice 2A Task 1. Requirement 2 (mobile section editor) — extended in Task 5. Requirement 3 (repeatable rows) — Tasks 2–5, all three resource types. Requirement 4 (partial autosave) — every `Update*Request` in Tasks 2–4 uses `'sometimes'`. Requirement 5 (outbox/idempotency/locking/conflict) — reused from Slice 2A for row *edits*; row *creation* gets its own idempotency via the new `SectionSyncService::create()` (Task 2); the offline-scope decision for create/delete is stated explicitly in the Architecture section, not silently narrowed. Requirement 8 (authorization tests) — Task 6. Requirement 9 (device verification) — Task 7. Requirement 6 (conditional allergy/ADR) and requirement 7 (de-identification, already delivered in 2A) — the Medication Chart's `DeidentificationNotice` reuse in Task 5 extends requirement 7's coverage to this slice's free-text field; ADR proper remains Slice 2C.
- **Placeholder scan:** No task defers real logic; the "medication_chart_status not cross-checked against actual rows" boundary is explicitly named as a Slice 3 concern, not left ambiguous.
- **Type consistency:** `SectionSyncService::create()`'s return shape matches `sync()`'s (`array{status, httpStatus, model}`, minus a redundant `created` flag simplified out during design). Every row payload method (`CaseVitalController::payload()`, etc.) returns the same field set the corresponding `Update*Request` accepts, so a synced row and a freshly created row are interchangeable on the frontend.
