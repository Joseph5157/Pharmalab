# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2A (Sync Infrastructure, Case Profile, History & Diagnosis) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution was chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generalize the accepted SYNC-SPIKE-01 offline-sync protocol (IndexedDB outbox, idempotent `client_operation_id`, server `lock_version`, optimistic-concurrency conflict resolution) from the single experimental `CaseDraftNote` into a reusable engine, then use it to ship the first two real sections of the mobile case editor — Case Profile and History & Diagnosis — with partial (field-level) autosave and the explicit vitals/investigations/medication-chart "unavailable" schema fields that Slice 1 deferred to this slice.

**Architecture:** This is the first of three sequential sub-plans for Slice 2 (2A → 2B → 2C), chosen over one monolithic plan because the full mobile editor spans 6 sections, 4 repeatable resource types and a generalized sync engine — too large to review or gate as a single unit. 2A builds the shared backend engine (`App\Contracts\Syncable` + `SyncsWithLockVersion` trait + `SectionSyncService`, generalizing `CaseDraftNoteController`'s transaction/dedup/conflict logic) and the shared frontend engine (`outboxStore.ts` + `useSectionSync.ts`, generalizing `caseDraftStore.ts` + the script block of `CaseDraftNote.vue`), proves both against `ClinicalCase` and `CaseClinicalProfile` (both already carry an unused `lock_version` column from earlier slices), and ships a real two-section mobile editor shell (`CaseEditor.vue`). 2B will reuse this engine for the three repeatable-row sections (Vitals, Investigations, Medication Chart). 2C will reuse it again for SOAP and Conditional Clinical Activities, and add end-to-end device verification across all six sections. The existing `CaseDraftNote` experiment, its controller, and `caseDraftStore.ts` are left untouched — Slice 2 adds parallel, generalized infrastructure rather than modifying the accepted spike.

**Tech Stack:** Laravel 13 (PHP 8.4), Eloquent, PHPUnit, SQLite (`:memory:`) for the test suite, PostgreSQL in production; Inertia.js + Vue 3 + TypeScript on the frontend, native `fetch` + IndexedDB for the offline sync path (matching the accepted SYNC-SPIKE-01 pattern — not an Inertia form).

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.1–4.2, decisions in [`docs/decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md`](../../decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md), accepted sync protocol in [`docs/SYNC_SPIKE_01_FINDINGS.md`](../../SYNC_SPIKE_01_FINDINGS.md), Slice 1 plan and ledger in [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md`](2026-09-25-direct-documentation-impl-01-slice-1.md).

## Global Constraints

- Run every `php`, `composer`, `npm`, `vendor/bin/phpunit` command through the **PowerShell** tool, not Bash — `php` is only on PATH via PowerShell (Laravel Herd) in this environment.
- `doctrine/dbal` is **not installed** in this project (confirmed: absent from `vendor/doctrine`). Never use `Schema::table(...)->change()`. Where an existing column must become nullable or a constraint must be replaced (Task 2's `sync_operations.case_draft_note_id`), drop the column/constraint and re-add it in separate `Schema::table()` calls within the same migration.
- After adding or changing any route in `routes/web.php`, regenerate the Wayfinder TypeScript files by running `npm run build` (the `@laravel/vite-plugin-wayfinder` Vite plugin regenerates `resources/js/actions/**` and `resources/js/routes/**` as part of the build) and commit the regenerated files in the **same commit** as the route change. Never hand-edit anything under `resources/js/actions/` or `resources/js/routes/` — it is generated. Do not commit incidental line-ending/comment churn in unrelated generated files; if `git status` shows generated files you did not intend to touch, run `git checkout -- resources/js/actions resources/js/routes` before committing and only regenerate immediately before the commit that needs it.
- `lock_version` is never mass-fillable on any model. It is only ever bumped via `forceFill()` inside `SyncsWithLockVersion::applySyncedAttributes()` (Task 2). Task 1 fixes an existing Slice 1 regression: `CaseClinicalProfile`'s `#[Fillable([...])]` currently lists `'lock_version'`, which would let a client set it directly through mass assignment — remove it.
- Every sync-capable model (`ClinicalCase`, `CaseClinicalProfile`, and later Slice 2B/2C models) implements `App\Contracts\Syncable` via the `App\Models\Concerns\SyncsWithLockVersion` trait and is synced only through `App\Services\SectionSyncService`. Do not hand-roll a second copy of the dedup/lock/conflict transaction in a new controller — that duplication is exactly what Task 2 exists to prevent.
- Optional child records are created only inside the **first deliberate `sync()` call**, never on a `GET`/`show` request. This is the Slice 1 "no eager child rows" rule (`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md` Review Focus item 4) and it must not regress here: rendering the case editor page must never insert a `CaseClinicalProfile` row.
- Partial autosave (Slice 2 requirement): every `Update*Request` used by a `sync()` endpoint gives each field rule set a leading `'sometimes'` entry, so a payload that omits a key is neither validated nor written — the existing `UpdateCaseClinicalProfileRequest::rules()` currently has `'allergy_status' => ['required', ...]` with no `'sometimes'`, which would reject any partial autosave that doesn't include `allergy_status`; Task 5 fixes this named example directly.
- This repository has no JavaScript/TypeScript unit-test runner (only Playwright e2e specs and PHPUnit; confirmed by searching for Vitest/Jest configuration — none exists). Do not add one. Frontend-only tasks are gated by `npm run types:check` (TypeScript compiles) and are functionally verified once a real page in a later task renders them; say so explicitly in the task rather than inventing a test file that doesn't match the project's established testing shape.
- Baseline before this plan: `main` at commit `151e1a1` (one commit past the accepted `65c5b7e` Slice 1 merge, itself just a docs formatting commit). `php artisan test` passes 131 tests, 129 passed, 2 skipped, 453 assertions. Every task's "run full suite" step expects these to still pass plus the task's new tests.

## Review Focus

- **Partial-save field wipe.** A sync request that supplies only one field (e.g. a payload containing just `past_medical_history`) must not fail validation because `allergy_status` is absent, and must not null out `allergy_status` or any other previously-saved field on the row. Tasks 4 and 5's controller tests each post a single-field payload after an initial full save and assert every other stored field is unchanged.
- **Eager profile creation regression.** Opening the case editor (`GET /student/cases/{case}/edit`) must never create a `CaseClinicalProfile` row — only a deliberate `sync()` call may. Task 5's test asserts `$case->fresh()->clinicalProfile` is still `null` after rendering the editor page, and Task 6's editor-shell test repeats the same assertion at the page level.
- **Stale `lock_version` silently overwriting a newer save.** Any `sync()` call whose `base_lock_version` does not match the row's current `lock_version` must return HTTP 409 with the current server state and must not mutate the row. Task 2's engine test and Tasks 4/5's HTTP-level tests each assert this by sending a stale version after an intervening save.
- **Cross-institution / other-student / post-submission access.** Every new `PUT` endpoint must deny a different student in the same institution, a user from a different institution, and — once the case's status leaves Draft/Returned — even the owning student. Task 7's dedicated authorization test file exercises all three denials against both new controllers, matching the negative-authorization pattern established in Slice 1's `CaseClinicalProfileTest`.
- **Idempotent replay creating a duplicate write.** A retried request carrying the same `client_operation_id` (the exact scenario an offline device produces when it reconnects and resends) must not double-apply the change or insert a second `SyncOperation` row. Task 2's engine test and Task 4's controller test both replay an operation ID and assert `lock_version` only advanced once and exactly one `SyncOperation` row exists for that ID.

---

## Task 1: Explicit-absence schema fields and the Slice 1 `lock_version` Fillable fix

**Files:**
- Create: `database/migrations/2026_09_28_000000_add_pharmd_slice2_section_availability_fields.php`
- Modify: `app/Models/ClinicalCase.php` (extend `#[Fillable([...])]`)
- Modify: `app/Models/CaseClinicalProfile.php` (remove `'lock_version'` from `#[Fillable([...])]`)
- Test: `tests/Feature/PharmdCaseSectionAvailabilityFieldsTest.php`

**Interfaces:**
- Produces: new nullable columns on `clinical_cases` — `vitals_status` (string, values `'recorded'|'unavailable'`), `vitals_unavailable_reason` (text), `investigations_status` (string, values `'recorded'|'unavailable'`), `investigations_unavailable_reason` (text), `medication_chart_status` (string, values `'documented'|'none_documented'`). These are schema-only in this task; Slice 2B's Vitals/Investigations/Medication controllers own reading and validating them.

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
            'medication_chart_status',
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
        ]);

        $fresh = $case->fresh();
        $this->assertSame('unavailable', $fresh->vitals_status);
        $this->assertSame('Patient not examined at bedside during ward round.', $fresh->vitals_unavailable_reason);
        $this->assertSame('recorded', $fresh->investigations_status);
        $this->assertNull($fresh->investigations_unavailable_reason);
        $this->assertSame('none_documented', $fresh->medication_chart_status);
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
Expected: FAIL — unknown columns `vitals_status` etc.; third test currently passes for the wrong reason (column is still mass-assignable) — confirm by temporarily noting `lock_version` remains fillable, then proceed to fix it in Step 4.

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
            ]);
        });
    }
};
```

- [ ] **Step 4: Extend `ClinicalCase`'s Fillable list**

In `app/Models/ClinicalCase.php`, add the five new keys to the `#[Fillable([...])]` array, after `'deidentification_attested_by',`:

```php
    'vitals_status',
    'vitals_unavailable_reason',
    'investigations_status',
    'investigations_unavailable_reason',
    'medication_chart_status',
```

- [ ] **Step 5: Fix `CaseClinicalProfile`'s Fillable list**

In `app/Models/CaseClinicalProfile.php`, remove `'lock_version',` from the `#[Fillable([...])]` array. `lock_version` must only ever be set via `forceFill()`.

- [ ] **Step 6: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdCaseSectionAvailabilityFieldsTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Run the full suite to confirm no regression**

Run (PowerShell): `php artisan test`
Expected: 131 previous tests still pass (129 passed / 2 skipped) plus the 3 new ones — 134 passed / 2 skipped.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_28_000000_add_pharmd_slice2_section_availability_fields.php app/Models/ClinicalCase.php app/Models/CaseClinicalProfile.php tests/Feature/PharmdCaseSectionAvailabilityFieldsTest.php
git commit -m "feat: add explicit vitals/investigations/medication-chart absence fields and fix CaseClinicalProfile lock_version mass assignment"
```

---

## Task 2: Generalize the sync engine — `Syncable` contract, polymorphic `SyncOperation`, `SectionSyncService`

**Files:**
- Create: `database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php`
- Create: `app/Contracts/Syncable.php`
- Create: `app/Models/Concerns/SyncsWithLockVersion.php`
- Modify: `app/Models/SyncOperation.php` (add `syncable_type`/`syncable_id` to fillable, add `syncable()` morph relation)
- Modify: `app/Models/ClinicalCase.php` (implement `Syncable` via the new trait)
- Create: `app/Services/SectionSyncService.php`
- Test: `tests/Feature/SectionSyncServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\ClinicalCase` (as the first real `Syncable` under test), `App\Models\SyncOperation`, `App\Services\AuditTrail::record()`.
- Produces: `App\Contracts\Syncable` (`getLockVersion(): int`, `getInstitutionId(): string`, `applySyncedAttributes(array $attributes, int $newLockVersion): void`), `App\Models\Concerns\SyncsWithLockVersion` (generic trait implementing all three), `App\Services\SectionSyncService::sync(Model&Syncable $model, User $user, string $sectionKey, string $clientOperationId, int $baseLockVersion, array $attributes, ?string $resolution, bool $confirmed): array{status: string, httpStatus: int, model: Model&Syncable}`. Consumed by Tasks 4 and 5 (Case Profile, History & Diagnosis) and by every Slice 2B/2C section controller.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
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

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=SectionSyncServiceTest`
Expected: FAIL — `App\Services\SectionSyncService` not found.

- [ ] **Step 3: Write the `sync_operations` polymorphism migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropForeign(['case_draft_note_id']);
        });

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropColumn('case_draft_note_id');
        });

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->foreignUlid('case_draft_note_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
            $table->string('syncable_type', 150)->nullable()->after('case_draft_note_id');
            $table->ulid('syncable_id')->nullable()->after('syncable_type');
            $table->index(['syncable_type', 'syncable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropIndex(['syncable_type', 'syncable_id']);
            $table->dropColumn(['syncable_type', 'syncable_id']);
            $table->dropForeign(['case_draft_note_id']);
            $table->dropColumn('case_draft_note_id');
        });

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->foreignUlid('case_draft_note_id')->after('user_id')->constrained()->restrictOnDelete();
        });
    }
};
```

- [ ] **Step 4: Write the `Syncable` contract**

```php
<?php

namespace App\Contracts;

interface Syncable
{
    public function getLockVersion(): int;

    public function getInstitutionId(): string;

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(array $attributes, int $newLockVersion): void;
}
```

- [ ] **Step 5: Write the `SyncsWithLockVersion` trait**

```php
<?php

namespace App\Models\Concerns;

trait SyncsWithLockVersion
{
    public function getLockVersion(): int
    {
        return (int) $this->lock_version;
    }

    public function getInstitutionId(): string
    {
        return (string) $this->institution_id;
    }

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(array $attributes, int $newLockVersion): void
    {
        $this->forceFill([...$attributes, 'lock_version' => $newLockVersion])->save();
    }
}
```

- [ ] **Step 6: Make `ClinicalCase` implement `Syncable`**

In `app/Models/ClinicalCase.php`, add imports and apply the trait/interface:

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
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SectionSyncService
{
    public function __construct(private readonly AuditTrail $audit) {}

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
        return DB::transaction(function () use ($model, $user, $sectionKey, $clientOperationId, $baseLockVersion, $attributes, $resolution, $confirmed): array {
            /** @var Model&Syncable $locked */
            $locked = $model::query()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            $existing = SyncOperation::query()
                ->where('user_id', $user->id)
                ->where('client_operation_id', $clientOperationId)
                ->first();

            if ($existing !== null) {
                abort_unless(
                    $existing->syncable_type === $locked::class && $existing->syncable_id === $locked->getKey(),
                    409,
                    'Operation ID already belongs to another record.',
                );

                return ['status' => $existing->result_status, 'httpStatus' => $existing->result_status === 'conflict' ? 409 : 200, 'model' => $locked];
            }

            if ($resolution === 'use_server' || $resolution === 'keep_local_copy') {
                $status = $resolution === 'use_server' ? 'resolved_server' : 'resolved_device_copy';
                $this->recordOperation($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, $status);
                $this->auditResolution($user, $locked, $sectionKey, $resolution, $baseLockVersion);

                return ['status' => $status, 'httpStatus' => 200, 'model' => $locked];
            }

            if ($baseLockVersion !== $locked->getLockVersion()) {
                $this->recordOperation($user, $locked, $sectionKey, $clientOperationId, $baseLockVersion, 'conflict');

                return ['status' => 'conflict', 'httpStatus' => 409, 'model' => $locked];
            }

            if ($resolution === 'replace_server' && ! $confirmed) {
                abort(422, 'Replacing the server version requires explicit confirmation.');
            }

            $locked->applySyncedAttributes($attributes, $locked->getLockVersion() + 1);

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
            'syncable_type' => $model::class,
            'syncable_id' => $model->getKey(),
            'client_operation_id' => $clientOperationId,
            'section_key' => $sectionKey,
            'base_lock_version' => $baseLockVersion,
            'result_status' => $status,
            'server_version' => $model->getLockVersion(),
        ]);
    }

    /** @param Model&Syncable $model */
    private function auditResolution(User $user, Model&Syncable $model, string $sectionKey, string $resolution, int $baseLockVersion): void
    {
        $this->audit->record($user, $model, "{$sectionKey}.conflict_resolved", [
            'section_key' => $sectionKey,
            'resolution' => $resolution,
            'base_lock_version' => $baseLockVersion,
            'server_lock_version' => $model->getLockVersion(),
        ]);
    }
}
```

- [ ] **Step 9: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=SectionSyncServiceTest`
Expected: PASS (6 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 6 new tests pass (140 passed / 2 skipped).

- [ ] **Step 11: Static analysis**

Run (PowerShell): `vendor\bin\phpstan analyse`
Expected: 0 errors. (`Model&Syncable` intersection types require accurate `@property`/`@method` docblocks already present on `ClinicalCase`; if PHPStan flags the intersection type, add `@phpstan-require-implements Syncable` is unnecessary here since the type is enforced at the parameter boundary — fix any real type error rather than suppressing it.)

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_09_28_000001_make_sync_operations_polymorphic.php app/Contracts/Syncable.php app/Models/Concerns/SyncsWithLockVersion.php app/Models/SyncOperation.php app/Models/ClinicalCase.php app/Services/SectionSyncService.php tests/Feature/SectionSyncServiceTest.php
git commit -m "feat: generalize the sync-spike protocol into a reusable Syncable contract and SectionSyncService"
```

---

## Task 3: Frontend outbox engine — `outboxStore.ts` and `useSectionSync.ts`

**Files:**
- Create: `resources/js/lib/outboxStore.ts`
- Create: `resources/js/composables/useSectionSync.ts`

**Interfaces:**
- Produces: `StoredSection<T>`, `StoredSectionCopy<T>`, `sectionKey(userId, sectionKey, resourceId): string`, `getSection<T>(key)`, `putSection<T>(section)`, `deleteSection(key)`, `keepSectionCopy<T>(section)`, `clearSectionOutbox()` (in `outboxStore.ts`); `useSectionSync<T>(options): { payload, state, savedAt, online, conflict, confirmingReplace, deviceCopyKept, edit, resolveWithServer, keepDeviceCopy, replaceServer, retry }` (in `useSectionSync.ts`). Both are consumed for the first time by Task 4 (Case Profile section) and again by Task 5 (History & Diagnosis section) and every later Slice 2B/2C section. This task's files have no consumer yet and are not independently testable — per the Global Constraints, there is no JS unit-test runner in this repo; Task 4 is where these files are first exercised in a real page and manually verified in a browser.

This generalizes `resources/js/lib/caseDraftStore.ts` and the script block of `resources/js/pages/student/CaseDraftNote.vue`, which are both left untouched (the accepted SYNC-SPIKE-01 experiment keeps working exactly as before).

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

- [ ] **Step 3: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors. (`@/lib/...` and `@/composables/...` aliases already resolve per the existing `tsconfig.json`/Vite alias used by `caseDraftStore.ts` imports elsewhere — confirm by checking that `resources/js/pages/student/CaseDraftNote.vue` already imports via `@/lib/caseDraftStore`.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/lib/outboxStore.ts resources/js/composables/useSectionSync.ts
git commit -m "feat: add a generalized IndexedDB outbox and section-sync composable"
```

---

## Task 4: Case Profile section — request, controller, route, Vue component

**Files:**
- Create: `app/Http/Requests/Concerns/HasSyncEnvelope.php`
- Create: `app/Http/Requests/Student/UpdateClinicalCaseContextRequest.php`
- Create: `app/Http/Controllers/Student/CaseContextController.php`
- Modify: `routes/web.php` (add the `sync` route)
- Create: `resources/js/pages/student/case-editor/CaseProfileSection.vue`
- Test: `tests/Feature/CaseContextSyncTest.php`

**Interfaces:**
- Consumes: `App\Services\SectionSyncService::sync()` (Task 2), `useSectionSync` + `outboxStore` (Task 3).
- Produces: `HasSyncEnvelope::syncEnvelopeRules(): array`, `HasSyncEnvelope::syncEnvelope(): array{client_operation_id: string, base_lock_version: int, resolution: string|null, confirmed: bool}`, `HasSyncEnvelope::sectionData(): array` (validated data minus the four envelope keys) — reused by Task 5 and every later section request. `PUT /student/cases/{case}/context` returning `{ "section": { ...case-context fields, lock_version, updated_at } }`. `CaseProfileSection.vue` — a `<script setup>` component taking `caseId: string`, `userId: number`, `initial: CaseContextPayload` as props, consumed by Task 6's `CaseEditor.vue`.

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

- [ ] **Step 4: Write `UpdateClinicalCaseContextRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicalCaseContextRequest extends FormRequest
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
            'encounter_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'case_category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'age_value' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:150'],
            'age_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sex' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'intersex', 'unknown'])],
            'care_setting' => ['sometimes', 'nullable', Rule::in(['inpatient', 'outpatient', 'emergency', 'other'])],
            'hospital_day_at_first_review' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'information_source' => ['sometimes', 'nullable', 'string', 'max:60'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:300'],
            'pregnancy_lactation_status' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
```

- [ ] **Step 5: Write `CaseContextController`**

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
            'lock_version' => $case->lock_version,
            'updated_at' => $case->updated_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add inside the existing `role:student` group, immediately after the `student.cases.show` route:

```php
        Route::put('student/cases/{case}/context', [CaseContextController::class, 'sync'])->name('student.cases.context.sync');
```

Add the import near the other `Student` controller imports:

```php
use App\Http\Controllers\Student\CaseContextController;
```

- [ ] **Step 7: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`
This regenerates `resources/js/actions/App/Http/Controllers/Student/CaseContextController.ts` and the matching route file. Confirm with `git status` that only Wayfinder files plus your own new files changed.

- [ ] **Step 8: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CaseContextSyncTest`
Expected: PASS (5 tests).

- [ ] **Step 9: Write the Case Profile Vue section component**

```vue
<script setup lang="ts">
import { RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

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
                    <input v-model.number="payload.age_value" type="number" min="0" max="150" data-test="age-value" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
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

- [ ] **Step 10: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors.

- [ ] **Step 11: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: all previous + 5 new tests pass (145 passed / 2 skipped).

- [ ] **Step 12: Commit**

```bash
git add app/Http/Requests/Concerns/HasSyncEnvelope.php app/Http/Requests/Student/UpdateClinicalCaseContextRequest.php app/Http/Controllers/Student/CaseContextController.php routes/web.php resources/js/actions resources/js/routes resources/js/pages/student/case-editor/CaseProfileSection.vue tests/Feature/CaseContextSyncTest.php
git commit -m "feat: add partial-autosave Case Profile section (request, controller, route, component)"
```

---

## Task 5: History & Diagnosis section — partial validation fix, controller, route, Vue component

**Files:**
- Modify: `app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php` (add `'sometimes'` to every field, add sync envelope)
- Create: `app/Http/Controllers/Student/CaseClinicalProfileController.php`
- Modify: `app/Models/CaseClinicalProfile.php` (implement `Syncable`)
- Modify: `routes/web.php` (add the `sync` route)
- Create: `resources/js/lib/deidentification.ts`
- Create: `resources/js/components/DeidentificationNotice.vue`
- Create: `resources/js/pages/student/case-editor/HistoryDiagnosisSection.vue`
- Test: `tests/Feature/CaseClinicalProfileSyncTest.php`

**Interfaces:**
- Consumes: `HasSyncEnvelope` (Task 4), `SectionSyncService` (Task 2), `useSectionSync` (Task 3).
- Produces: `PUT /student/cases/{case}/clinical-profile` returning `{ "section": {...profile fields, lock_version, updated_at} }`; creates the `CaseClinicalProfile` row lazily on the first deliberate sync, never on page render. `detectPotentialIdentifiers(text: string): string[]`, `<DeidentificationNotice :text="..." />` — reused by later Slice 2B/2C sections with free-text fields.

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

    public function test_rendering_the_editor_page_never_creates_a_profile_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->get("/student/cases/{$case->id}");

        $this->assertNull($case->fresh()->clinicalProfile);
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

- [ ] **Step 3: Fix `UpdateCaseClinicalProfileRequest` for partial autosave**

Replace the full contents of `app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseClinicalProfileRequest extends FormRequest
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
}
```

- [ ] **Step 4: Make `CaseClinicalProfile` implement `Syncable`**

In `app/Models/CaseClinicalProfile.php`, add imports and apply the trait/interface exactly as Task 2 did for `ClinicalCase`:

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

        $result = $sync->sync(
            $profile,
            $request->user(),
            'clinical_profile',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            [...$request->sectionData(), 'last_saved_by' => $request->user()->id],
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
Expected: PASS (6 tests).

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

- [ ] **Step 11: Write the History & Diagnosis Vue section component**

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
                <input v-model="complaint.complaint" type="text" maxlength="255" placeholder="Complaint" :data-test="`chief-complaint-${index}`" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                <input v-model="complaint.duration" type="text" maxlength="60" placeholder="Duration" class="w-32 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
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
                <input v-model="diagnosis.label" type="text" maxlength="255" placeholder="Diagnosis" :data-test="`diagnosis-${index}`" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
                <select v-model="diagnosis.type" class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @change="edit">
                    <option value="provisional">Provisional</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="comorbidity">Comorbidity</option>
                </select>
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
Expected: all previous + 6 new tests pass (151 passed / 2 skipped).

- [ ] **Step 14: Commit**

```bash
git add app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php app/Http/Controllers/Student/CaseClinicalProfileController.php app/Models/CaseClinicalProfile.php routes/web.php resources/js/actions resources/js/routes resources/js/lib/deidentification.ts resources/js/components/DeidentificationNotice.vue resources/js/pages/student/case-editor/HistoryDiagnosisSection.vue tests/Feature/CaseClinicalProfileSyncTest.php
git commit -m "feat: add partial-autosave History & Diagnosis section with de-identification warnings"
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
                'lock_version' => $case->lock_version,
                'updated_at' => $case->updated_at->toIso8601String(),
            ],
            'clinicalProfile' => $profile === null ? null : [
                'chief_complaints' => $profile->chief_complaints,
                'history_present_illness' => $profile->history_present_illness,
                'diagnoses' => $profile->diagnoses,
                'past_medical_history' => $profile->past_medical_history,
                'past_medical_history_none' => $profile->past_medical_history_none,
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
Expected: all previous + 3 new tests pass (154 passed / 2 skipped).

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
Expected: all previous + 3 new tests pass (157 passed / 2 skipped).

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
- Typing in a Case Profile field shows "Saving…" then "Saved" within ~1 second (700ms debounce + round trip).
- Typing in the History & Diagnosis "History of present illness" textarea with a value containing a 10-digit number (e.g. `9876543210`) shows the de-identification warning below the field.
- Touch targets (buttons, radio labels, nav pills) are comfortably tappable — no two adjacent tap targets closer than ~8px.

- [ ] **Step 3: Offline draft recovery, reconnect, and conflict — repeat the SYNC-SPIKE-01 script against the new sections**

Using DevTools network throttling/offline toggle:
1. Go offline, edit a Case Profile field. Confirm the status changes to "Saved on this device" and the value survives a page refresh (re-fetch the page, confirm the field still shows the offline-entered value pulled from IndexedDB via `useSectionSync`'s `onMounted` recovery).
2. Go back online. Confirm the pending change auto-syncs and the status returns to "Saved".
3. Simulate a conflict: open the same case in two tabs, edit Case Profile in Tab A and let it save, then edit the same field in Tab B (which still holds the pre-Tab-A `lock_version`) and let it sync. Confirm Tab B shows the conflict panel with "Use server version" / "Keep local draft as a copy" / "Replace server version", and that choosing each option produces the behavior described in Task 2/3 (no silent overwrite in any path).
4. Log out. Confirm no readable draft remains for that user (open DevTools Application → IndexedDB → `pharmalab-section-outbox` and confirm entries are gone, or repeat via `clearSectionOutbox()` if a logout hook isn't wired up yet — if it is NOT wired up, note this as a known gap for Slice 2C to address, do not silently treat it as passing).

- [ ] **Step 4: Tablet viewport (820×1180)**

Repeat Step 2's pass criteria at 820×1180. Confirm the layout doesn't look sparse/stretched (grid columns in `CaseProfileSection.vue` should occupy the `sm:grid-cols-2` layout reasonably).

- [ ] **Step 5: Desktop viewport (1280×900, Chrome/Edge)**

Repeat Step 2's pass criteria at 1280×900. Confirm the editor's `max-w-2xl` container doesn't look oddly narrow on a wide screen — if it does, note this as a candidate polish item for Slice 2C rather than changing layout now (scope discipline: 2A's job is correctness and the two sections, not final visual polish across all breakpoints).

- [ ] **Step 6: Record the result**

Update `PROJECT_STATE.md`'s Slice 2 entry (once all of Slice 2A/2B/2C are complete and merged, per the Slice 1 precedent — do not update `PROJECT_STATE.md` after 2A alone, since Slice 2 as a whole is still active). For now, note the verification outcome (pass/fail per step, with any gaps found) in the pull-request description when 2A is opened for review.

---

## Self-Review Notes

- **Spec coverage:** User requirement 1 (explicit none/unavailable states) — Task 1 (schema only; UI lands in Slice 2B). Requirement 2 (mobile section-based editor) — Task 6. Requirement 3 (repeatable rows) — out of scope for 2A by design, owned by Slice 2B. Requirement 4 (partial autosave, `allergy_status` named example) — Tasks 4 and 5, with a dedicated regression test. Requirement 5 (IndexedDB outbox, idempotency, optimistic locking, conflict handling) — Tasks 2 and 3, proven end-to-end in Tasks 4, 5 and manually in Task 8. Requirement 6 (conditional allergy/ADR fields) — allergy fields in Task 5; ADR fields are Slice 2C's Conditional Clinical Activities section. Requirement 7 (de-identification warnings) — Task 5. Requirement 8 (student ownership, assigned-faculty visibility, institution isolation tests) — Task 7. Requirement 9 (device verification) — Task 8.
- **Placeholder scan:** No task contains "TBD"/"handle appropriately"/unshown code. Task 3's composable and store have no automated test of their own — this is called out explicitly as a deliberate, justified divergence (no JS unit-test runner exists in this repo) rather than a silently-skipped test.
- **Type consistency:** `SectionSyncService::sync()`'s return shape (`array{status, httpStatus, model}`) is identical across Tasks 2, 4 and 5. `useSectionSync`'s `SyncedSection` base type (`lock_version` + `updated_at`) is used consistently by both `CaseProfileSection.vue` and `HistoryDiagnosisSection.vue`. `HasSyncEnvelope` is defined once in Task 4 and reused unmodified in Task 5.
