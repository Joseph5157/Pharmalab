# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2A (Sync Infrastructure, Case Profile, History & Diagnosis) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution was chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Revision note (post-review):** This plan was reviewed and returned with blocking corrections before any implementation began. This revision fixes: a data-loss bug in the `sync_operations` migration, a false-conflict bug from sharing one `lock_version` across four independent sections, an idempotency hole that let a replayed operation ID resolve to a class the caller never asked for, a missing logout hook for the new IndexedDB store, and several clinical fields/controls the field catalogue requires that the original draft never rendered. See each task's **Revision:** note for what changed and why.

**Goal:** Generalize the accepted SYNC-SPIKE-01 offline-sync protocol (IndexedDB outbox, idempotent `client_operation_id`, server `lock_version`, optimistic-concurrency conflict resolution) from the single experimental `CaseDraftNote` into a reusable engine, then use it to ship the first two real sections of the mobile case editor — Case Profile and History & Diagnosis — with partial (field-level) autosave and the explicit vitals/investigations/medication-chart "unavailable" schema fields that Slice 1 deferred to this slice.

**Architecture:** This is the first of three sequential sub-plans for Slice 2 (2A → 2B → 2C), chosen over one monolithic plan because the full mobile editor spans 6 sections, 4 repeatable resource types and a generalized sync engine — too large to review or gate as a single unit. 2A builds the shared backend engine (`App\Contracts\Syncable` + `SyncsWithLockVersion` trait + `SectionSyncService`, generalizing `CaseDraftNoteController`'s transaction/dedup/conflict logic) and the shared frontend engine (`outboxStore.ts` + `useSectionSync.ts`, generalizing `caseDraftStore.ts` + the script block of `CaseDraftNote.vue`), proves both against `ClinicalCase` and `CaseClinicalProfile`, and ships a real two-section mobile editor shell (`CaseEditor.vue`). 2B reuses this engine for the three repeatable-row sections (Vitals, Investigations, Medication Chart) and adds offline-capable row *creation*. 2C reuses it again for SOAP and Conditional Clinical Activities, and adds end-to-end device verification across all six sections. The existing `CaseDraftNote` experiment, its controller, and `caseDraftStore.ts` are left untouched — Slice 2 adds parallel, generalized infrastructure rather than modifying the accepted spike; only the shared logout hook (Task 3) touches code the spike already uses, and only additively (it clears a *second*, independent store alongside the existing one).

Four sections write to the single `ClinicalCase` row in this slice and the next (Case Profile in 2A; the three "unavailable" availability toggles in 2B). Sharing one `lock_version` across all four would mean editing Case Profile and toggling "vitals unavailable" in the same visit produces a false 409 for whichever request lands second, even though the two edits touch disjoint columns. `Syncable::getLockVersion()`/`applySyncedAttributes()` therefore take the `section_key` being synced and resolve it to one of four independent lock columns via a `lockVersionColumn(string $sectionKey): string` hook — one column (`lock_version`) for Case Profile, one new column apiece for the three 2B toggles (added to `clinical_cases` in Task 1, ahead of when 2B needs them, so Task 2's contract change and Task 1's schema change land together). Every other `Syncable` model in this series (`CaseClinicalProfile` here; `CaseVital`/`CaseInvestigation`/`CaseMedication`/`SoapNote`/`CaseClinicalActivity` in 2B/2C) is its own row, so the default `lockVersionColumn()` implementation (always `'lock_version'`) is correct for all of them unchanged.

**Tech Stack:** Laravel 13 (PHP 8.4), Eloquent, PHPUnit, SQLite (`:memory:`) for the test suite, PostgreSQL in production; Inertia.js + Vue 3 + TypeScript on the frontend, native `fetch` + IndexedDB for the offline sync path (matching the accepted SYNC-SPIKE-01 pattern — not an Inertia form).

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.1–4.2 and §5, decisions in [`docs/decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md`](../../decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md), accepted sync protocol in [`docs/SYNC_SPIKE_01_FINDINGS.md`](../../SYNC_SPIKE_01_FINDINGS.md), Slice 1 plan and ledger in [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md`](2026-09-25-direct-documentation-impl-01-slice-1.md).

## Global Constraints

- Run every `php`, `composer`, `npm`, `vendor/bin/phpunit` command through the **PowerShell** tool, not Bash — `php` is only on PATH via PowerShell (Laravel Herd) in this environment.
- `doctrine/dbal` is **not installed** in this project (confirmed: absent from `vendor/doctrine`). Never use `Schema::table(...)->change()`. Where an existing column's constraint must change without `dbal`, use raw `DB::statement()` (fine for a metadata-only change such as PostgreSQL's `ALTER COLUMN ... DROP NOT NULL`) or, where the driver cannot alter the constraint in place at all (SQLite), rebuild the table: create a new table with the target schema, `INSERT INTO ... SELECT ...` every existing row across, drop the old table, rename the new one. **Never drop a column that holds production data as a way to change its constraint** — Task 2's migration is the concrete example this constraint exists to prevent a regression of.
- After adding or changing any route in `routes/web.php`, regenerate the Wayfinder TypeScript files by running `npm run build` (the `@laravel/vite-plugin-wayfinder` Vite plugin regenerates `resources/js/actions/**` and `resources/js/routes/**` as part of the build) and commit the regenerated files in the **same commit** as the route change. Never hand-edit anything under `resources/js/actions/` or `resources/js/routes/` — it is generated. Do not commit incidental line-ending/comment churn in unrelated generated files; if `git status` shows generated files you did not intend to touch, run `git checkout -- resources/js/actions resources/js/routes` before committing and only regenerate immediately before the commit that needs it.
- `lock_version` (and every section-specific lock column added in Task 1) is never mass-fillable on any model. It is only ever bumped via `forceFill()` inside `SyncsWithLockVersion::applySyncedAttributes()` (Task 2). Task 1 fixes an existing Slice 1 regression: `CaseClinicalProfile`'s `#[Fillable([...])]` currently lists `'lock_version'`, which would let a client set it directly through mass assignment — remove it.
- Every sync-capable model (`ClinicalCase`, `CaseClinicalProfile`, and later Slice 2B/2C models) implements `App\Contracts\Syncable` via the `App\Models\Concerns\SyncsWithLockVersion` trait and is synced only through `App\Services\SectionSyncService`. Do not hand-roll a second copy of the dedup/lock/conflict transaction in a new controller — that duplication is exactly what Task 2 exists to prevent. `Syncable::getLockVersion()` and `applySyncedAttributes()` both take the `section_key` being synced (see Architecture) — a model backing more than one section must override `lockVersionColumn(string $sectionKey): string`; a model backing exactly one section (every model except `ClinicalCase`) inherits the trait's single-column default and never needs to override it.
- A replayed `client_operation_id` is only ever resolved through `SectionSyncService::modelClassForSection(string $sectionKey): string` (Task 2) — the single accessor for the private `SECTION_MODELS` constant, throwing `InvalidArgumentException` on an unrecognized key — never by instantiating or querying a class name read out of the `sync_operations.syncable_type` column (`new $row->syncable_type` / `($row->syncable_type)::query()` are both forbidden) and never via an inline `self::SECTION_MODELS[$key] ?? $model::class` fallback, which routes around the allow-list instead of being governed by it. `sync()`, `create()` (2B) and `delete()` (2B) all call `modelClassForSection()` to both (a) assert the model instance they were handed actually matches its claimed section, and (b) validate a replayed `SyncOperation`'s stored `syncable_type` — neither check is ever satisfied by a caller-supplied "expected class" argument; `create()`/`delete()` derive it internally exactly as `sync()` does.
- Partial autosave (Slice 2 requirement): every `Update*Request` used by a `sync()` endpoint gives each field rule set a leading `'sometimes'` entry, so a payload that omits a key is neither validated nor written — the existing `UpdateCaseClinicalProfileRequest::rules()` currently has `'allergy_status' => ['required', ...]` with no `'sometimes'`, which would reject any partial autosave that doesn't include `allergy_status`; Task 5 fixes this named example directly.
- Every sync/store request rejects unknown top-level fields (field catalogue §5, "Unknown request fields are rejected by the server" — this is a spec requirement, not a hardening choice this plan invents). Task 4 introduces a shared `App\Http\Requests\Concerns\RejectsUnknownFields` trait alongside `HasSyncEnvelope`. The trait's check lives in a plain method, `rejectUnknownFields(Validator $validator): void` — deliberately **not** named `withValidator()` — because `withValidator()` is a Laravel-called hook a class can only define once; every request class in this plan and in 2B/2C defines its own `withValidator()` and calls `$this->rejectUnknownFields($validator);` as its first statement, whether or not it also needs a second, request-specific check in the same method. A request that defines `withValidator()` without that first-line call has silently lost unknown-field rejection — Review Focus calls this out as a class of bug to watch for in review, not just in the request classes this plan writes. The trait's `rejectUnknownFields()` rejects only **top-level** keys — it deliberately does not descend into array/object values, so a field whose value is itself a restricted-key object (2C's `case_clinical_activities.details` JSON) must restrict its nested keys with Laravel's `array:key1,key2` rule *in addition to* the trait; 2C's Tasks 3–5 do exactly that, and any future nested-JSON request must follow the same pattern.
- Free-text narrative fields get the de-identification warning (`DeidentificationNotice.vue`, Task 5) wherever they appear anywhere in the editor, not only on the fields this slice happens to touch first — 2B and 2C's plans each apply it to their own narrative fields (medication notes, SOAP Subjective, ADR event, counselling notes) using the same component this task creates.
- Logging out must clear the new section outbox (`clearSectionOutbox()`, Task 3) in the same place the app already clears the SYNC-SPIKE-01 draft store on logout (`resources/js/components/UserMenuContent.vue`'s `handleLogout()`, which already calls `clearCaseDraftStorage()`). This is not a "nice to have" deferrable to 2C — a shared/institutional device that logs a second student in must not be able to read the first student's unsynced drafts, and Task 3 wires it in the same commit that creates the store.
- This repository has no JavaScript/TypeScript unit-test runner (only Playwright e2e specs and PHPUnit; confirmed by searching for Vitest/Jest configuration — none exists). Do not add one. Frontend-only tasks are gated by `npm run types:check` (TypeScript compiles) and are functionally verified once a real page in a later task renders them; say so explicitly in the task rather than inventing a test file that doesn't match the project's established testing shape.
- Baseline before this plan: `main` at commit `151e1a1` (one commit past the accepted `65c5b7e` Slice 1 merge, itself just a docs formatting commit). `php artisan test` passes 131 tests, 129 passed, 2 skipped, 453 assertions. Every task's "run full suite" step expects these to still pass plus the task's new tests; running totals in this plan are computed cumulatively from that baseline and were re-checked against the actual test methods each task adds during this revision.

## Review Focus

- **Partial-save field wipe.** A sync request that supplies only one field (e.g. a payload containing just `past_medical_history`) must not fail validation because `allergy_status` is absent, and must not null out `allergy_status` or any other previously-saved field on the row. Tasks 4 and 5's controller tests each post a single-field payload after an initial full save and assert every other stored field is unchanged.
- **False conflict between Case Profile and a sibling `ClinicalCase`-backed section.** Because 2B's three availability toggles share the `clinical_cases` row with Case Profile, syncing Case Profile must never bump a lock column a concurrent availability-toggle sync depends on, and vice versa. Task 2's engine test proves two different `section_key`s against the same `ClinicalCase` row advance independent lock columns and never 409 each other.
- **Eager profile creation regression.** Opening the case editor (`GET /student/cases/{case}/edit`) must never create a `CaseClinicalProfile` row — only a deliberate `sync()` call may. Task 5's test asserts `$case->fresh()->clinicalProfile` is still `null` after rendering the editor page, and Task 6's editor-shell test repeats the same assertion at the page level.
- **Stale `lock_version` silently overwriting a newer save.** Any `sync()` call whose `base_lock_version` does not match the row's current lock column value must return HTTP 409 with the current server state and must not mutate the row. Task 2's engine test and Tasks 4/5's HTTP-level tests each assert this by sending a stale version after an intervening save.
- **Cross-institution / other-student / post-submission access.** Every new `PUT` endpoint must deny a different student in the same institution, a user from a different institution, and — once the case's status leaves Draft/Returned — even the owning student. Task 7's dedicated authorization test file exercises all three denials against both new controllers, matching the negative-authorization pattern established in Slice 1's `CaseClinicalProfileTest`.
- **Idempotent replay creating a duplicate write, or resolving to the wrong record.** A retried request carrying the same `client_operation_id` must not double-apply the change or insert a second `SyncOperation` row (Task 2's engine test, Task 4's controller test). A `client_operation_id` reused across two *different* section endpoints — a client bug or a malicious replay — must be rejected outright rather than silently resolving to whichever record the first use created (Task 2's engine test covers this directly against the `sync_operations` unique constraint and the section-key/class allow-list).
- **Logout leaving a readable draft behind.** `clearSectionOutbox()` must actually run on logout, not just exist as an exported function nobody calls. Task 3 wires it into the same `handleLogout()` the SYNC-SPIKE-01 draft store already uses and Task 8's manual pass verifies IndexedDB is empty afterward.
- **A request-specific `withValidator()` silently disabling unknown-field rejection.** Any request class in this plan (or 2B/2C) that needs its own `withValidator()` for a second check — an age/unit bound, a BP-pairing rule, a row-existence gate — must call `$this->rejectUnknownFields($validator);` as its first statement. A class that defines `withValidator()` without that call has quietly lost the field catalogue §5 guarantee even though it still `use`s the trait. Every such class's test file includes an unknown-top-level-field 422 assertion specifically so this can't regress unnoticed.
- **A caller-supplied "expected class" routing around the section allow-list.** `SectionSyncService::create()`/`delete()` (2B) derive the model class for a section from `modelClassForSection($sectionKey)` internally — never from an argument the calling controller passes in. A controller that could pass any class it likes defeats the whole point of the allow-list; Task 2's (2B) tests assert `create()`'s signature has no such parameter and that mismatched model/section combinations are rejected.
- **An unknown nested key inside a JSON/array field passing through the top-level-only trait check.** `RejectsUnknownFields` rejects only top-level keys; a key nested inside an array-valued field (2C's `details` object) would otherwise be silently accepted and persisted. Every request that accepts such a field must pair the trait with an `array:key1,key2` rule naming exactly the allowed nested keys — 2C's Tasks 3–5 do this and each includes a nested-unknown-field 422 test.

---

## Task 1: Explicit-absence schema fields, independent section-lock columns, and the Slice 1 `lock_version` Fillable fix

**Revision:** Originally this task added only the five vitals/investigations/medication-chart status fields. It now also adds the three independent lock columns the availability toggles need (see Architecture) — adding them here, ahead of when 2B's controllers consume them, keeps the schema change and the `Syncable` contract change (Task 2) landing together instead of forcing 2B to reopen this migration. It also adds an optional explanation field for "no current medicines" (field catalogue §4.2 "Medication history... or explicit no previous/current medicines documented" — the original draft added the boolean toggle in 2B but never gave the student anywhere to say *why*, which the review flagged as a gap against the same pattern already used for vitals/investigations).

**Files:**
- Create: `database/migrations/2026_09_28_000000_add_pharmd_slice2_section_availability_fields.php`
- Modify: `app/Models/ClinicalCase.php` (extend `#[Fillable([...])]`)
- Modify: `app/Models/CaseClinicalProfile.php` (remove `'lock_version'` from `#[Fillable([...])]`)
- Test: `tests/Feature/PharmdCaseSectionAvailabilityFieldsTest.php`

**Interfaces:**
- Produces: new nullable columns on `clinical_cases` — `vitals_status` (string, values `'recorded'|'unavailable'`), `vitals_unavailable_reason` (text), `investigations_status` (string, values `'recorded'|'unavailable'`), `investigations_unavailable_reason` (text), `medication_chart_status` (string, values `'documented'|'none_documented'`), `medication_chart_none_reason` (text, optional explanation for `none_documented`), `vitals_availability_lock_version` (unsigned big integer, default 0), `investigations_availability_lock_version` (unsigned big integer, default 0), `medication_chart_availability_lock_version` (unsigned big integer, default 0). These are schema-only in this task; Slice 2B's Vitals/Investigations/Medication controllers own reading and validating the status/reason fields, and Task 2 of this slice is what makes the three new lock columns reachable through `Syncable`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdCaseSectionAvailabilityFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_cases_has_the_explicit_absence_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('clinical_cases', [
            'vitals_status', 'vitals_unavailable_reason',
            'investigations_status', 'investigations_unavailable_reason',
            'medication_chart_status', 'medication_chart_none_reason',
        ]));
    }

    public function test_clinical_cases_has_independent_lock_columns_for_the_three_availability_toggles(): void
    {
        $this->assertTrue(Schema::hasColumns('clinical_cases', [
            'vitals_availability_lock_version',
            'investigations_availability_lock_version',
            'medication_chart_availability_lock_version',
        ]));
    }

    public function test_explicit_absence_fields_persist_on_a_case(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Patient not examined at bedside during ward round.',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'none_documented',
            'medication_chart_none_reason' => 'No home or chart medicines reported by caregiver.',
        ]);

        $fresh = $case->fresh();
        $this->assertSame('unavailable', $fresh->vitals_status);
        $this->assertSame('Patient not examined at bedside during ward round.', $fresh->vitals_unavailable_reason);
        $this->assertSame('recorded', $fresh->investigations_status);
        $this->assertNull($fresh->investigations_unavailable_reason);
        $this->assertSame('none_documented', $fresh->medication_chart_status);
        $this->assertSame('No home or chart medicines reported by caregiver.', $fresh->medication_chart_none_reason);
        $this->assertSame(0, $fresh->vitals_availability_lock_version);
        $this->assertSame(0, $fresh->investigations_availability_lock_version);
        $this->assertSame(0, $fresh->medication_chart_availability_lock_version);
    }

    public function test_lock_version_is_not_mass_assignable_on_case_clinical_profile(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
            'lock_version' => 99,
        ]);

        $this->assertSame(0, $profile->fresh()->lock_version);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=PharmdCaseSectionAvailabilityFieldsTest`
Expected: FAIL — unknown columns; third test currently passes for the wrong reason (column is still mass-assignable) — confirmed by Step 4's fix.

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
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->string('vitals_status', 20)->nullable()->after('deidentification_attested_by');
            $table->text('vitals_unavailable_reason')->nullable()->after('vitals_status');
            $table->string('investigations_status', 20)->nullable()->after('vitals_unavailable_reason');
            $table->text('investigations_unavailable_reason')->nullable()->after('investigations_status');
            $table->string('medication_chart_status', 20)->nullable()->after('investigations_unavailable_reason');
            $table->text('medication_chart_none_reason')->nullable()->after('medication_chart_status');
            $table->unsignedBigInteger('vitals_availability_lock_version')->default(0)->after('lock_version');
            $table->unsignedBigInteger('investigations_availability_lock_version')->default(0)->after('vitals_availability_lock_version');
            $table->unsignedBigInteger('medication_chart_availability_lock_version')->default(0)->after('investigations_availability_lock_version');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->dropColumn([
                'vitals_status',
                'vitals_unavailable_reason',
                'investigations_status',
                'investigations_unavailable_reason',
                'medication_chart_status',
                'medication_chart_none_reason',
                'vitals_availability_lock_version',
                'investigations_availability_lock_version',
                'medication_chart_availability_lock_version',
            ]);
        });
    }
};
```

- [ ] **Step 4: Extend `ClinicalCase`'s Fillable list**

In `app/Models/ClinicalCase.php`, add the six new mass-assignable keys to the `#[Fillable([...])]` array, after `'deidentification_attested_by',` (the three lock columns are deliberately **not** added here — they are bumped only via `forceFill()` in `SyncsWithLockVersion`, per the Global Constraints):

```php
    'vitals_status',
    'vitals_unavailable_reason',
    'investigations_status',
    'investigations_unavailable_reason',
    'medication_chart_status',
    'medication_chart_none_reason',
```

- [ ] **Step 5: Fix `CaseClinicalProfile`'s Fillable list**

In `app/Models/CaseClinicalProfile.php`, remove `'lock_version',` from the `#[Fillable([...])]` array. `lock_version` must only ever be set via `forceFill()`.

- [ ] **Step 6: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdCaseSectionAvailabilityFieldsTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Run the full suite to confirm no regression**

Run (PowerShell): `php artisan test`
Expected: 129 previous passed (2 skipped unchanged) + 4 new — 133 passed / 2 skipped (135 total).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_28_000000_add_pharmd_slice2_section_availability_fields.php app/Models/ClinicalCase.php app/Models/CaseClinicalProfile.php tests/Feature/PharmdCaseSectionAvailabilityFieldsTest.php
git commit -m "feat: add explicit section-availability fields, independent lock columns, and fix CaseClinicalProfile lock_version mass assignment"
```

---

## Task 2: Generalize the sync engine — `Syncable` contract, polymorphic `SyncOperation` (data-preserving migration), `SectionSyncService`

**Revision:** Two blocking fixes land here. First, the original `sync_operations` migration dropped `case_draft_note_id` and re-added it to relax its `NOT NULL` constraint — on a database that already has rows (i.e. anywhere past local development), that destroys every existing sync-operation's association with its `CaseDraftNote`. The corrected migration never drops the column: PostgreSQL gets a metadata-only `ALTER COLUMN ... DROP NOT NULL`; SQLite, which cannot alter a column constraint in place at all, gets a table rebuild that copies every row across before the old table is dropped. Second, `Syncable::getLockVersion()`/`applySyncedAttributes()` now take the `section_key` being synced (see the plan's Architecture section) so `ClinicalCase` can route four different sections to four different lock columns without a false conflict between them.

**Files:**
- Create: `database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php`
- Create: `app/Contracts/Syncable.php`
- Create: `app/Models/Concerns/SyncsWithLockVersion.php`
- Modify: `app/Models/SyncOperation.php` (add `syncable_type`/`syncable_id` to fillable, add `syncable()` morph relation)
- Modify: `app/Models/ClinicalCase.php` (implement `Syncable` via the new trait, override `lockVersionColumn()`)
- Create: `app/Services/SectionSyncService.php`
- Test: `tests/Feature/SectionSyncServiceTest.php`
- Test: `tests/Feature/SyncOperationsMigrationTest.php`

**Interfaces:**
- Consumes: `App\Models\ClinicalCase` (as the first real `Syncable` under test), `App\Models\SyncOperation`, `App\Services\AuditTrail::record()`.
- Produces: `App\Contracts\Syncable` (`getLockVersion(string $sectionKey): int`, `getInstitutionId(): string`, `applySyncedAttributes(string $sectionKey, array $attributes, int $newLockVersion): void`), `App\Models\Concerns\SyncsWithLockVersion` (implements all three against a single `lock_version` column by default, via a `protected function lockVersionColumn(string $sectionKey): string` hook models can override), `App\Services\SectionSyncService::sync(Model&Syncable $model, User $user, string $sectionKey, string $clientOperationId, int $baseLockVersion, array $attributes, ?string $resolution, bool $confirmed): array{status: string, httpStatus: int, model: Model&Syncable}`, and a private `SECTION_MODELS` allow-list (`array<string, class-string>`) that `sync()`'s replay path and 2B's `create()` method both check a stored `syncable_type` against. Consumed by Tasks 4 and 5 (Case Profile, History & Diagnosis) and by every Slice 2B/2C section controller.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\SectionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SectionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_save_persists_attributes_and_bumps_lock_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'Drug Therapy Problem'], null, false);

        $this->assertSame('saved', $result['status']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('Drug Therapy Problem', $result['model']->case_category);
        $this->assertSame(1, $result['model']->lock_version);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'case_category' => 'Drug Therapy Problem', 'lock_version' => 1]);
    }

    public function test_replaying_the_same_client_operation_id_does_not_double_apply(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $operationId = (string) Str::uuid();

        $service->sync($case, $student, 'case_context', $operationId, 0, ['case_category' => 'A'], null, false);
        $result = $service->sync($case, $student, 'case_context', $operationId, 0, ['case_category' => 'A'], null, false);

        $this->assertSame('saved', $result['status']);
        $this->assertSame(1, $case->fresh()->lock_version);
        $this->assertSame(1, SyncOperation::query()->where('client_operation_id', $operationId)->count());
    }

    public function test_reusing_an_operation_id_against_a_different_section_key_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);
        $operationId = (string) Str::uuid();

        $service->sync($case, $student, 'case_context', $operationId, 0, ['case_category' => 'A'], null, false);

        $this->expectException(HttpException::class);

        $service->sync($case, $student, 'vitals_availability', $operationId, 0, ['vitals_status' => 'unavailable'], null, false);
    }

    public function test_reusing_an_operation_id_against_a_different_syncable_row_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $case->institution_id, 'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown', 'last_saved_by' => $student->id,
        ]);
        $service = app(SectionSyncService::class);
        $operationId = (string) Str::uuid();

        $service->sync($case, $student, 'case_context', $operationId, 0, ['case_category' => 'A'], null, false);

        $this->expectException(HttpException::class);

        $service->sync($profile, $student, 'clinical_profile', $operationId, 0, ['allergy_status' => 'no_known_allergy'], null, false);
    }

    public function test_a_stale_base_lock_version_is_reported_as_a_conflict_without_mutating_the_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'First'], null, false);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'Conflicting'], null, false);

        $this->assertSame('conflict', $result['status']);
        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('First', $result['model']->case_category);
        $this->assertSame('First', $case->fresh()->case_category);
    }

    public function test_use_server_resolution_ignores_local_content_and_does_not_bump_the_version(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'Server value'], null, false);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'Ignored local value'], 'use_server', false);

        $this->assertSame('resolved_server', $result['status']);
        $this->assertSame('Server value', $result['model']->case_category);
        $this->assertSame(1, $result['model']->lock_version);
    }

    public function test_replace_server_without_confirmation_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'First'], null, false);

        $this->expectException(HttpException::class);

        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 1, ['case_category' => 'Forced'], 'replace_server', false);
    }

    public function test_replace_server_with_confirmation_overwrites_and_records_an_audit_event(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'First'], null, false);
        $result = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 1, ['case_category' => 'Forced'], 'replace_server', true);

        $this->assertSame('resolved_replaced', $result['status']);
        $this->assertSame('Forced', $result['model']->case_category);
        $this->assertSame(2, $result['model']->lock_version);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => ClinicalCase::class,
            'auditable_id' => $case->id,
            'event_type' => 'case_context.conflict_resolved',
        ]);
    }

    public function test_two_different_sections_on_the_same_clinical_case_row_do_not_false_conflict(): void
    {
        [, $student, $case] = $this->makeCase();
        $service = app(SectionSyncService::class);

        $caseContext = $service->sync($case, $student, 'case_context', (string) Str::uuid(), 0, ['case_category' => 'DTP'], null, false);
        $vitalsAvailability = $service->sync($case, $student, 'vitals_availability', (string) Str::uuid(), 0, ['vitals_status' => 'unavailable', 'vitals_unavailable_reason' => 'Not examined.'], null, false);

        $this->assertSame('saved', $caseContext['status']);
        $this->assertSame('saved', $vitalsAvailability['status']);
        $fresh = $case->fresh();
        $this->assertSame(1, $fresh->lock_version);
        $this->assertSame(1, $fresh->vitals_availability_lock_version);
        $this->assertSame('DTP', $fresh->case_category);
        $this->assertSame('unavailable', $fresh->vitals_status);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        return [$institution, $student, $case];
    }
}
```

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseDraftNote;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncOperationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_draft_note_id_is_nullable_and_the_polymorphic_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('sync_operations', ['syncable_type', 'syncable_id']));

        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => CaseStatus::Draft,
        ]);

        $operation = SyncOperation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'user_id' => $student->id,
            'case_draft_note_id' => null,
            'syncable_type' => ClinicalCase::class,
            'syncable_id' => $case->id,
            'client_operation_id' => (string) Str::uuid(),
            'section_key' => 'case_context',
            'base_lock_version' => 0,
            'result_status' => 'saved',
            'server_version' => 1,
        ]);

        $this->assertNull($operation->fresh()->case_draft_note_id);
    }

    public function test_migration_round_trip_preserves_existing_case_draft_note_associations(): void
    {
        // This test exercises real rollback/re-migrate DDL inside PHPUnit's per-test
        // transaction. SQLite supports transactional DDL so this is safe under
        // RefreshDatabase; if it proves flaky under a different test database driver,
        // switch this one test to Illuminate\Foundation\Testing\DatabaseTransactions
        // run against a dedicated non-memory SQLite file instead of dropping the
        // coverage — the thing under test (existing rows survive the migration) matters
        // more than which isolation trait proves it.
        //
        // The rollback is pinned to THIS migration file via --path, not --step=1.
        // Once Slice 2B/2C add newer migrations, a bare --step=1 would roll back the
        // newest migration (e.g. 2C's singleton unique index) instead of
        // make_sync_operations_polymorphic, silently exercising the wrong file. --path
        // keeps this test aimed at the exact migration under test forever.
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $draftNote = CaseDraftNote::query()->withoutGlobalScopes()->create([
            'case_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'content' => 'Draft content.',
        ]);
        $operation = SyncOperation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'user_id' => $student->id,
            'case_draft_note_id' => $draftNote->id,
            'client_operation_id' => (string) Str::uuid(),
            'section_key' => 'case_draft_note',
            'base_lock_version' => 0,
            'result_status' => 'saved',
            'server_version' => 1,
        ]);

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php']);
        Artisan::call('migrate', ['--path' => 'database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php']);

        $this->assertSame($draftNote->id, SyncOperation::query()->withoutGlobalScopes()->findOrFail($operation->id)->case_draft_note_id);
    }

    public function test_rollback_refuses_once_generalized_sync_operations_rows_exist(): void
    {
        // Artisan::call() is expected to let a thrown RuntimeException from
        // inside Migration::down() propagate as a normal PHP exception (this
        // is the standard way Laravel test suites assert a migration
        // failure) — caught explicitly here, rather than via
        // expectException(), so the post-failure assertions below still run
        // regardless of exactly how the exception surfaces in this Laravel
        // version; if $thrown stays null, that itself is a failure worth
        // seeing directly rather than a silently-passed expectException.
        // Like the round-trip test above, this exercises real rollback DDL
        // inside PHPUnit's per-test transaction (safe under SQLite, which
        // supports transactional DDL); if it proves flaky under a different
        // driver, isolate this test on a dedicated file-backed SQLite
        // database via DatabaseTransactions rather than dropping it. The
        // rollback is pinned to THIS migration file via --path (not --step=1,
        // which would roll back 2B/2C's newer migrations once they exist).
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $draftNote = CaseDraftNote::query()->withoutGlobalScopes()->create([
            'case_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'content' => 'Draft content.',
        ]);
        SyncOperation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'user_id' => $student->id,
            'case_draft_note_id' => $draftNote->id,
            'client_operation_id' => (string) Str::uuid(),
            'section_key' => 'case_draft_note',
            'base_lock_version' => 0,
            'result_status' => 'saved',
            'server_version' => 1,
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'rotation_assignment_id' => null, 'case_number' => 1, 'status' => CaseStatus::Draft,
        ]);
        SyncOperation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'user_id' => $student->id,
            'case_draft_note_id' => null,
            'syncable_type' => ClinicalCase::class,
            'syncable_id' => $case->id,
            'client_operation_id' => (string) Str::uuid(),
            'section_key' => 'case_context',
            'base_lock_version' => 0,
            'result_status' => 'saved',
            'server_version' => 1,
        ]);

        $thrown = null;
        try {
            Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php']);
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'Expected the rollback to refuse once a generalized (non-CaseDraftNote) row exists.');
        $this->assertStringContainsString('Cannot roll back: generalized sync_operations rows exist', $thrown->getMessage());
        $this->assertTrue(Schema::hasColumns('sync_operations', ['syncable_type', 'syncable_id']));
        $this->assertSame(2, SyncOperation::query()->withoutGlobalScopes()->count());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=SectionSyncServiceTest`
Expected: FAIL — `App\Services\SectionSyncService` not found.

Run (PowerShell): `php artisan test --filter=SyncOperationsMigrationTest`
Expected: FAIL — `syncable_type`/`syncable_id` columns don't exist; `case_draft_note_id` is still `NOT NULL`.

- [ ] **Step 3: Write the data-preserving `sync_operations` polymorphism migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes sync_operations polymorphic (syncable_type/syncable_id) so any
 * Syncable model, not just CaseDraftNote, can use the sync engine.
 *
 * down() is a ONE-WAY DOOR once the polymorphic path is in real use: as
 * soon as any row exists with syncable_type set and case_draft_note_id
 * NULL (i.e. any Slice 2A+ section has synced at least once), there is no
 * legacy case_draft_note_id value to restore for that row, so re-imposing
 * the original NOT NULL constraint is impossible without inventing data.
 * down() detects this and refuses outright rather than attempting a doomed
 * ALTER/rebuild that would either fail loudly (PostgreSQL) or silently
 * corrupt data (a naive SQLite rebuild coercing NULL to some placeholder).
 * An operator who genuinely needs to revert the whole generalized-sync
 * feature must first make an explicit data decision — typically deleting
 * the generalized sync_operations rows and accepting the loss of their
 * idempotency/audit history — before this migration can roll back; it will
 * never make that decision silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildWithPolymorphicColumns();

            return;
        }

        // PostgreSQL: dropping NOT NULL is a metadata-only change — no existing
        // row's case_draft_note_id value is read, copied or touched.
        DB::statement('ALTER TABLE sync_operations ALTER COLUMN case_draft_note_id DROP NOT NULL');

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->string('syncable_type', 150)->nullable()->after('case_draft_note_id');
            $table->ulid('syncable_id')->nullable()->after('syncable_type');
            $table->index(['syncable_type', 'syncable_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('sync_operations')->whereNotNull('syncable_type')->exists()) {
            throw new \RuntimeException('Cannot roll back: generalized sync_operations rows exist with no case_draft_note_id value. Manual data decision required — see migration docblock.');
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildWithoutPolymorphicColumns();

            return;
        }

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropIndex(['syncable_type', 'syncable_id']);
            $table->dropColumn(['syncable_type', 'syncable_id']);
        });

        DB::statement('ALTER TABLE sync_operations ALTER COLUMN case_draft_note_id SET NOT NULL');
    }

    /**
     * SQLite cannot alter a column's NOT NULL constraint in place. Rebuild the
     * table with the target schema and copy every existing row across before
     * dropping the old table — this is the safe SQLite migration shape; it must
     * never be simplified to a dropColumn()+addColumn() pair, which would lose
     * every row's case_draft_note_id value instead of just relaxing it.
     */
    private function rebuildWithPolymorphicColumns(): void
    {
        Schema::create('sync_operations_rebuild', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_draft_note_id')->nullable()->constrained('case_draft_notes')->restrictOnDelete();
            $table->string('syncable_type', 150)->nullable();
            $table->ulid('syncable_id')->nullable();
            $table->uuid('client_operation_id');
            $table->string('section_key', 80)->default('case_draft_note');
            $table->unsignedBigInteger('base_lock_version');
            $table->string('result_status', 40);
            $table->unsignedBigInteger('server_version');
            $table->timestamps();

            $table->unique(['user_id', 'client_operation_id']);
            $table->index(['institution_id', 'case_draft_note_id']);
            $table->index(['syncable_type', 'syncable_id']);
        });

        DB::statement(
            'INSERT INTO sync_operations_rebuild (id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at) '
            .'SELECT id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at FROM sync_operations'
        );

        Schema::drop('sync_operations');
        Schema::rename('sync_operations_rebuild', 'sync_operations');
    }

    private function rebuildWithoutPolymorphicColumns(): void
    {
        Schema::create('sync_operations_rebuild', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_draft_note_id')->constrained('case_draft_notes')->restrictOnDelete();
            $table->uuid('client_operation_id');
            $table->string('section_key', 80)->default('case_draft_note');
            $table->unsignedBigInteger('base_lock_version');
            $table->string('result_status', 40);
            $table->unsignedBigInteger('server_version');
            $table->timestamps();

            $table->unique(['user_id', 'client_operation_id']);
            $table->index(['institution_id', 'case_draft_note_id']);
        });

        DB::statement(
            'INSERT INTO sync_operations_rebuild (id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at) '
            .'SELECT id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at FROM sync_operations'
        );

        Schema::drop('sync_operations');
        Schema::rename('sync_operations_rebuild', 'sync_operations');
    }
};
```

- [ ] **Step 4: Write the `Syncable` contract**

```php
<?php

namespace App\Contracts;

interface Syncable
{
    public function getLockVersion(string $sectionKey): int;

    public function getInstitutionId(): string;

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(string $sectionKey, array $attributes, int $newLockVersion): void;
}
```

- [ ] **Step 5: Write the `SyncsWithLockVersion` trait**

```php
<?php

namespace App\Models\Concerns;

trait SyncsWithLockVersion
{
    public function getLockVersion(string $sectionKey): int
    {
        return (int) $this->getAttribute($this->lockVersionColumn($sectionKey));
    }

    public function getInstitutionId(): string
    {
        return (string) $this->institution_id;
    }

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(string $sectionKey, array $attributes, int $newLockVersion): void
    {
        $this->forceFill([...$attributes, $this->lockVersionColumn($sectionKey) => $newLockVersion])->save();
    }

    /**
     * Every model except ClinicalCase backs exactly one section and keeps this
     * default. ClinicalCase backs four (see this plan's Architecture section)
     * and overrides this to route each section_key to its own lock column so
     * that syncing one section can never false-conflict with a concurrent sync
     * of another.
     */
    protected function lockVersionColumn(string $sectionKey): string
    {
        return 'lock_version';
    }
}
```

- [ ] **Step 6: Make `ClinicalCase` implement `Syncable` with per-section lock columns**

In `app/Models/ClinicalCase.php`, add imports:

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

Change the class declaration and trait usage:

```php
class ClinicalCase extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

Add the override (any private method placement is fine; place it near the other relation/accessor methods):

```php
    protected function lockVersionColumn(string $sectionKey): string
    {
        return match ($sectionKey) {
            'vitals_availability' => 'vitals_availability_lock_version',
            'investigations_availability' => 'investigations_availability_lock_version',
            'medication_chart_availability' => 'medication_chart_availability_lock_version',
            default => 'lock_version',
        };
    }
```

- [ ] **Step 7: Update `SyncOperation`**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncOperation extends Model
{
    use BelongsToInstitution, HasUlids;

    protected $fillable = [
        'institution_id', 'user_id', 'case_draft_note_id', 'syncable_type', 'syncable_id',
        'client_operation_id', 'section_key', 'base_lock_version', 'result_status', 'server_version',
    ];

    protected function casts(): array
    {
        return ['base_lock_version' => 'integer', 'server_version' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 8: Write `SectionSyncService`**

```php
<?php

namespace App\Services;

use App\Contracts\Syncable;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SectionSyncService
{
    /**
     * The single source of truth for "which class backs which section" —
     * every model/section-key consistency check in this service (sync()
     * here; create() and delete(), added in Slice 2B) goes through
     * modelClassForSection() below, which reads this constant and this
     * constant alone. This is also the only place a stored
     * sync_operations.syncable_type string is ever compared against, never
     * used to instantiate a class. There is no fallback to $model::class
     * anywhere in this service — an unrecognized section key is a
     * programming error and throws immediately rather than silently
     * degrading to "whatever class happened to be passed in". Slice 2B/2C
     * extend this constant as each new Syncable model lands; do not remove
     * entries, only add them.
     *
     * @var array<string, class-string>
     */
    private const SECTION_MODELS = [
        'case_context' => ClinicalCase::class,
        'vitals_availability' => ClinicalCase::class,
        'investigations_availability' => ClinicalCase::class,
        'medication_chart_availability' => ClinicalCase::class,
        'clinical_profile' => CaseClinicalProfile::class,
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * The one and only accessor for SECTION_MODELS. Every method in this
     * service that needs "what class backs this section" — including the
     * caller-facing consistency check in sync() and, from Slice 2B,
     * create()/delete() — calls this rather than reading the constant
     * directly, so there is exactly one place that can throw on an unknown
     * key and exactly one place that could ever drift from the constant.
     *
     * @throws \InvalidArgumentException if $sectionKey is not a recognized section
     */
    public function modelClassForSection(string $sectionKey): string
    {
        return self::SECTION_MODELS[$sectionKey]
            ?? throw new \InvalidArgumentException("Unknown section key: {$sectionKey}");
    }

    /**
     * @param  Model&Syncable  $model
     * @param  array<string, mixed>  $attributes
     * @return array{status: string, httpStatus: int, model: Model&Syncable}
     */
    public function sync(
        Model&Syncable $model,
        User $user,
        string $sectionKey,
        string $clientOperationId,
        int $baseLockVersion,
        array $attributes,
        ?string $resolution,
        bool $confirmed,
    ): array {
        $expectedClass = $this->modelClassForSection($sectionKey);
        abort_unless($model::class === $expectedClass, 500, "Model/section mismatch: {$sectionKey} expects {$expectedClass}, got {$model::class}.");

        return DB::transaction(function () use ($model, $user, $sectionKey, $expectedClass, $clientOperationId, $baseLockVersion, $attributes, $resolution, $confirmed): array {
            /** @var Model&Syncable $locked */
            $locked = $model::query()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            $existing = SyncOperation::query()
                ->where('user_id', $user->id)
                ->where('client_operation_id', $clientOperationId)
                ->first();

            if ($existing !== null) {
                abort_unless(
                    $existing->section_key === $sectionKey
                        && $existing->syncable_type === $expectedClass
                        && $existing->syncable_id === $locked->getKey(),
                    409,
                    'Operation ID already used for a different action.',
                );

                return ['status' => $existing->result_status, 'httpStatus' => $existing->result_status === 'conflict' ? 409 : 200, 'model' => $locked];
            }

            if ($resolution === 'use_server' || $resolution === 'keep_local_copy') {
                $status = $resolution === 'use_server' ? 'resolved_server' : 'resolved_device_copy';
                $this->recordOperation($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, $status);
                $this->auditResolution($user, $locked, $sectionKey, $resolution, $baseLockVersion);

                return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
            }

            if ($baseLockVersion !== $locked->getLockVersion($sectionKey)) {
                $this->recordOperation($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, 'conflict');

                return ['status' => 'conflict', 'httpStatus' => 409, 'model' => $locked];
            }

            if ($resolution === 'replace_server' && ! $confirmed) {
                abort(422, 'Replacing the server version requires explicit confirmation.');
            }

            $locked->applySyncedAttributes($sectionKey, $attributes, $locked->getLockVersion($sectionKey) + 1);

            $status = $resolution === 'replace_server' ? 'resolved_replaced' : 'saved';
            $this->recordOperation($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, $status);

            if ($resolution === 'replace_server') {
                $this->auditResolution($user, $locked, $sectionKey, $resolution, $baseLockVersion);
            }

            return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
        });
    }

    /** @param Model&Syncable $model */
    private function recordOperation(User $user, Model&Syncable $model, string $sectionKey, string $clientOperationId, int $baseLockVersion, string $status): SyncOperation
    {
        return SyncOperation::query()->create([
            'institution_id' => $model->getInstitutionId(),
            'user_id' => $user->id,
            'syncable_type' => $this->modelClassForSection($sectionKey),
            'syncable_id' => $model->getKey(),
            'client_operation_id' => $clientOperationId,
            'section_key' => $sectionKey,
            'base_lock_version' => $baseLockVersion,
            'result_status' => $status,
            'server_version' => $model->getLockVersion($sectionKey),
        ]);
    }

    /** @param Model&Syncable $model */
    private function auditResolution(User $user, Model&Syncable $model, string $sectionKey, string $resolution, int $baseLockVersion): void
    {
        $this->audit->record($user, $model, "{$sectionKey}.conflict_resolved", [
            'section_key' => $sectionKey,
            'resolution' => $resolution,
            'base_lock_version' => $baseLockVersion,
            'server_lock_version' => $model->getLockVersion($sectionKey),
        ]);
    }
}
```

Note on `SECTION_MODELS`/`modelClassForSection()`: this pair is intentionally the single source of truth for "which class backs which section", read by `sync()` above and, from Slice 2B, by `create()` and `delete()` too (both need the same governing map to validate a *creation* or *deletion* replay/consistency check, since a brand-new or about-to-be-removed row can't lean on an earlier `sync()` call to have already proven the mapping). Neither `create()` nor `delete()` accepts a caller-supplied "expected class" parameter — that was the original design's mistake (a caller could pass anything); both derive it internally via `$this->modelClassForSection($sectionKey)`, exactly as `sync()` does. 2B's Task 2 extends the `SECTION_MODELS` constant with `'vitals' => CaseVital::class`, `'investigations' => CaseInvestigation::class`, `'medications' => CaseMedication::class`; 2C's Tasks 1–5 add `'soap'`, `'clinical_activity_adr'`, `'clinical_activity_counselling'`, `'clinical_activities'`. Do not let this list drift out of sync with the section keys the controllers actually pass — Review Focus in each slice's plan calls this out.

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=SectionSyncServiceTest`
Expected: PASS (9 tests).

Run (PowerShell): `php artisan test --filter=SyncOperationsMigrationTest`
Expected: PASS (3 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 133 previous passed + 12 new — 145 passed / 2 skipped (147 total).

- [ ] **Step 11: Static analysis**

Run (PowerShell): `vendor\bin\phpstan analyse`
Expected: 0 errors. Fix any real type error the `Model&Syncable` intersection type surfaces rather than suppressing it.

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php app/Contracts/Syncable.php app/Models/Concerns/SyncsWithLockVersion.php app/Models/SyncOperation.php app/Models/ClinicalCase.php app/Services/SectionSyncService.php tests/Feature/SectionSyncServiceTest.php tests/Feature/SyncOperationsMigrationTest.php
git commit -m "feat: generalize the sync-spike protocol into a section-keyed Syncable contract and SectionSyncService, preserving existing sync_operations data"
```

---

## Task 3: Frontend outbox engine — `outboxStore.ts`, `useSectionSync.ts`, and the logout hook

**Revision:** The original draft built `clearSectionOutbox()` but never called it from anywhere, leaving the same class of privacy gap the accepted spike already closed for `CaseDraftNote`. This task now wires it into `UserMenuContent.vue`'s existing `handleLogout()` in the same commit that creates the store, alongside `clearCaseDraftStorage()`.

**Files:**
- Create: `resources/js/lib/outboxStore.ts`
- Create: `resources/js/composables/useSectionSync.ts`
- Modify: `resources/js/components/UserMenuContent.vue` (call `clearSectionOutbox()` on logout)

**Interfaces:**
- Produces: `StoredSection<T>`, `StoredSectionCopy<T>`, `sectionKey(userId, sectionKey, resourceId): string`, `getSection<T>(key)`, `putSection<T>(section)`, `deleteSection(key)`, `keepSectionCopy<T>(section)`, `listAllSections<T>(): Promise<StoredSection<T>[]>`, `clearSectionOutbox()` (in `outboxStore.ts`); `useSectionSync<T>(options): { payload, state, savedAt, online, baseLockVersion, conflict, confirmingReplace, deviceCopyKept, edit, resolveWithServer, keepDeviceCopy, replaceServer, retry }` (in `useSectionSync.ts`). Both are consumed for the first time by Task 4 (Case Profile section) and again by Task 5 (History & Diagnosis section) and every later Slice 2B/2C section; `listAllSections` is unused until 2B Task 2 wires it into offline-capable row creation, but is added here so the store's shape doesn't change mid-series; `baseLockVersion` is unused until 2B Task 2 wires it into row deletion's optimistic-concurrency check, added for the same reason. Per the Global Constraints, there is no JS unit-test runner in this repo; Task 4 is where these files are first exercised in a real page and manually verified in a browser.

This generalizes `resources/js/lib/caseDraftStore.ts` and the script block of `resources/js/pages/student/CaseDraftNote.vue`, which are both left untouched (the accepted SYNC-SPIKE-01 experiment keeps working exactly as before) except for the one-line addition to `UserMenuContent.vue`'s logout handler.

- [ ] **Step 1: Write `outboxStore.ts`**

```typescript
export type StoredSection<T = Record<string, unknown>> = {
    key: string;
    sectionKey: string;
    resourceId: string;
    userId: number;
    payload: T;
    baseLockVersion: number;
    clientOperationId: string;
    updatedAt: string;
};

export type StoredSectionCopy<T = Record<string, unknown>> = StoredSection<T> & {
    id: string;
    copiedAt: string;
};

const DATABASE_NAME = 'pharmalab-section-outbox';
const DATABASE_VERSION = 1;
let connection: Promise<IDBDatabase> | null = null;

function database(): Promise<IDBDatabase> {
    if (connection) return connection;

    connection = new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('sections')) {
                db.createObjectStore('sections', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('copies')) {
                db.createObjectStore('copies', { keyPath: 'id' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return connection;
}

function requestResult<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export const sectionKey = (userId: number, section: string, resourceId: string) =>
    `${userId}:${section}:${resourceId}`;

export async function getSection<T>(key: string) {
    const db = await database();
    return requestResult<StoredSection<T> | undefined>(
        db.transaction('sections').objectStore('sections').get(key),
    );
}

export async function putSection<T>(section: StoredSection<T>) {
    const db = await database();
    await requestResult(
        db.transaction('sections', 'readwrite').objectStore('sections').put(section),
    );
}

export async function deleteSection(key: string) {
    const db = await database();
    await requestResult(
        db.transaction('sections', 'readwrite').objectStore('sections').delete(key),
    );
}

export async function keepSectionCopy<T>(section: StoredSection<T>) {
    const db = await database();
    const copy: StoredSectionCopy<T> = {
        ...section,
        id: crypto.randomUUID(),
        copiedAt: new Date().toISOString(),
    };
    await requestResult(
        db.transaction('copies', 'readwrite').objectStore('copies').put(copy),
    );
    return copy;
}

/**
 * Used by Slice 2B's offline-capable row creation to find every queued "add
 * row" draft for the current user after reconnecting, since those drafts are
 * keyed by a client-generated local id the caller doesn't already know.
 */
export async function listAllSections<T>(): Promise<StoredSection<T>[]> {
    const db = await database();
    return requestResult<StoredSection<T>[]>(
        db.transaction('sections').objectStore('sections').getAll(),
    );
}

export async function clearSectionOutbox() {
    const db = await database();
    const transaction = db.transaction(['sections', 'copies'], 'readwrite');
    transaction.objectStore('sections').clear();
    transaction.objectStore('copies').clear();

    await new Promise<void>((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });

    db.close();
    connection = null;
}
```

- [ ] **Step 2: Write `useSectionSync.ts`**

```typescript
import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import {
    deleteSection,
    getSection,
    keepSectionCopy,
    putSection,
    sectionKey as buildSectionKey,
    type StoredSection,
} from '@/lib/outboxStore';

export type SyncState = 'server' | 'unsynced' | 'saving' | 'device' | 'failed' | 'conflict';

export type SyncedSection = { lock_version: number; updated_at: string };

export type SectionSyncOptions<T extends SyncedSection> = {
    userId: number;
    resourceId: string;
    sectionKey: string;
    endpoint: string;
    initialPayload: T;
    debounceMs?: number;
};

export function useSectionSync<T extends SyncedSection>(options: SectionSyncOptions<T>) {
    const storageKey = buildSectionKey(options.userId, options.sectionKey, options.resourceId);
    const payload = ref(structuredClone(options.initialPayload)) as Ref<T>;
    const baseLockVersion = ref(options.initialPayload.lock_version);
    const operationId = ref<string | null>(null);
    const state = ref<SyncState>('server');
    const savedAt = ref(new Date(options.initialPayload.updated_at));
    const online = ref(navigator.onLine);
    const conflict = ref<{ server: T; local: T } | null>(null);
    const confirmingReplace = ref(false);
    const deviceCopyKept = ref(false);
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;
    let syncing = false;

    function csrfToken(): string {
        return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    }

    function currentDraft(): StoredSection<T> | null {
        if (!operationId.value) return null;
        return {
            key: storageKey,
            sectionKey: options.sectionKey,
            resourceId: options.resourceId,
            userId: options.userId,
            payload: payload.value,
            baseLockVersion: baseLockVersion.value,
            clientOperationId: operationId.value,
            updatedAt: new Date().toISOString(),
        };
    }

    async function edit() {
        conflict.value = null;
        confirmingReplace.value = false;
        operationId.value = crypto.randomUUID();
        state.value = 'unsynced';
        const draft = currentDraft();
        if (draft) await putSection(draft);

        if (!online.value) {
            state.value = 'device';
            return;
        }

        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => void sync(), options.debounceMs ?? 700);
    }

    async function sync(
        resolution?: 'use_server' | 'keep_local_copy' | 'replace_server',
        confirmed = false,
    ) {
        if (syncing || !operationId.value) return;
        if (!online.value) {
            state.value = 'device';
            return;
        }

        syncing = true;
        state.value = 'saving';
        const sentOperation = operationId.value;
        const sentPayload = payload.value;
        const sentBaseVersion = baseLockVersion.value;

        try {
            const response = await fetch(options.endpoint, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    client_operation_id: sentOperation,
                    base_lock_version: sentBaseVersion,
                    ...sentPayload,
                    resolution,
                    confirmed,
                }),
            });

            if (response.status === 409) {
                const body = (await response.json()) as { section: T };
                if (operationId.value === sentOperation) {
                    conflict.value = { server: body.section, local: sentPayload };
                    state.value = 'conflict';
                }
                return;
            }
            if (!response.ok) throw new Error(`Sync failed with ${response.status}`);

            const body = (await response.json()) as { section: T };
            baseLockVersion.value = body.section.lock_version;
            savedAt.value = new Date(body.section.updated_at);

            if (operationId.value === sentOperation) {
                if (resolution === 'use_server' || resolution === 'keep_local_copy') {
                    payload.value = body.section;
                }
                operationId.value = null;
                conflict.value = null;
                confirmingReplace.value = false;
                await deleteSection(storageKey);
                state.value = 'server';
            } else {
                const newerDraft = currentDraft();
                if (newerDraft) {
                    newerDraft.baseLockVersion = body.section.lock_version;
                    await putSection(newerDraft);
                }
                state.value = 'unsynced';
                window.setTimeout(() => void sync(), 0);
            }
        } catch {
            state.value = online.value ? 'failed' : 'device';
        } finally {
            syncing = false;
        }
    }

    async function resolveWithServer() {
        if (!conflict.value) return;
        operationId.value = crypto.randomUUID();
        await putSection(currentDraft()!);
        await sync('use_server');
    }

    async function keepDeviceCopy() {
        const draft = currentDraft();
        if (!draft || !conflict.value) return;
        await keepSectionCopy(draft);
        deviceCopyKept.value = true;
        operationId.value = crypto.randomUUID();
        await putSection(currentDraft()!);
        await sync('keep_local_copy');
    }

    async function replaceServer() {
        if (!conflict.value) return;
        baseLockVersion.value = conflict.value.server.lock_version;
        operationId.value = crypto.randomUUID();
        await putSection(currentDraft()!);
        await sync('replace_server', true);
    }

    function retry() {
        void sync();
    }

    function handleOnline() {
        online.value = true;
        if (operationId.value && state.value !== 'conflict') void sync();
    }

    function handleOffline() {
        online.value = false;
        if (operationId.value) state.value = 'device';
    }

    onMounted(async () => {
        const local = await getSection<T>(storageKey);
        if (local) {
            payload.value = local.payload;
            baseLockVersion.value = local.baseLockVersion;
            operationId.value = local.clientOperationId;
            state.value = online.value ? 'unsynced' : 'device';
            if (online.value) void sync();
        }
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onBeforeUnmount(() => {
        window.clearTimeout(debounceTimer);
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return {
        payload,
        state: computed(() => state.value),
        savedAt,
        online: computed(() => online.value),
        // Exposed so a repeatable-row component's delete action (added in
        // Slice 2B) can send the row's current lock version in its DELETE
        // request body — deleting a row is subject to the same optimistic
        // concurrency check as editing one, so the caller needs the lock
        // version this composable is already tracking rather than
        // duplicating that state itself.
        baseLockVersion: computed(() => baseLockVersion.value),
        conflict,
        confirmingReplace,
        deviceCopyKept,
        edit,
        resolveWithServer,
        keepDeviceCopy,
        replaceServer,
        retry,
    };
}
```

- [ ] **Step 3: Wire `clearSectionOutbox()` into logout**

In `resources/js/components/UserMenuContent.vue`, add the import next to the existing `clearCaseDraftStorage` import:

```typescript
import { clearSectionOutbox } from '@/lib/outboxStore';
```

Update `handleLogout` to clear both stores before the request fires:

```typescript
const handleLogout = async () => {
    await clearCaseDraftStorage();
    await clearSectionOutbox();
    router.flushAll();
    router.post(logout.url());
};
```

- [ ] **Step 4: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors. (`@/lib/...` and `@/composables/...` aliases already resolve per the existing `tsconfig.json`/Vite alias used by `caseDraftStore.ts` imports elsewhere.)

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/outboxStore.ts resources/js/composables/useSectionSync.ts resources/js/components/UserMenuContent.vue
git commit -m "feat: add a generalized IndexedDB outbox and section-sync composable, and clear it on logout"
```

---

## Task 4: Case Profile section — request, controller, route, Vue component (with generated Case ID, derived rotation/site/ward, and unit-dependent age validation)

**Revision:** The original draft's `CaseProfileSection.vue` never displayed the field catalogue's mandatory "Educational Case ID" or "Rotation, site and ward/unit" fields (§4.1 — both already exist as data on `ClinicalCase`/`RotationAssignment`, they were simply never surfaced), and validated `age_value` against a flat `max:150` regardless of `age_unit`, which is wrong for an age recorded in days or months. This revision adds a read-only case-identity block to the payload/component and replaces the flat age bound with a closure rule keyed on `age_unit`.

**Files:**
- Create: `app/Http/Requests/Concerns/HasSyncEnvelope.php`
- Create: `app/Http/Requests/Concerns/RejectsUnknownFields.php`
- Create: `app/Http/Requests/Student/UpdateClinicalCaseContextRequest.php`
- Create: `app/Http/Controllers/Student/CaseContextController.php`
- Modify: `routes/web.php` (add the `sync` route)
- Create: `resources/js/pages/student/case-editor/CaseProfileSection.vue`
- Test: `tests/Feature/CaseContextSyncTest.php`

**Interfaces:**
- Consumes: `App\Services\SectionSyncService::sync()` (Task 2), `useSectionSync` + `outboxStore` (Task 3).
- Produces: `HasSyncEnvelope::syncEnvelopeRules(): array`, `HasSyncEnvelope::syncEnvelope(): array{client_operation_id: string, base_lock_version: int, resolution: string|null, confirmed: bool}`, `HasSyncEnvelope::sectionData(): array` (validated data minus the four envelope keys) — reused by every request class this plan and 2B/2C add. `RejectsUnknownFields::withValidator(Validator $validator): void` — a `FormRequest` hook (Laravel calls `withValidator()` automatically if the request class defines it) that fails validation if the payload contains a top-level key not present in `rules()` (after stripping `.*` wildcard suffixes) — reused the same way, and directly implements field catalogue §5's "Unknown request fields are rejected by the server". `PUT /student/cases/{case}/context` returning `{ "section": { ...case-context fields, case_display: {...read-only}, lock_version, updated_at } }`. `CaseProfileSection.vue` — a `<script setup>` component taking `caseId: string`, `userId: number`, `initial: CaseContextPayload` as props, consumed by Task 6's `CaseEditor.vue`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseContextSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_can_sync_case_profile_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'inpatient',
            'information_source' => 'case sheet',
            'weight_kg' => 65.25,
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.care_setting', 'inpatient');
        $response->assertJsonPath('section.lock_version', 1);
        $this->assertSame('inpatient', $case->fresh()->care_setting);
        $this->assertSame('case sheet', $case->fresh()->information_source);
    }

    public function test_the_response_includes_the_generated_case_id_and_derived_rotation_site_and_ward(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'inpatient',
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.case_display.case_number', $case->case_number);
    }

    public function test_a_single_field_payload_does_not_touch_other_saved_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'inpatient',
            'information_source' => 'case sheet',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 1,
            'weight_kg' => 70,
        ]);

        $response->assertOk();
        $fresh = $case->fresh();
        $this->assertSame('inpatient', $fresh->care_setting);
        $this->assertSame('case sheet', $fresh->information_source);
        $this->assertSame('70.00', $fresh->weight_kg);
    }

    public function test_age_in_days_rejects_a_value_over_364(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'age_value' => 400, 'age_unit' => 'days',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('age_value');
    }

    public function test_age_in_years_accepts_a_value_over_150_days_bound_but_rejects_over_120(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'age_value' => 118, 'age_unit' => 'years',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'age_value' => 130, 'age_unit' => 'years',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('age_value');
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'care_setting' => 'inpatient', 'patient_name' => 'Should be rejected',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_name');
    }

    public function test_a_stale_base_lock_version_returns_a_409_conflict(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'inpatient',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'outpatient',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('section.care_setting', 'inpatient');
    }

    public function test_replaying_a_client_operation_id_does_not_double_apply(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $operationId = (string) Str::uuid();

        $payload = ['client_operation_id' => $operationId, 'base_lock_version' => 0, 'care_setting' => 'inpatient'];
        $this->putJson("/student/cases/{$case->id}/context", $payload)->assertOk();
        $this->putJson("/student/cases/{$case->id}/context", $payload)->assertOk();

        $this->assertSame(1, $case->fresh()->lock_version);
        $this->assertSame(1, SyncOperation::query()->where('client_operation_id', $operationId)->count());
    }

    public function test_a_different_student_cannot_sync_the_case_profile(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $response = $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'care_setting' => 'inpatient',
        ]);

        $response->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=CaseContextSyncTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Write the `HasSyncEnvelope` trait**

```php
<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Arr;

trait HasSyncEnvelope
{
    /** @return array<string, mixed> */
    protected function syncEnvelopeRules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'base_lock_version' => ['required', 'integer', 'min:0'],
            'resolution' => ['nullable', 'in:use_server,keep_local_copy,replace_server'],
            'confirmed' => ['boolean'],
        ];
    }

    /** @return array{client_operation_id: string, base_lock_version: int, resolution: string|null, confirmed: bool} */
    public function syncEnvelope(): array
    {
        return [
            'client_operation_id' => (string) $this->input('client_operation_id'),
            'base_lock_version' => (int) $this->input('base_lock_version'),
            'resolution' => $this->input('resolution'),
            'confirmed' => (bool) $this->boolean('confirmed'),
        ];
    }

    /** @return array<string, mixed> */
    public function sectionData(): array
    {
        return Arr::except($this->validated(), ['client_operation_id', 'base_lock_version', 'resolution', 'confirmed']);
    }
}
```

- [ ] **Step 4: Write the `RejectsUnknownFields` trait**

**Revision (second review round):** The first draft named this method `withValidator()`, the exact hook name Laravel calls automatically — which meant any request that also needed its own `withValidator()` for a different check would silently *override* the trait's version and skip unknown-field rejection entirely, undetected until an unknown field slipped through in production. This trait now exposes a plain, differently-named method. **Every** request using this trait defines its own `withValidator()` and calls `$this->rejectUnknownFields($validator);` as its first statement — an explicit, auditable call site in each class, never an implicit hook.

```php
<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

/**
 * Field catalogue §5 ("Unknown request fields are rejected by the server") is
 * a spec requirement, not an optional hardening choice. Every sync/store
 * request in this plan and Slices 2B/2C uses this trait alongside
 * HasSyncEnvelope and calls rejectUnknownFields() explicitly from its own
 * withValidator() — deliberately NOT named withValidator() itself, so a
 * request that also needs a withValidator() for some other check (an
 * age/unit bound, a BP-pairing rule, an "unavailable while rows exist"
 * check, ...) can never silently skip this one by defining its own hook.
 *
 * This method only rejects TOP-LEVEL unknown keys. It does not inspect the
 * keys inside an array/object-valued field, so a field whose value is
 * itself a JSON object with a restricted key set (e.g.
 * case_clinical_activities.details in Slice 2C) must ALSO declare a
 * Laravel `array:key1,key2,...` rule listing exactly the allowed nested
 * keys — otherwise an unknown nested key would pass this top-level check
 * and be persisted silently.
 */
trait RejectsUnknownFields
{
    protected function rejectUnknownFields(Validator $validator): void
    {
        $allowedKeys = collect(array_keys($this->rules()))
            ->map(fn (string $key): string => Str::before($key, '.*'))
            ->map(fn (string $key): string => explode('.', $key)[0])
            ->unique()
            ->all();

        $providedKeys = array_keys($this->all());
        $unknown = array_diff($providedKeys, $allowedKeys);

        if ($unknown === []) {
            return;
        }

        $validator->after(function (Validator $validator) use ($unknown): void {
            foreach ($unknown as $key) {
                $validator->errors()->add($key, "The {$key} field is not recognized.");
            }
        });
    }
}
```

- [ ] **Step 5: Write `UpdateClinicalCaseContextRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClinicalCaseContextRequest extends FormRequest
{
    use HasSyncEnvelope, RejectsUnknownFields;

    private const AGE_MAX_BY_UNIT = ['days' => 364, 'months' => 59, 'years' => 120];

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->syncEnvelopeRules(),
            'encounter_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'case_category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'age_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'age_unit' => ['sometimes', 'nullable', Rule::in(['days', 'months', 'years'])],
            'sex' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'intersex', 'unknown'])],
            'care_setting' => ['sometimes', 'nullable', Rule::in(['inpatient', 'outpatient', 'emergency', 'other'])],
            'hospital_day_at_first_review' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'information_source' => ['sometimes', 'nullable', 'string', 'max:60'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:300'],
            'pregnancy_lactation_status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Age has no single universal maximum — 364 in days, 59 in months (the
     * common paediatric convention of expressing under-5 ages in months) and
     * 120 in years are each independently plausible; a flat max:150 applied
     * to a value recorded in days would accept an impossible "150-day-old
     * patient is really 150 years old" typo class of error. This can't be a
     * static rule because the bound depends on the sibling age_unit field.
     */
    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->has('age_value') || $this->input('age_value') === null) {
                return;
            }

            $unit = $this->input('age_unit');
            $max = self::AGE_MAX_BY_UNIT[$unit] ?? null;

            if ($max !== null && (int) $this->input('age_value') > $max) {
                $validator->errors()->add('age_value', "The age value must not exceed {$max} when the unit is {$unit}.");
            }
        });
    }
}
```

- [ ] **Step 6: Write `CaseContextController`**

```php
<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateClinicalCaseContextRequest;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class CaseContextController extends Controller
{
    public function sync(UpdateClinicalCaseContextRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $case,
            $request->user(),
            'case_context',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['section' => $this->payload($result['model'])], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(ClinicalCase $case): array
    {
        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);

        return [
            'encounter_date' => $case->encounter_date?->toDateString(),
            'case_category' => $case->case_category,
            'age_value' => $case->age_value,
            'age_unit' => $case->age_unit,
            'sex' => $case->sex,
            'care_setting' => $case->care_setting,
            'hospital_day_at_first_review' => $case->hospital_day_at_first_review,
            'information_source' => $case->information_source,
            'weight_kg' => $case->weight_kg,
            'height_cm' => $case->height_cm,
            'pregnancy_lactation_status' => $case->pregnancy_lactation_status,
            'case_display' => [
                'case_number' => $case->case_number,
                'rotation_name' => $case->rotationAssignment?->rotation?->name,
                'clinical_site_name' => $case->clinicalSite?->name,
                'ward_name' => $case->ward?->name,
            ],
            'lock_version' => $case->lock_version,
            'updated_at' => $case->updated_at->toIso8601String(),
        ];
    }
}
```

Note: `case_display` is a read-only block — the field catalogue marks Educational Case ID and rotation/site/ward as "Generated" / "Derived from the authorized rotation assignment" (§4.1), never student-editable, so it is never part of `UpdateClinicalCaseContextRequest::rules()` and `RejectsUnknownFields` would reject it if a client ever tried to send it back in a sync payload — it only ever flows server → client.

- [ ] **Step 7: Add the route**

In `routes/web.php`, add inside the existing `role:student` group, immediately after the `student.cases.show` route:

```php
        Route::put('student/cases/{case}/context', [CaseContextController::class, 'sync'])->name('student.cases.context.sync');
```

Add the import near the other `Student` controller imports:

```php
use App\Http\Controllers\Student\CaseContextController;
```

- [ ] **Step 8: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`
This regenerates `resources/js/actions/App/Http/Controllers/Student/CaseContextController.ts` and the matching route file. Confirm with `git status` that only Wayfinder files plus your own new files changed.

- [ ] **Step 9: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseContextSyncTest`
Expected: PASS (9 tests).

- [ ] **Step 10: Write the Case Profile Vue section component**

```vue
<script setup lang="ts">
import { RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

type CaseDisplay = {
    case_number: number;
    rotation_name: string | null;
    clinical_site_name: string | null;
    ward_name: string | null;
};

type CaseContextPayload = SyncedSection & {
    encounter_date: string | null;
    case_category: string | null;
    age_value: number | null;
    age_unit: string | null;
    sex: string | null;
    care_setting: string | null;
    hospital_day_at_first_review: number | null;
    information_source: string | null;
    weight_kg: string | null;
    height_cm: string | null;
    pregnancy_lactation_status: string | null;
    case_display: CaseDisplay;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: CaseContextPayload;
}>();

const { payload, state, edit, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace } =
    useSectionSync<CaseContextPayload>({
        userId: props.userId,
        resourceId: props.caseId,
        sectionKey: 'case_context',
        endpoint: `/student/cases/${props.caseId}/context`,
        initialPayload: props.initial,
    });

const statusLabel = computed(() => ({
    saving: 'Saving…',
    server: 'Saved',
    device: 'Saved on this device',
    unsynced: 'Unsynced changes',
    failed: 'Sync failed',
    conflict: 'Conflict — review changes',
})[state.value]);

const ageMaxByUnit: Record<string, number> = { days: 364, months: 59, years: 120 };
const ageMax = computed(() => (payload.value.age_unit ? ageMaxByUnit[payload.value.age_unit] : undefined));
</script>

<template>
    <section aria-labelledby="case-profile-heading" class="space-y-5">
        <div class="flex items-center justify-between">
            <h2 id="case-profile-heading" class="font-display text-lg text-[#0b2942] dark:text-white">Case Profile</h2>
            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-test="case-profile-status">
                <component :is="state === 'saving' ? RefreshCw : state === 'server' ? Check : state === 'device' ? CloudOff : state === 'unsynced' ? FileClock : AlertTriangle" class="size-3.5" :class="state === 'saving' ? 'animate-spin' : ''" />
                {{ statusLabel }}
            </span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60" data-test="case-display">
            <p class="font-bold text-slate-700 dark:text-slate-200">Educational Case ID: #{{ payload.case_display.case_number }}</p>
            <p class="mt-1 text-slate-500">
                {{ payload.case_display.rotation_name ?? 'Rotation not assigned' }} ·
                {{ payload.case_display.clinical_site_name ?? 'Site not assigned' }} ·
                {{ payload.case_display.ward_name ?? 'Ward not assigned' }}
            </p>
            <p class="mt-1 text-xs text-slate-400">Generated and read-only. Never a hospital identifier.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Care setting</span>
                <select v-model="payload.care_setting" data-test="care-setting" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option :value="null">Select…</option>
                    <option value="inpatient">Inpatient</option>
                    <option value="outpatient">Outpatient</option>
                    <option value="emergency">Emergency</option>
                    <option value="other">Other</option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Case documentation date</span>
                <input v-model="payload.encounter_date" type="date" data-test="encounter-date" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit" />
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Information source</span>
                <input v-model="payload.information_source" type="text" maxlength="60" data-test="information-source" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Hospital day at first review</span>
                <input v-model.number="payload.hospital_day_at_first_review" type="number" min="1" max="999" data-test="hospital-day" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Age</span>
                    <input v-model.number="payload.age_value" type="number" min="0" :max="ageMax" data-test="age-value" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Unit</span>
                    <select v-model="payload.age_unit" data-test="age-unit" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit">
                        <option :value="null">—</option>
                        <option value="days">Days</option>
                        <option value="months">Months</option>
                        <option value="years">Years</option>
                    </select>
                </label>
            </div>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Sex as recorded</span>
                <select v-model="payload.sex" data-test="sex" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option :value="null">Select…</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="intersex">Intersex</option>
                    <option value="unknown">Unknown</option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Weight (kg)</span>
                <input v-model.number="payload.weight_kg" type="number" step="0.01" min="0" max="500" data-test="weight-kg" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Height (cm)</span>
                <input v-model.number="payload.height_cm" type="number" step="0.01" min="0" max="300" data-test="height-cm" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>

            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Pregnancy/lactation status (if relevant)</span>
                <input v-model="payload.pregnancy_lactation_status" type="text" maxlength="30" data-test="pregnancy-lactation-status" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>

        <section v-if="conflict" data-test="case-profile-conflict" class="rounded-2xl border border-rose-200 bg-white p-4 dark:border-rose-900 dark:bg-slate-900">
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

- [ ] **Step 11: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 12: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 145 previous passed + 9 new — 154 passed / 2 skipped (156 total).

- [ ] **Step 13: Commit**

```bash
git add app/Http/Requests/Concerns/HasSyncEnvelope.php app/Http/Requests/Concerns/RejectsUnknownFields.php app/Http/Requests/Student/UpdateClinicalCaseContextRequest.php app/Http/Controllers/Student/CaseContextController.php routes/web.php resources/js/actions resources/js/routes resources/js/pages/student/case-editor/CaseProfileSection.vue tests/Feature/CaseContextSyncTest.php
git commit -m "feat: add partial-autosave Case Profile section with generated case ID, derived rotation/site/ward, and unit-dependent age bounds"
```

---

## Task 5: History & Diagnosis section — partial validation fix, full field set, controller, route, Vue component

**Revision:** `UpdateCaseClinicalProfileRequest` already validated `past_surgical_history`, `adherence_status`, `family_history`, `substance_history` and `examination_findings` in the original draft, but `HistoryDiagnosisSection.vue` never rendered inputs for any of them — a student could never actually enter this data through the UI the plan shipped. This revision adds those five fields to the component (and to the controller's read/write payload, which also silently omitted them). It also applies `DeidentificationNotice` to every narrative field in this section, not only History of present illness, and clears `allergy_substance`/`allergy_reaction` server-side whenever `allergy_status` moves away from `known_allergy` in the same request (previously a student could set Known allergy → substance → then flip back to No known allergy and the stale substance/reaction would silently remain in the database, contradicting the visible UI).

**Files:**
- Modify: `app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php` (add `'sometimes'` to every field, add sync envelope + unknown-field rejection)
- Create: `app/Http/Controllers/Student/CaseClinicalProfileController.php`
- Modify: `app/Models/CaseClinicalProfile.php` (implement `Syncable`)
- Modify: `routes/web.php` (add the `sync` route)
- Create: `resources/js/lib/deidentification.ts`
- Create: `resources/js/components/DeidentificationNotice.vue`
- Create: `resources/js/pages/student/case-editor/HistoryDiagnosisSection.vue`
- Test: `tests/Feature/CaseClinicalProfileSyncTest.php`

**Interfaces:**
- Consumes: `HasSyncEnvelope`, `RejectsUnknownFields` (Task 4), `SectionSyncService` (Task 2), `useSectionSync` (Task 3).
- Produces: `PUT /student/cases/{case}/clinical-profile` returning `{ "section": {...profile fields, lock_version, updated_at} }`; creates the `CaseClinicalProfile` row lazily on the first deliberate sync, never on page render. `detectPotentialIdentifiers(text: string): string[]`, `<DeidentificationNotice :text="..." />` — reused by every narrative field in this section and by every later Slice 2B/2C section with free text (medication notes, SOAP Subjective, ADR event, counselling notes).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseClinicalProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_profile_row_exists_until_the_first_deliberate_sync(): void
    {
        [, , $case] = $this->makeCase();

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_first_sync_lazily_creates_the_profile_and_persists_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'history_present_illness' => 'Three-day history of headache.',
            'allergy_status' => 'no_known_allergy',
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.allergy_status', 'no_known_allergy');
        $response->assertJsonPath('section.lock_version', 1);
        $this->assertNotNull($case->fresh()->clinicalProfile);
        $this->assertSame('Three-day history of headache.', $case->fresh()->clinicalProfile->history_present_illness);
    }

    public function test_a_single_field_payload_does_not_require_allergy_status_or_erase_it(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'allergy_status' => 'known_allergy',
            'allergy_substance' => 'Penicillin',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 1,
            'past_medical_history' => 'Type 2 diabetes mellitus, 5 years.',
        ]);

        $response->assertOk();
        $fresh = $case->fresh()->clinicalProfile;
        $this->assertSame('known_allergy', $fresh->allergy_status);
        $this->assertSame('Penicillin', $fresh->allergy_substance);
        $this->assertSame('Type 2 diabetes mellitus, 5 years.', $fresh->past_medical_history);
    }

    public function test_a_single_field_payload_persists_the_previously_missing_history_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'past_surgical_history' => 'Appendicectomy, 2019.',
            'adherence_status' => 'adherent',
            'family_history' => 'Father: hypertension.',
            'substance_history' => 'No tobacco or alcohol use reported.',
            'examination_findings' => 'Alert, oriented, no acute distress (ward-round discussion).',
        ]);

        $fresh = $case->fresh()->clinicalProfile;
        $this->assertSame('Appendicectomy, 2019.', $fresh->past_surgical_history);
        $this->assertSame('adherent', $fresh->adherence_status);
        $this->assertSame('Father: hypertension.', $fresh->family_history);
        $this->assertSame('No tobacco or alcohol use reported.', $fresh->substance_history);
        $this->assertSame('Alert, oriented, no acute distress (ward-round discussion).', $fresh->examination_findings);
    }

    public function test_allergy_substance_is_required_when_allergy_status_is_known_allergy_in_the_same_request(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'allergy_status' => 'known_allergy',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('allergy_substance');
    }

    public function test_moving_allergy_status_away_from_known_allergy_clears_the_conditional_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'allergy_status' => 'known_allergy', 'allergy_substance' => 'Penicillin', 'allergy_reaction' => 'Rash',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 1,
            'allergy_status' => 'no_known_allergy',
        ])->assertOk();

        $fresh = $case->fresh()->clinicalProfile;
        $this->assertSame('no_known_allergy', $fresh->allergy_status);
        $this->assertNull($fresh->allergy_substance);
        $this->assertNull($fresh->allergy_reaction);
    }

    public function test_rendering_the_editor_page_never_creates_a_profile_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->get("/student/cases/{$case->id}");

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'allergy_status' => 'unknown', 'patient_mrn' => '12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('patient_mrn');
    }

    public function test_a_different_student_cannot_sync_the_clinical_profile(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'allergy_status' => 'unknown',
        ]);

        $response->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell): `php artisan test --filter=CaseClinicalProfileSyncTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Fix `UpdateCaseClinicalProfileRequest` for partial autosave and unknown-field rejection**

Replace the full contents of `app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCaseClinicalProfileRequest extends FormRequest
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
            'chief_complaints' => ['sometimes', 'nullable', 'array'],
            'chief_complaints.*.complaint' => ['required_with:chief_complaints', 'string', 'max:255'],
            'chief_complaints.*.duration' => ['nullable', 'string', 'max:60'],
            'history_present_illness' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diagnoses' => ['sometimes', 'nullable', 'array'],
            'diagnoses.*.label' => ['required_with:diagnoses', 'string', 'max:255'],
            'diagnoses.*.type' => ['nullable', Rule::in(['provisional', 'confirmed', 'comorbidity'])],
            'past_medical_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'past_medical_history_none' => ['sometimes', 'boolean'],
            'past_surgical_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'adherence_status' => ['sometimes', 'nullable', Rule::in(['adherent', 'partially_adherent', 'non_adherent', 'unable_to_assess'])],
            'family_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'substance_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'examination_findings' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'allergy_status' => ['sometimes', 'required', Rule::in(['no_known_allergy', 'known_allergy', 'unknown'])],
            'allergy_substance' => ['sometimes', 'nullable', 'required_if:allergy_status,known_allergy', 'string', 'max:1000'],
            'allergy_reaction' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 4: Make `CaseClinicalProfile` implement `Syncable`**

In `app/Models/CaseClinicalProfile.php`, add imports and apply the trait/interface exactly as Task 2 did for `ClinicalCase` (this model backs exactly one section, so it does **not** override `lockVersionColumn()` — the trait's single-column default is correct):

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

```php
class CaseClinicalProfile extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

- [ ] **Step 5: Write `CaseClinicalProfileController`**

```php
<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateCaseClinicalProfileRequest;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class CaseClinicalProfileController extends Controller
{
    public function sync(UpdateCaseClinicalProfileRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $profile = CaseClinicalProfile::query()->firstOrCreate(
            ['clinical_case_id' => $case->id],
            ['institution_id' => $case->institution_id, 'last_saved_by' => $request->user()->id],
        );

        $envelope = $request->syncEnvelope();
        $data = $request->sectionData();

        // Allergy conditional fields must never silently outlive their status:
        // a student who records substance/reaction for "known_allergy" and
        // then changes the answer must not leave stale substance/reaction
        // data behind, invisible in a UI that now shows neither field.
        if (array_key_exists('allergy_status', $data) && $data['allergy_status'] !== 'known_allergy') {
            $data['allergy_substance'] = null;
            $data['allergy_reaction'] = null;
        }

        $result = $sync->sync(
            $profile,
            $request->user(),
            'clinical_profile',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$data, 'last_saved_by' => $request->user()->id],
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['section' => $this->payload($result['model'])], $result['httpStatus']);
    }

    /** @return array<string, mixed> */
    private function payload(CaseClinicalProfile $profile): array
    {
        return [
            'chief_complaints' => $profile->chief_complaints,
            'history_present_illness' => $profile->history_present_illness,
            'diagnoses' => $profile->diagnoses,
            'past_medical_history' => $profile->past_medical_history,
            'past_medical_history_none' => $profile->past_medical_history_none,
            'past_surgical_history' => $profile->past_surgical_history,
            'adherence_status' => $profile->adherence_status,
            'family_history' => $profile->family_history,
            'substance_history' => $profile->substance_history,
            'examination_findings' => $profile->examination_findings,
            'allergy_status' => $profile->allergy_status,
            'allergy_substance' => $profile->allergy_substance,
            'allergy_reaction' => $profile->allergy_reaction,
            'lock_version' => $profile->lock_version,
            'updated_at' => $profile->updated_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add immediately after `student.cases.context.sync`:

```php
        Route::put('student/cases/{case}/clinical-profile', [CaseClinicalProfileController::class, 'sync'])->name('student.cases.clinical-profile.sync');
```

Add the import:

```php
use App\Http\Controllers\Student\CaseClinicalProfileController;
```

- [ ] **Step 7: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 8: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseClinicalProfileSyncTest`
Expected: PASS (9 tests).

- [ ] **Step 9: Write the de-identification helper**

```typescript
type IdentifierPattern = { label: string; regex: RegExp };

const PATTERNS: IdentifierPattern[] = [
    { label: 'email address', regex: /[\w.+-]+@[\w-]+\.[a-zA-Z]{2,}/ },
    { label: 'phone number', regex: /(?:\+?91[\s-]?)?[6-9]\d{9}\b/ },
    { label: 'long identification number (record/Aadhaar-style)', regex: /\b\d{6,}\b/ },
];

export function detectPotentialIdentifiers(text: string | null | undefined): string[] {
    if (!text) return [];
    const found = new Set<string>();
    for (const { label, regex } of PATTERNS) {
        if (regex.test(text)) found.add(label);
    }
    return Array.from(found);
}
```

- [ ] **Step 10: Write the notice component**

```vue
<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { detectPotentialIdentifiers } from '@/lib/deidentification';

const props = defineProps<{ text: string | null | undefined }>();

const warnings = computed(() => detectPotentialIdentifiers(props.text));
</script>

<template>
    <p
        v-if="warnings.length"
        role="alert"
        data-test="deidentification-warning"
        class="mt-1 flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400"
    >
        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
        <span>
            This text may contain a {{ warnings.join(' or ') }}. De-identified case notes must not include patient
            names, record numbers, phone numbers or contact details.
        </span>
    </p>
</template>
```

- [ ] **Step 11: Write the History & Diagnosis Vue section component (now with every history field the request already validates)**

```vue
<script setup lang="ts">
import { Plus, Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type ChiefComplaint = { complaint: string; duration: string | null };
type Diagnosis = { label: string; type: 'provisional' | 'confirmed' | 'comorbidity' | null };

type ClinicalProfilePayload = SyncedSection & {
    chief_complaints: ChiefComplaint[] | null;
    history_present_illness: string | null;
    diagnoses: Diagnosis[] | null;
    past_medical_history: string | null;
    past_medical_history_none: boolean;
    past_surgical_history: string | null;
    adherence_status: string | null;
    family_history: string | null;
    substance_history: string | null;
    examination_findings: string | null;
    allergy_status: string;
    allergy_substance: string | null;
    allergy_reaction: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: ClinicalProfilePayload;
}>();

const { payload, state, edit, conflict, resolveWithServer, keepDeviceCopy, replaceServer, retry, confirmingReplace } =
    useSectionSync<ClinicalProfilePayload>({
        userId: props.userId,
        resourceId: props.caseId,
        sectionKey: 'clinical_profile',
        endpoint: `/student/cases/${props.caseId}/clinical-profile`,
        initialPayload: props.initial,
    });

const statusLabel = computed(() => ({
    saving: 'Saving…',
    server: 'Saved',
    device: 'Saved on this device',
    unsynced: 'Unsynced changes',
    failed: 'Sync failed',
    conflict: 'Conflict — review changes',
})[state.value]);

function addComplaint() {
    payload.value.chief_complaints = [...(payload.value.chief_complaints ?? []), { complaint: '', duration: null }];
    edit();
}
function removeComplaint(index: number) {
    payload.value.chief_complaints = (payload.value.chief_complaints ?? []).filter((_, i) => i !== index);
    edit();
}
function addDiagnosis() {
    payload.value.diagnoses = [...(payload.value.diagnoses ?? []), { label: '', type: 'provisional' }];
    edit();
}
function removeDiagnosis(index: number) {
    payload.value.diagnoses = (payload.value.diagnoses ?? []).filter((_, i) => i !== index);
    edit();
}
</script>

<template>
    <section aria-labelledby="history-diagnosis-heading" class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 id="history-diagnosis-heading" class="font-display text-lg text-[#0b2942] dark:text-white">History &amp; Diagnosis</h2>
            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-500" data-test="history-diagnosis-status">
                <component :is="state === 'saving' ? RefreshCw : state === 'server' ? Check : state === 'device' ? CloudOff : state === 'unsynced' ? FileClock : AlertTriangle" class="size-3.5" :class="state === 'saving' ? 'animate-spin' : ''" />
                {{ statusLabel }}
            </span>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Chief complaints</h3>
            <div v-for="(complaint, index) in payload.chief_complaints ?? []" :key="index" class="mb-2 flex gap-2">
                <label class="flex-1 text-sm">
                    <span class="sr-only">Complaint {{ index + 1 }}</span>
                    <input v-model="complaint.complaint" type="text" maxlength="255" placeholder="Complaint" :data-test="`chief-complaint-${index}`" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                </label>
                <label class="w-32 text-sm">
                    <span class="sr-only">Duration for complaint {{ index + 1 }}</span>
                    <input v-model="complaint.duration" type="text" maxlength="60" placeholder="Duration" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                </label>
                <button type="button" aria-label="Remove complaint" class="rounded-xl border px-2" @click="removeComplaint(index)"><Trash2 class="size-4" /></button>
            </div>
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addComplaint"><Plus class="size-4" /> Add complaint</button>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">History of present illness</span>
            <textarea v-model="payload.history_present_illness" rows="5" maxlength="5000" data-test="history-present-illness" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.history_present_illness" />
        </label>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Diagnosis / active problem</h3>
            <div v-for="(diagnosis, index) in payload.diagnoses ?? []" :key="index" class="mb-2 flex gap-2">
                <label class="flex-1 text-sm">
                    <span class="sr-only">Diagnosis {{ index + 1 }}</span>
                    <input v-model="diagnosis.label" type="text" maxlength="255" placeholder="Diagnosis" :data-test="`diagnosis-${index}`" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                </label>
                <label class="text-sm">
                    <span class="sr-only">Type for diagnosis {{ index + 1 }}</span>
                    <select v-model="diagnosis.type" class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit">
                        <option value="provisional">Provisional</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="comorbidity">Comorbidity</option>
                    </select>
                </label>
                <button type="button" aria-label="Remove diagnosis" class="rounded-xl border px-2" @click="removeDiagnosis(index)"><Trash2 class="size-4" /></button>
            </div>
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addDiagnosis"><Plus class="size-4" /> Add diagnosis</button>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Past medical history</span>
            <textarea v-model="payload.past_medical_history" rows="3" maxlength="5000" :disabled="payload.past_medical_history_none" data-test="past-medical-history" class="w-full rounded-xl border border-slate-200 px-3 py-2 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input v-model="payload.past_medical_history_none" type="checkbox" data-test="past-medical-history-none" @change="edit" />
            None known / not available
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Past surgical history (optional)</span>
            <textarea v-model="payload.past_surgical_history" rows="2" maxlength="5000" data-test="past-surgical-history" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <fieldset>
            <legend class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Adherence status</legend>
            <div class="flex flex-wrap gap-3 text-sm">
                <label v-for="option in ['adherent', 'partially_adherent', 'non_adherent', 'unable_to_assess']" :key="option" class="flex items-center gap-1.5">
                    <input v-model="payload.adherence_status" type="radio" :value="option" :data-test="`adherence-status-${option}`" @change="edit" />
                    {{ option.replace(/_/g, ' ') }}
                </label>
            </div>
        </fieldset>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Family history (optional)</span>
            <textarea v-model="payload.family_history" rows="2" maxlength="5000" data-test="family-history" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.family_history" />
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Tobacco/alcohol/substance history (optional)</span>
            <textarea v-model="payload.substance_history" rows="2" maxlength="5000" data-test="substance-history" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Relevant examination findings (optional; note the source)</span>
            <textarea v-model="payload.examination_findings" rows="3" maxlength="5000" data-test="examination-findings" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            <DeidentificationNotice :text="payload.examination_findings" />
        </label>

        <fieldset>
            <legend class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Allergy status</legend>
            <div class="flex flex-wrap gap-3">
                <label v-for="option in ['no_known_allergy', 'known_allergy', 'unknown']" :key="option" class="flex items-center gap-1.5 text-sm">
                    <input v-model="payload.allergy_status" type="radio" :value="option" :data-test="`allergy-status-${option}`" @change="edit" />
                    {{ option.replace(/_/g, ' ') }}
                </label>
            </div>
        </fieldset>

        <div v-if="payload.allergy_status === 'known_allergy'" class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Allergy substance</span>
                <input v-model="payload.allergy_substance" type="text" maxlength="1000" data-test="allergy-substance" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Reaction</span>
                <input v-model="payload.allergy_reaction" type="text" maxlength="1000" data-test="allergy-reaction" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
            </label>
        </div>

        <section v-if="conflict" data-test="history-diagnosis-conflict" class="rounded-2xl border border-rose-200 bg-white p-4 dark:border-rose-900 dark:bg-slate-900">
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

- [ ] **Step 12: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 13: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 154 previous passed + 9 new — 163 passed / 2 skipped (165 total).

- [ ] **Step 14: Commit**

```bash
git add app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php app/Http/Controllers/Student/CaseClinicalProfileController.php app/Models/CaseClinicalProfile.php routes/web.php resources/js/actions resources/js/routes resources/js/lib/deidentification.ts resources/js/components/DeidentificationNotice.vue resources/js/pages/student/case-editor/HistoryDiagnosisSection.vue tests/Feature/CaseClinicalProfileSyncTest.php
git commit -m "feat: add the full partial-autosave History & Diagnosis section, clearing stale allergy fields on status change"
```

---

## Task 6: Mobile section-editor shell

**Files:**
- Create: `app/Http/Controllers/Student/CaseEditorController.php`
- Modify: `routes/web.php` (add the `edit` route)
- Create: `resources/js/pages/student/CaseEditor.vue`
- Modify: `resources/js/pages/student/CaseShow.vue` (add an entry-point button)
- Test: `tests/Feature/CaseEditorPageTest.php`

**Interfaces:**
- Consumes: `CaseProfileSection.vue` (Task 4), `HistoryDiagnosisSection.vue` (Task 5).
- Produces: `GET /student/cases/{case}/edit` rendering `student/CaseEditor` with props `{ clinicalCase, context, clinicalProfile, userId }`. Section list/progress/nav pattern consumed by Slice 2B and 2C, which each add one more entry to the `sections` array and one more `<component :is>` branch.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseEditorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_can_open_the_editor_and_it_does_not_create_a_profile_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('student/CaseEditor')
            ->where('clinicalCase.id', $case->id)
            ->where('clinicalProfile', null));
        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_editor_reflects_an_existing_profile(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);
        $this->actingAs($student);

        $response = $this->get("/student/cases/{$case->id}/edit");

        $response->assertInertia(fn ($page) => $page->where('clinicalProfile.allergy_status', 'unknown'));
    }

    public function test_a_different_student_cannot_open_the_editor(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->get("/student/cases/{$case->id}/edit")->assertForbidden();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status = CaseStatus::Draft): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => $status,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Write `CaseEditorController`**

```php
<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CaseEditorController extends Controller
{
    public function show(ClinicalCase $case): Response
    {
        Gate::authorize('view', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $profile = $case->clinicalProfile;

        return Inertia::render('student/CaseEditor', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'userId' => request()->user()->id,
            'context' => [
                'encounter_date' => $case->encounter_date?->toDateString(),
                'case_category' => $case->case_category,
                'age_value' => $case->age_value,
                'age_unit' => $case->age_unit,
                'sex' => $case->sex,
                'care_setting' => $case->care_setting,
                'hospital_day_at_first_review' => $case->hospital_day_at_first_review,
                'information_source' => $case->information_source,
                'weight_kg' => $case->weight_kg,
                'height_cm' => $case->height_cm,
                'pregnancy_lactation_status' => $case->pregnancy_lactation_status,
                'case_display' => [
                    'case_number' => $case->case_number,
                    'rotation_name' => $case->rotationAssignment?->rotation?->name,
                    'clinical_site_name' => $case->clinicalSite?->name,
                    'ward_name' => $case->ward?->name,
                ],
                'lock_version' => $case->lock_version,
                'updated_at' => $case->updated_at->toIso8601String(),
            ],
            'clinicalProfile' => $profile === null ? null : [
                'chief_complaints' => $profile->chief_complaints,
                'history_present_illness' => $profile->history_present_illness,
                'diagnoses' => $profile->diagnoses,
                'past_medical_history' => $profile->past_medical_history,
                'past_medical_history_none' => $profile->past_medical_history_none,
                'past_surgical_history' => $profile->past_surgical_history,
                'adherence_status' => $profile->adherence_status,
                'family_history' => $profile->family_history,
                'substance_history' => $profile->substance_history,
                'examination_findings' => $profile->examination_findings,
                'allergy_status' => $profile->allergy_status,
                'allergy_substance' => $profile->allergy_substance,
                'allergy_reaction' => $profile->allergy_reaction,
                'lock_version' => $profile->lock_version,
                'updated_at' => $profile->updated_at->toIso8601String(),
            ],
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add immediately after `student.cases.clinical-profile.sync`:

```php
        Route::get('student/cases/{case}/edit', [CaseEditorController::class, 'show'])->name('student.cases.edit');
```

Add the import:

```php
use App\Http\Controllers\Student\CaseEditorController;
```

- [ ] **Step 5: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 6: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Write `CaseEditor.vue`**

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import CaseProfileSection from './case-editor/CaseProfileSection.vue';
import HistoryDiagnosisSection from './case-editor/HistoryDiagnosisSection.vue';

type SectionId = 'case_profile' | 'history_diagnosis' | 'vitals_investigations' | 'medication_chart' | 'soap' | 'clinical_activities';

const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    userId: number;
    context: Record<string, unknown> & { lock_version: number; updated_at: string };
    clinicalProfile: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
        ],
    },
});

const sections: { id: SectionId; label: string; available: boolean }[] = [
    { id: 'case_profile', label: 'Case Profile', available: true },
    { id: 'history_diagnosis', label: 'History & Diagnosis', available: true },
    { id: 'vitals_investigations', label: 'Vitals & Investigations', available: false },
    { id: 'medication_chart', label: 'Medication Chart', available: false },
    { id: 'soap', label: 'SOAP', available: false },
    { id: 'clinical_activities', label: 'Conditional Clinical Activities', available: false },
];

const activeIndex = ref(0);
const activeSection = computed(() => sections[activeIndex.value]);

function goTo(index: number) {
    if (index < 0 || index >= sections.length) return;
    if (!sections[index].available) {
        if (sections[index].id === 'soap') router.get(`/student/cases/${props.clinicalCase.id}/soap`);
        return;
    }
    activeIndex.value = index;
}

const emptyClinicalProfile = {
    chief_complaints: null,
    history_present_illness: null,
    diagnoses: null,
    past_medical_history: null,
    past_medical_history_none: false,
    past_surgical_history: null,
    adherence_status: null,
    family_history: null,
    substance_history: null,
    examination_findings: null,
    allergy_status: 'unknown',
    allergy_substance: null,
    allergy_reaction: null,
    lock_version: 0,
    updated_at: new Date().toISOString(),
};
</script>

<template>
    <Head :title="`Case #${clinicalCase.case_number} — Edit`" />
    <main class="mx-auto w-full max-w-2xl px-4 pt-5 pb-28 sm:px-6 md:pt-8">
        <nav aria-label="Case sections" class="mb-5 flex gap-1.5 overflow-x-auto pb-1">
            <button
                v-for="(section, index) in sections"
                :key="section.id"
                type="button"
                :aria-current="index === activeIndex ? 'step' : undefined"
                :data-test="`section-nav-${section.id}`"
                class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-bold"
                :class="[
                    index === activeIndex ? 'border-[#0b2942] bg-[#0b2942] text-white' : 'border-slate-200 text-slate-500',
                    !section.available ? 'opacity-60' : '',
                ]"
                @click="goTo(index)"
            >
                {{ section.label }}
            </button>
        </nav>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900">
            <CaseProfileSection v-if="activeSection.id === 'case_profile'" :case-id="clinicalCase.id" :user-id="userId" :initial="context as any" />
            <HistoryDiagnosisSection v-else-if="activeSection.id === 'history_diagnosis'" :case-id="clinicalCase.id" :user-id="userId" :initial="(clinicalProfile ?? emptyClinicalProfile) as any" />
        </div>

        <div class="mt-5 flex justify-between">
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-slate-600 disabled:opacity-40" :disabled="activeIndex === 0" @click="goTo(activeIndex - 1)">
                <ChevronLeft class="size-4" /> Previous
            </button>
            <button type="button" class="flex items-center gap-1 text-sm font-bold text-slate-600 disabled:opacity-40" :disabled="activeIndex === sections.length - 1" @click="goTo(activeIndex + 1)">
                Next <ChevronRight class="size-4" />
            </button>
        </div>
    </main>
</template>
```

- [ ] **Step 8: Add an entry point from `CaseShow.vue`**

In `resources/js/pages/student/CaseShow.vue`, add a button next to the existing "Edit SOAP" button (inside the `v-if="clinicalCase.status === 'draft' || clinicalCase.status === 'returned'"` block of buttons, before the "Edit SOAP" `<Button>`):

```vue
                <Button
                    variant="outline"
                    @click="router.get(`/student/cases/${clinicalCase.id}/edit`)"
                >
                    Continue documentation
                </Button>
```

- [ ] **Step 9: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 10: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 163 previous passed + 3 new — 166 passed / 2 skipped (168 total).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Student/CaseEditorController.php routes/web.php resources/js/actions resources/js/routes resources/js/pages/student/CaseEditor.vue resources/js/pages/student/CaseShow.vue tests/Feature/CaseEditorPageTest.php
git commit -m "feat: add the mobile section-based case editor shell with Case Profile and History & Diagnosis wired in"
```

---

## Task 7: Authorization and institution-isolation tests

**Files:**
- Create: `tests/Feature/CaseEditorAuthorizationTest.php`

**Interfaces:**
- Consumes: `CaseContextController`, `CaseClinicalProfileController`, `CaseEditorController` (Tasks 4–6). No production code changes — this task closes gaps the per-task tests didn't already cover (a cross-institution denial and a post-submission lock, for both sync endpoints together, plus a faculty read-only check on the editor page).

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
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

class CaseEditorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_from_another_institution_cannot_sync_case_context_or_clinical_profile(): void
    {
        [, , $case] = $this->makeAssignedCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertNotFound();

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'allergy_status' => 'unknown',
        ])->assertNotFound();
    }

    public function test_the_owning_student_cannot_sync_either_section_once_the_case_is_submitted(): void
    {
        [, $student, $case] = $this->makeAssignedCase(CaseStatus::Submitted);
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/clinical-profile", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'allergy_status' => 'unknown',
        ])->assertForbidden();
    }

    public function test_assigned_faculty_can_open_the_editor_but_cannot_sync_either_section(): void
    {
        [, $student, $case, $faculty] = $this->makeAssignedCase();
        $this->actingAs($faculty);

        $this->get("/student/cases/{$case->id}/edit")->assertForbidden();

        $this->putJson("/student/cases/{$case->id}/context", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'care_setting' => 'inpatient',
        ])->assertForbidden();
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
            'institution_id' => $institution->id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => 'active',
        ]);
        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => $status,
        ]);

        return [$institution, $student, $case, $faculty];
    }
}
```

Note: `test_a_user_from_another_institution_cannot_sync_case_context_or_clinical_profile` expects `assertNotFound()` (404) rather than `assertForbidden()` (403) because route-model binding for `{case}` resolves against the institution-scoped global query (`BelongsToInstitution`); a case from a different institution never resolves for that user, so Laravel raises a 404 before the `FormRequest::authorize()` gate even runs. This mirrors the same 404-not-403 behavior documented as a pre-merge fix in Slice 1 (`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md`, "Pre-merge blockers fixed before merge").

- [ ] **Step 2: Run the tests**

Run (PowerShell): `php artisan test --filter=CaseEditorAuthorizationTest`
Expected: PASS (3 tests). If the cross-institution assertions unexpectedly return 403 instead of 404, check whether `ClinicalCase`'s route binding actually enforces the institution scope before controller/request code runs (it should, via the `BelongsToInstitution` global scope applied to implicit route-model binding) — do not weaken the assertion to accept either status without first confirming which one the app actually returns and why.

- [ ] **Step 3: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 166 previous passed + 3 new — 169 passed / 2 skipped (171 total).

- [ ] **Step 4: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no formatting diffs. If Pint reports diffs, run `vendor\bin\pint` and re-check `git diff`.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/CaseEditorAuthorizationTest.php
git commit -m "test: cover cross-institution, post-submission and faculty-read-only access for the Slice 2A editor"
```

---

## Task 8: Manual device verification (phone / tablet / desktop)

**Revision:** The original Step 3.4's offline-refresh check ("edit offline, confirm the value survives a page refresh") described behavior the app cannot provide without a service worker caching the page shell — a hard refresh while offline hits the browser's own network-error interstitial, not the Inertia app, so there is nothing on-page to re-read from IndexedDB until the browser can load the app again. The corrected script verifies draft *recovery on reconnect*, not offline refresh.

**Files:** None (verification only; no code changes).

**Interfaces:** None.

This repository has no committed Playwright specs for authenticated, stateful flows (the only spec, `tests/e2e/welcome.spec.ts`, covers the public welcome page, and `playwright.config.ts`'s `baseURL` points at a deployed URL, not a local dev server). Consistent with how SYNC-SPIKE-01 and Slice 1 were verified (per `PROJECT_STATE.md`: "Headless Chromium at 390 × 844"), this task is a manual/interactive browser-verification pass, not a new automated spec. Use whichever interactive browser tool is available in the session (Chrome DevTools MCP or Claude in Chrome) against the local dev server.

- [ ] **Step 1: Start the app**

Run (PowerShell, background): `php artisan serve` and, in a second shell, `npm run dev`. Confirm the app loads at the local dev URL and you can log in as a seeded student.

- [ ] **Step 2: Phone viewport (390×844, matching a mid-size Android Chrome phone)**

Resize/emulate the browser to 390×844. As a student with an active rotation assignment, create a case and open `/student/cases/{id}/edit`.

Pass criteria:
- The section nav bar is horizontally scrollable and fits without page-level horizontal scroll.
- Only one section's fields are visible at a time; switching sections via the nav or Previous/Next updates the visible fields without a full page reload (Inertia page stays the same, only `activeIndex` changes).
- The Case Profile section shows the read-only Educational Case ID / rotation / site / ward block above the editable fields.
- Typing in a Case Profile field shows "Saving…" then "Saved" within ~1 second (700ms debounce + round trip).
- Setting Age unit to "Days" and typing an age value over 364 shows a validation error on save; the same value is accepted when the unit is "Years".
- Typing in the History & Diagnosis "History of present illness" textarea (or Family history, or Examination findings) with a value containing a 10-digit number (e.g. `9876543210`) shows the de-identification warning below the field.
- Setting Allergy status to "Known allergy", filling in a substance, then switching back to "No known allergy" and reopening the page shows the substance field is gone (cleared server-side, not just hidden).
- Touch targets (buttons, radio labels, nav pills) are comfortably tappable — no two adjacent tap targets closer than ~8px.

- [ ] **Step 3: Offline draft recovery, reconnect, and conflict**

Using DevTools network throttling/offline toggle:
1. Go offline, edit a Case Profile field. Confirm the status changes to "Saved on this device".
2. **Without refreshing the page** (a hard refresh while offline hits the browser's own network-error page, not the app — there is nothing to verify there), navigate within the app (e.g. switch sections and back) and confirm the offline-entered value is still shown from the in-memory `useSectionSync` state.
3. Go back online. Confirm the pending change auto-syncs and the status returns to "Saved". Then reload the page and confirm the value persisted server-side (this is the real recovery guarantee — not an offline refresh, but a post-reconnect one).
4. Simulate a conflict: open the same case in two tabs, edit Case Profile in Tab A and let it save, then edit the same field in Tab B (which still holds the pre-Tab-A `lock_version`) and let it sync. Confirm Tab B shows the conflict panel with "Use server version" / "Keep local draft as a copy" / "Replace server version", and that choosing each option produces the behavior described in Task 2/3 (no silent overwrite in any path).
5. Edit a Case Profile field and, separately, toggle nothing else (2B's availability toggles don't exist yet in this slice) — confirm this single-section case still works exactly as before; the independent-lock-column change in Task 2 is fully exercised once 2B's toggles exist, and is re-verified in 2B's own device-verification task.
6. Log out. Open DevTools Application → IndexedDB and confirm `pharmalab-section-outbox` has no entries left (Task 3's logout hook). If entries remain, this is a regression, not an expected gap — stop and fix it before proceeding, do not note it as a known limitation.

- [ ] **Step 4: Tablet viewport (820×1180)**

Repeat Step 2's pass criteria at 820×1180. Confirm the layout doesn't look sparse/stretched (grid columns in `CaseProfileSection.vue` should occupy the `sm:grid-cols-2` layout reasonably).

- [ ] **Step 5: Desktop viewport (1280×900, Chrome/Edge)**

Repeat Step 2's pass criteria at 1280×900. Confirm the editor's `max-w-2xl` container doesn't look oddly narrow on a wide screen — if it does, note this as a candidate polish item for Slice 2C rather than changing layout now (scope discipline: 2A's job is correctness and the two sections, not final visual polish across all breakpoints).

- [ ] **Step 6: Record the result**

Update `PROJECT_STATE.md`'s Slice 2 entry (once all of Slice 2A/2B/2C are complete and merged, per the Slice 1 precedent — do not update `PROJECT_STATE.md` after 2A alone, since Slice 2 as a whole is still active). For now, note the verification outcome (pass/fail per step, with any gaps found) in the pull-request description when 2A is opened for review.

---

## Self-Review Notes

- **Spec coverage:** User requirement 1 (explicit none/unavailable states) — Task 1 (schema, including the "no current medicines" explanation field and independent lock columns; UI lands in Slice 2B). Requirement 2 (mobile section-based editor) — Task 6. Requirement 3 (repeatable rows) — out of scope for 2A by design, owned by Slice 2B. Requirement 4 (partial autosave, `allergy_status` named example) — Tasks 4 and 5, with dedicated regression tests, now also proving the allergy-conditional-field-clearing behavior. Requirement 5 (IndexedDB outbox, idempotency, optimistic locking, conflict handling) — Tasks 2 and 3, including cross-section and cross-endpoint replay-safety hardening, proven end-to-end in Tasks 4, 5 and manually in Task 8. Requirement 6 (conditional allergy/ADR fields) — allergy fields in Task 5, including the clear-on-change fix; ADR fields are Slice 2C's Conditional Clinical Activities section. Requirement 7 (de-identification warnings) — Task 5, now applied to every narrative field in this slice, not only HPI. Requirement 8 (student ownership, assigned-faculty visibility, institution isolation tests) — Task 7. Requirement 9 (device verification) — Task 8, with the offline-refresh script corrected to describe actual browser behavior.
- **Placeholder scan:** No task contains "TBD"/"handle appropriately"/unshown code. Task 3's composable and store have no automated test of their own — this is called out explicitly as a deliberate, justified divergence (no JS unit-test runner exists in this repo) rather than a silently-skipped test. Task 2's migration round-trip *and* rollback-refusal tests each name a concrete fallback (isolate on a dedicated file-backed SQLite database via `DatabaseTransactions`) if the transactional-DDL approach proves flaky under a non-SQLite driver, rather than leaving the risk unaddressed. Both tests also pin their `migrate:rollback` to `make_sync_operations_polymorphic.php` via `--path` rather than `--step=1`, so they keep exercising the correct migration after 2B/2C introduce newer ones.
- **Type consistency:** `SectionSyncService::sync()`'s return shape (`array{status, httpStatus, model}`) is identical across Tasks 2, 4 and 5. `Syncable::getLockVersion(string $sectionKey)`/`applySyncedAttributes(string $sectionKey, ...)` is the same two-argument-plus-section-key shape everywhere it's called in this plan, and 2B/2C's plans are written against this exact signature (not the single-argument version the original draft shipped). `useSectionSync`'s `SyncedSection` base type (`lock_version` + `updated_at`) is used consistently by both `CaseProfileSection.vue` and `HistoryDiagnosisSection.vue`. `HasSyncEnvelope` and `RejectsUnknownFields` are defined once in Task 4 and reused unmodified (or, for the one class that needs a second `withValidator()` concern, inlined equivalently and explained) by every later request class in this series.
- **Review Focus coverage:** every Review Focus item (partial-save field wipe, false conflict between sibling `ClinicalCase` sections, eager profile creation, stale lock overwrite, cross-institution/role/status access, idempotent replay including cross-section/cross-endpoint misuse, logout leaving a readable draft, a request-specific `withValidator()` silently dropping unknown-field rejection, and a caller-supplied "expected class" routing around the section allow-list) has a named test in the task that owns the code — none are asserted only in prose. The nested-JSON-key item is a 2C concern (this slice has no array/object-valued request fields) and is covered there with `array:key1,key2` rules and dedicated tests.
