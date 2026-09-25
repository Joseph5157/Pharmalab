# DIRECT-DOCUMENTATION-IMPL-01 — Slice 1 (Domain and Schema Reconciliation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconcile the accepted Pharm.D fixed-form field catalogue with the existing walking-skeleton clinical-case schema: add the minimum normalized tables for repeatable vitals, investigations, medicines and conditional clinical activities, fix one stable form version, add server request validation, add an authoritative completeness service, and add authorization policies for every new resource — without building any new UI, routes for the new child resources, or the full submission/validation policy (those are Slice 2 and Slice 3).

**Architecture:** Extend the existing `ClinicalCase` aggregate exactly the way `soap_notes` and `case_versions` already extend it: new columns directly on `clinical_cases`/`soap_notes` for scalar case-context fields, one new 1:1 `case_clinical_profiles` table for the history/diagnosis/allergy narrative, and four new 1:many child tables (`case_vitals`, `case_investigations`, `case_medications`, `case_clinical_activities`) for the repeatable/conditional sections. Every new table follows the established pattern: ULID primary key, `institution_id` + `BelongsToInstitution` global scope, `restrictOnDelete` foreign keys. No new controllers or routes are added for the child resources — only their models, policies and `FormRequest` validation rule sets, which Slice 2 will wire into new routes. The one existing route that does change is `POST /student/cases`, which gains the new optional case-context fields and starts writing a server-fixed `form_version`.

**Tech Stack:** Laravel 13 (PHP 8.4), Eloquent, PHPUnit, SQLite (`:memory:`) for the test suite, PostgreSQL in production. No frontend changes in this slice.

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 1 bullets under "Delivery slices"), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4, decisions in [`docs/decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md`](../../decisions/2026-09-25_PHASE1_CLINICAL_DOCUMENTATION_DECISIONS.md).

## Global Constraints

- Run every `php`, `composer`, `npm`, `vendor/bin/phpunit` command through the **PowerShell** tool, not Bash — `php` is only on PATH via PowerShell (Laravel Herd) in this environment; Bash's `php` is not found.
- Every new Eloquent model MUST use the `App\Models\Concerns\BelongsToInstitution` trait and `Illuminate\Database\Eloquent\Concerns\HasUlids`, exactly like `SoapNote`, `CaseVersion` and `CaseStatusTransition`. A model missing the trait leaks cross-institution rows through relation queries — this is the single most important rule in this plan.
- Every new foreign key uses `->constrained()->restrictOnDelete()` (or `->constrained('users')->restrictOnDelete()` for actor columns), matching every existing migration in `database/migrations/2026_09_21_110000_create_clinical_case_tables.php`.
- Use the `#[Fillable([...])]` attribute (not `protected $fillable`) on every new model, matching `ClinicalCase`, `SoapNote`, `CaseVersion`.
- `form_version` is never read from client input. It is always set server-side from `App\Enums\CaseFormVersion::PharmdV1->value` when a case is created. No task may add `form_version` to a `FormRequest`'s `rules()`.
- Do not add any new routes, controllers, or Inertia pages in this slice. Only `CaseController::store` (existing route) changes. Everything else (the 4 repeatable resources, the case profile) gets models + policies + `FormRequest` classes only — Slice 2 wires routes to them.
- Do not touch `App\Actions\SubmitCase`'s snapshot builder in this slice — extending the immutable submission snapshot to the new fields is explicitly Slice 3 ("Create an immutable snapshot tied to the fixed form version"). Touching it now would be redone in Slice 3.
- Do not implement the full submission-completeness validation policy (SpO₂ ranges, date ordering, "vitals or justified reason", de-identification attestation gating). `CaseCompletenessService` in this slice reports section presence only; Slice 3 owns the full validation policy.
- Preserve existing walking-skeleton cases: every new/altered column on `clinical_cases` and `soap_notes` is nullable or has a database-level `->default(...)`, so migrating an existing row never fails and never leaves it in an invalid state.

## Review Focus

- **Cross-institution leak through a forgotten trait.** A new model created without `BelongsToInstitution` would let a user from institution A load rows belonging to institution B through an Eloquent relation, bypassing every policy check. Each model task's test suite includes a `Gate::forUser($otherInstitutionUser)->denies(...)` assertion.
- **Client-supplied `form_version`.** If `StoreClinicalCaseRequest::rules()` ever includes a `form_version` key, a student could submit an arbitrary/future form version and break the "fixed at case creation" guarantee. Task 6's test posts a payload that includes a spoofed `form_version` and asserts the stored value is still the server default.
- **NOT NULL column added without a database default breaks the existing walking-skeleton rows.** `clinical_cases.form_version` must carry a `->default(App\Enums\CaseFormVersion::PharmdV1->value)` at the schema level (not just at the application layer), or migrating pre-existing rows fails on both SQLite and PostgreSQL. Task 1's test asserts a case created via a raw insert (no `form_version` supplied) still reads back the default.
- **Decimal casts return strings, not floats.** `weight_kg`/`height_cm` use Eloquent's `decimal:2` cast, which returns a string (e.g. `"70.50"`). A test asserting `assertEquals(70.5, $case->weight_kg)` would be comparing the wrong type; assertions must compare against the string form (`"70.50"`) or cast explicitly. Task 1's test is written this way deliberately.
- **Eager-creating child rows the student never touched.** The candidate spec's non-negotiable rule is "do not auto-create ADR/intervention/counselling/monitoring or profile drafts because a screen was opened." Task 3 explicitly tests that a freshly created `ClinicalCase` has `clinicalProfile()` return `null` until a `CaseClinicalProfile` row is deliberately inserted — this must not regress when Slice 2 wires up autosave.

---

## Task 1: Extend `clinical_cases` and `soap_notes` schema, add `CaseFormVersion` enum

**Files:**
- Create: `app/Enums/CaseFormVersion.php`
- Create: `database/migrations/2026_09_26_000000_extend_case_and_soap_tables_for_pharmd_baseline.php`
- Modify: `app/Models/ClinicalCase.php` (Fillable list, `casts()`, add `attestedBy()` relation)
- Modify: `app/Models/SoapNote.php` (Fillable list, add `casts()`)
- Test: `tests/Feature/PharmdCaseBaselineSchemaTest.php`

**Interfaces:**
- Produces: `App\Enums\CaseFormVersion::PharmdV1` (backed string enum, value `'pharmd-case-v1'`), new nullable columns `care_setting`, `hospital_day_at_first_review`, `information_source`, `weight_kg`, `height_cm`, `pregnancy_lactation_status`, `deidentification_attested_at`, `deidentification_attested_by` and non-null `form_version` on `clinical_cases`; new nullable columns `drug_related_problem_status` (string), `drug_related_problem_categories` (array cast) on `soap_notes`. `ClinicalCase::attestedBy(): BelongsTo<User, ClinicalCase>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmdCaseBaselineSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_case_created_without_an_explicit_form_version_receives_the_fixed_default(): void
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

        $this->assertSame(CaseFormVersion::PharmdV1->value, $case->fresh()->form_version);
    }

    public function test_case_context_and_attestation_fields_persist(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => null,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            'care_setting' => 'inpatient',
            'hospital_day_at_first_review' => 2,
            'information_source' => 'case sheet',
            'weight_kg' => 70.5,
            'height_cm' => 172.25,
            'pregnancy_lactation_status' => 'not_applicable',
        ]);

        $fresh = $case->fresh();
        $this->assertNull($fresh->deidentification_attested_at);
        $this->assertNull($fresh->deidentification_attested_by);
        $this->assertSame('inpatient', $fresh->care_setting);
        $this->assertSame(2, $fresh->hospital_day_at_first_review);
        $this->assertSame('70.50', $fresh->weight_kg);
        $this->assertSame('172.25', $fresh->height_cm);

        $fresh->update([
            'deidentification_attested_at' => now(),
            'deidentification_attested_by' => $student->id,
        ]);

        $this->assertTrue($fresh->fresh()->attestedBy->is($student));
    }

    public function test_soap_note_stores_drug_related_problem_status_and_categories(): void
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

        $soap = SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
            'drug_related_problem_status' => 'identified',
            'drug_related_problem_categories' => ['dose_too_low', 'monitoring_required'],
        ]);

        $fresh = $soap->fresh();
        $this->assertSame('identified', $fresh->drug_related_problem_status);
        $this->assertSame(['dose_too_low', 'monitoring_required'], $fresh->drug_related_problem_categories);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=PharmdCaseBaselineSchemaTest`
Expected: FAIL — `App\Enums\CaseFormVersion` not found / unknown column `form_version`.

- [ ] **Step 3: Add the `CaseFormVersion` enum**

```php
<?php

namespace App\Enums;

enum CaseFormVersion: string
{
    case PharmdV1 = 'pharmd-case-v1';

    public function label(): string
    {
        return match ($this) {
            self::PharmdV1 => 'Pharm.D Clinical Case — Form v1',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

```php
<?php

use App\Enums\CaseFormVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->string('form_version', 40)->default(CaseFormVersion::PharmdV1->value)->after('case_number');
            $table->string('care_setting', 30)->nullable()->after('sex');
            $table->unsignedSmallInteger('hospital_day_at_first_review')->nullable()->after('care_setting');
            $table->string('information_source', 60)->nullable()->after('hospital_day_at_first_review');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('information_source');
            $table->decimal('height_cm', 5, 2)->nullable()->after('weight_kg');
            $table->string('pregnancy_lactation_status', 30)->nullable()->after('height_cm');
            $table->timestamp('deidentification_attested_at')->nullable()->after('pregnancy_lactation_status');
            $table->foreignId('deidentification_attested_by')->nullable()->after('deidentification_attested_at')->constrained('users')->restrictOnDelete();
        });

        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->string('drug_related_problem_status', 30)->nullable()->after('plan');
            $table->json('drug_related_problem_categories')->nullable()->after('drug_related_problem_status');
        });
    }

    public function down(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->dropColumn(['drug_related_problem_status', 'drug_related_problem_categories']);
        });

        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deidentification_attested_by');
            $table->dropColumn([
                'form_version',
                'care_setting',
                'hospital_day_at_first_review',
                'information_source',
                'weight_kg',
                'height_cm',
                'pregnancy_lactation_status',
                'deidentification_attested_at',
            ]);
        });
    }
};
```

- [ ] **Step 5: Update `ClinicalCase` model**

In `app/Models/ClinicalCase.php`, add the import and extend the `Fillable` attribute:

```php
use App\Enums\CaseFormVersion;
```

Replace the `#[Fillable([...])]` array (lines 37–54) with:

```php
#[Fillable([
    'institution_id',
    'rotation_assignment_id',
    'student_id',
    'case_number',
    'status',
    'current_revision_number',
    'form_version',
    'encounter_date',
    'case_category',
    'age_value',
    'age_unit',
    'sex',
    'care_setting',
    'hospital_day_at_first_review',
    'information_source',
    'weight_kg',
    'height_cm',
    'pregnancy_lactation_status',
    'deidentification_attested_at',
    'deidentification_attested_by',
    'clinical_site_id',
    'department_id',
    'ward_id',
    'submitted_at',
    'approved_at',
])]
```

Replace `casts()` (lines 59–67) with:

```php
protected function casts(): array
{
    return [
        'status' => CaseStatus::class,
        'form_version' => CaseFormVersion::class,
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'encounter_date' => 'date',
        'deidentification_attested_at' => 'datetime',
        'weight_kg' => 'decimal:2',
        'height_cm' => 'decimal:2',
    ];
}
```

Add a new relation after `ward()` (after line 97):

```php
/** @return BelongsTo<User, $this> */
public function attestedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'deidentification_attested_by');
}
```

- [ ] **Step 6: Update `SoapNote` model**

In `app/Models/SoapNote.php`, extend the `Fillable` attribute (lines 27–38):

```php
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'revision_number',
    'subjective',
    'objective',
    'assessment',
    'plan',
    'drug_related_problem_status',
    'drug_related_problem_categories',
    'author_id',
    'last_saved_by',
    'lock_version',
])]
```

Add a `casts()` method (there is none today) right after the class opening (after `use BelongsToInstitution, HasUlids;`):

```php
protected function casts(): array
{
    return [
        'drug_related_problem_categories' => 'array',
    ];
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdCaseBaselineSchemaTest`
Expected: PASS.

- [ ] **Step 8: Run the full existing suite to confirm no regression**

Run (PowerShell): `php artisan test`
Expected: 73 existing tests still pass (71 passed / 2 skipped) plus the 3 new ones — 76 passed / 2 skipped.

- [ ] **Step 9: Commit**

```bash
git add app/Enums/CaseFormVersion.php database/migrations/2026_09_26_000000_extend_case_and_soap_tables_for_pharmd_baseline.php app/Models/ClinicalCase.php app/Models/SoapNote.php tests/Feature/PharmdCaseBaselineSchemaTest.php
git commit -m "feat: extend clinical case and SOAP schema with Pharm.D baseline fields"
```

---

## Task 2: Create the five repeatable/conditional detail tables (schema only)

**Files:**
- Create: `database/migrations/2026_09_26_000001_create_pharmd_case_detail_tables.php`
- Test: `tests/Feature/PharmdCaseDetailTablesSchemaTest.php`

**Interfaces:**
- Produces: tables `case_clinical_profiles` (unique on `clinical_case_id`), `case_vitals`, `case_investigations`, `case_medications`, `case_clinical_activities` — all with `id` (ULID), `institution_id`, `clinical_case_id`, `recorded_by`/`last_saved_by` (`users`), `created_at`/`updated_at`. No models yet — those are Tasks 3–5.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdCaseDetailTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_detail_tables_exist_with_their_key_columns(): void
    {
        $this->assertTrue(Schema::hasTable('case_clinical_profiles'));
        $this->assertTrue(Schema::hasColumns('case_clinical_profiles', [
            'id', 'institution_id', 'clinical_case_id', 'chief_complaints', 'history_present_illness',
            'diagnoses', 'past_medical_history', 'past_medical_history_none', 'allergy_status',
            'allergy_substance', 'allergy_reaction', 'last_saved_by', 'lock_version',
        ]));

        $this->assertTrue(Schema::hasTable('case_vitals'));
        $this->assertTrue(Schema::hasColumns('case_vitals', [
            'id', 'institution_id', 'clinical_case_id', 'observation_type', 'value_numeric', 'value_text', 'unit', 'observed_on', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_investigations'));
        $this->assertTrue(Schema::hasColumns('case_investigations', [
            'id', 'institution_id', 'clinical_case_id', 'test_name', 'result_type', 'result_value', 'unit', 'reference_range', 'reported_flag', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_medications'));
        $this->assertTrue(Schema::hasColumns('case_medications', [
            'id', 'institution_id', 'clinical_case_id', 'medication_context', 'generic_name', 'status', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_clinical_activities'));
        $this->assertTrue(Schema::hasColumns('case_clinical_activities', [
            'id', 'institution_id', 'clinical_case_id', 'activity_type', 'status', 'details', 'recorded_by',
        ]));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=PharmdCaseDetailTablesSchemaTest`
Expected: FAIL — tables do not exist.

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
        Schema::create('case_clinical_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->json('chief_complaints')->nullable();
            $table->text('history_present_illness')->nullable();
            $table->json('diagnoses')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->boolean('past_medical_history_none')->default(false);
            $table->text('past_surgical_history')->nullable();
            $table->string('adherence_status', 30)->nullable();
            $table->text('family_history')->nullable();
            $table->text('substance_history')->nullable();
            $table->text('examination_findings')->nullable();
            $table->string('allergy_status', 20)->default('unknown');
            $table->text('allergy_substance')->nullable();
            $table->text('allergy_reaction')->nullable();
            $table->foreignId('last_saved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique('clinical_case_id');
            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_vitals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('observation_type', 40);
            $table->decimal('value_numeric', 7, 2)->nullable();
            $table->string('value_text', 60)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->string('source', 60)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_investigations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('test_name', 120);
            $table->string('result_type', 20);
            $table->string('result_value', 255);
            $table->string('unit', 20)->nullable();
            $table->string('reference_range', 120)->nullable();
            $table->string('reported_flag', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->text('interpretation')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_medications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('medication_context', 20)->default('chart');
            $table->string('generic_name', 120);
            $table->string('brand_name', 120)->nullable();
            $table->string('indication', 255)->nullable();
            $table->string('dose_amount', 30)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('dosage_form', 30)->nullable();
            $table->string('route', 30)->nullable();
            $table->string('frequency', 60)->nullable();
            $table->string('start_reference', 30)->nullable();
            $table->string('stop_reference', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('prn_indication', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_clinical_activities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('activity_type', 20);
            $table->string('status', 30)->nullable();
            $table->json('details')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_clinical_activities');
        Schema::dropIfExists('case_medications');
        Schema::dropIfExists('case_investigations');
        Schema::dropIfExists('case_vitals');
        Schema::dropIfExists('case_clinical_profiles');
    }
};
```

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=PharmdCaseDetailTablesSchemaTest`
Expected: PASS. (The `case_clinical_profiles.clinical_case_id` unique constraint is exercised for real in Task 3's `test_duplicate_profile_for_the_same_case_is_rejected_by_the_database`, once the `CaseClinicalProfile` model exists to insert through.)

- [ ] **Step 5: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all previous + new tests pass.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_26_000001_create_pharmd_case_detail_tables.php tests/Feature/PharmdCaseDetailTablesSchemaTest.php
git commit -m "feat: create Pharm.D case detail tables for vitals, investigations, medications and activities"
```

---

## Task 3: `CaseClinicalProfile` model, relation, policy

**Files:**
- Create: `app/Models/CaseClinicalProfile.php`
- Create: `app/Policies/CaseClinicalProfilePolicy.php`
- Modify: `app/Models/ClinicalCase.php` (add `clinicalProfile()` relation)
- Test: `tests/Feature/CaseClinicalProfileTest.php`

**Interfaces:**
- Consumes: `ClinicalCase` (Task 1), `BelongsToInstitution` trait, `CaseStatus` enum.
- Produces: `App\Models\CaseClinicalProfile` (fillable: `institution_id`, `clinical_case_id`, `chief_complaints`, `history_present_illness`, `diagnoses`, `past_medical_history`, `past_medical_history_none`, `past_surgical_history`, `adherence_status`, `family_history`, `substance_history`, `examination_findings`, `allergy_status`, `allergy_substance`, `allergy_reaction`, `last_saved_by`, `lock_version`), `ClinicalCase::clinicalProfile(): HasOne<CaseClinicalProfile, ClinicalCase>`, `App\Policies\CaseClinicalProfilePolicy::{view,update}(User, CaseClinicalProfile): bool`.

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
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseClinicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_case_has_no_profile_until_one_is_deliberately_created(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $this->assertNull($case->fresh()->clinicalProfile);
    }

    public function test_profile_can_be_created_and_belongs_to_the_case(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'history_present_illness' => 'Three-day history of headache.',
            'diagnoses' => [['label' => 'Tension-type headache', 'type' => 'provisional']],
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->clinicalProfile->is($profile));
        $this->assertSame('Three-day history of headache.', $profile->fresh()->history_present_illness);
        $this->assertSame([['label' => 'Tension-type headache', 'type' => 'provisional']], $profile->fresh()->diagnoses);
    }

    public function test_duplicate_profile_for_the_same_case_is_rejected_by_the_database(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);
    }

    public function test_owning_student_may_view_and_update_their_draft_profile(): void
    {
        [$institution, $student, $case, $profile] = $this->makeCaseWithProfile();

        $this->assertTrue(Gate::forUser($student)->allows('view', $profile));
        $this->assertTrue(Gate::forUser($student)->allows('update', $profile));
    }

    public function test_a_different_student_cannot_view_the_profile(): void
    {
        [$institution, $student, $case, $profile] = $this->makeCaseWithProfile();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('update', $profile));
    }

    public function test_a_user_from_another_institution_cannot_view_the_profile(): void
    {
        [$institution, $student, $case, $profile] = $this->makeCaseWithProfile();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $profile));
    }

    public function test_student_cannot_update_the_profile_once_the_case_is_submitted(): void
    {
        [$institution, $student, $case, $profile] = $this->makeCaseWithProfile(CaseStatus::Submitted);

        $this->assertFalse(Gate::forUser($student)->allows('update', $profile));
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

    /** @return array{Institution, User, ClinicalCase, CaseClinicalProfile} */
    private function makeCaseWithProfile(CaseStatus $status = CaseStatus::Draft): array
    {
        [$institution, $student, $case] = $this->makeCase($status);

        $profile = CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'allergy_status' => 'unknown',
            'last_saved_by' => $student->id,
        ]);

        return [$institution, $student, $case, $profile];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseClinicalProfileTest`
Expected: FAIL — class `App\Models\CaseClinicalProfile` not found.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property array<int, array<string, mixed>>|null $chief_complaints
 * @property string|null $history_present_illness
 * @property array<int, array<string, mixed>>|null $diagnoses
 * @property string|null $past_medical_history
 * @property bool $past_medical_history_none
 * @property string|null $past_surgical_history
 * @property string|null $adherence_status
 * @property string|null $family_history
 * @property string|null $substance_history
 * @property string|null $examination_findings
 * @property string $allergy_status
 * @property string|null $allergy_substance
 * @property string|null $allergy_reaction
 * @property int $last_saved_by
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'chief_complaints',
    'history_present_illness',
    'diagnoses',
    'past_medical_history',
    'past_medical_history_none',
    'past_surgical_history',
    'adherence_status',
    'family_history',
    'substance_history',
    'examination_findings',
    'allergy_status',
    'allergy_substance',
    'allergy_reaction',
    'last_saved_by',
    'lock_version',
])]
class CaseClinicalProfile extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'chief_complaints' => 'array',
            'diagnoses' => 'array',
            'past_medical_history_none' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lastSavedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_saved_by');
    }
}
```

- [ ] **Step 4: Add the relation to `ClinicalCase`**

In `app/Models/ClinicalCase.php`, add after `attestedBy()` (added in Task 1):

```php
/** @return HasOne<CaseClinicalProfile, $this> */
public function clinicalProfile(): HasOne
{
    return $this->hasOne(CaseClinicalProfile::class);
}
```

`HasOne` is already imported (used by `currentSoap()`).

- [ ] **Step 5: Write the policy**

```php
<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseClinicalProfile;
use App\Models\User;

class CaseClinicalProfilePolicy
{
    public function view(User $user, CaseClinicalProfile $profile): bool
    {
        if ($profile->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $profile->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseClinicalProfile $profile): bool
    {
        if (! $this->view($user, $profile)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($profile->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseClinicalProfileTest`
Expected: PASS (7 tests).

- [ ] **Step 7: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add app/Models/CaseClinicalProfile.php app/Policies/CaseClinicalProfilePolicy.php app/Models/ClinicalCase.php tests/Feature/CaseClinicalProfileTest.php
git commit -m "feat: add CaseClinicalProfile model and policy"
```

---

## Task 4: `CaseVital` and `CaseInvestigation` models, relations, policies

**Files:**
- Create: `app/Models/CaseVital.php`
- Create: `app/Models/CaseInvestigation.php`
- Create: `app/Policies/CaseVitalPolicy.php`
- Create: `app/Policies/CaseInvestigationPolicy.php`
- Modify: `app/Models/ClinicalCase.php` (add `vitals()`, `investigations()` relations)
- Test: `tests/Feature/CaseVitalAndInvestigationTest.php`

**Interfaces:**
- Consumes: `ClinicalCase`, `CaseStatus`, `BelongsToInstitution`.
- Produces: `App\Models\CaseVital` (fillable: `institution_id`, `clinical_case_id`, `observation_type`, `value_numeric`, `value_text`, `unit`, `observed_on`, `observed_at_time`, `source`, `note`, `recorded_by`), `App\Models\CaseInvestigation` (fillable: `institution_id`, `clinical_case_id`, `test_name`, `result_type`, `result_value`, `unit`, `reference_range`, `reported_flag`, `observed_on`, `observed_at_time`, `interpretation`, `recorded_by`), `ClinicalCase::vitals(): HasMany<CaseVital, ClinicalCase>`, `ClinicalCase::investigations(): HasMany<CaseInvestigation, ClinicalCase>`, `CaseVitalPolicy::{view,update}`, `CaseInvestigationPolicy::{view,update}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseInvestigation;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseVitalAndInvestigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vital_row_is_only_created_when_deliberately_saved(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->vitals);
    }

    public function test_vital_belongs_to_the_case_and_is_visible_to_the_owning_student(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'blood_pressure',
            'value_text' => '120/80',
            'unit' => 'mmHg',
            'observed_on' => '2026-10-15',
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->vitals->first()->is($vital));
        $this->assertTrue(Gate::forUser($student)->allows('view', $vital));
    }

    public function test_investigation_belongs_to_the_case_and_is_visible_to_the_owning_student(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($case->fresh()->investigations->first()->is($investigation));
        $this->assertTrue(Gate::forUser($student)->allows('view', $investigation));
    }

    public function test_a_different_student_cannot_view_vitals_or_investigations(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'unit' => 'beats/min',
            'recorded_by' => $student->id,
        ]);
        $investigation = CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Sodium',
            'result_type' => 'numeric',
            'result_value' => '140',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $vital));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $investigation));
    }

    public function test_a_user_from_another_institution_cannot_view_vitals_or_investigations(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'temperature',
            'value_numeric' => 37,
            'unit' => '°C',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $vital));
    }

    public function test_student_cannot_update_vitals_once_the_case_is_submitted(): void
    {
        [$institution, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $vital = CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'spo2',
            'value_numeric' => 98,
            'unit' => '%',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($student)->allows('update', $vital));
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

Run (PowerShell): `php artisan test --filter=CaseVitalAndInvestigationTest`
Expected: FAIL — `App\Models\CaseVital` not found.

- [ ] **Step 3: Write `CaseVital`**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $observation_type
 * @property string|null $value_numeric
 * @property string|null $value_text
 * @property string|null $unit
 * @property string|null $observed_on
 * @property string|null $observed_at_time
 * @property string|null $source
 * @property string|null $note
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'observation_type',
    'value_numeric',
    'value_text',
    'unit',
    'observed_on',
    'observed_at_time',
    'source',
    'note',
    'recorded_by',
])]
class CaseVital extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

- [ ] **Step 4: Write `CaseInvestigation`**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $test_name
 * @property string $result_type
 * @property string $result_value
 * @property string|null $unit
 * @property string|null $reference_range
 * @property string|null $reported_flag
 * @property string|null $observed_on
 * @property string|null $observed_at_time
 * @property string|null $interpretation
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'test_name',
    'result_type',
    'result_value',
    'unit',
    'reference_range',
    'reported_flag',
    'observed_on',
    'observed_at_time',
    'interpretation',
    'recorded_by',
])]
class CaseInvestigation extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

- [ ] **Step 5: Add relations to `ClinicalCase`**

Add after `clinicalProfile()`:

```php
/** @return HasMany<CaseVital, $this> */
public function vitals(): HasMany
{
    return $this->hasMany(CaseVital::class);
}

/** @return HasMany<CaseInvestigation, $this> */
public function investigations(): HasMany
{
    return $this->hasMany(CaseInvestigation::class);
}
```

`HasMany` is already imported.

- [ ] **Step 6: Write `CaseVitalPolicy`**

```php
<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseVital;
use App\Models\User;

class CaseVitalPolicy
{
    public function view(User $user, CaseVital $vital): bool
    {
        if ($vital->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $vital->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseVital $vital): bool
    {
        if (! $this->view($user, $vital)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($vital->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
```

- [ ] **Step 7: Write `CaseInvestigationPolicy`**

```php
<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseInvestigation;
use App\Models\User;

class CaseInvestigationPolicy
{
    public function view(User $user, CaseInvestigation $investigation): bool
    {
        if ($investigation->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $investigation->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseInvestigation $investigation): bool
    {
        if (! $this->view($user, $investigation)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($investigation->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseVitalAndInvestigationTest`
Expected: PASS (6 tests).

- [ ] **Step 9: Run the full suite**

Run (PowerShell): `php artisan test`

- [ ] **Step 10: Commit**

```bash
git add app/Models/CaseVital.php app/Models/CaseInvestigation.php app/Policies/CaseVitalPolicy.php app/Policies/CaseInvestigationPolicy.php app/Models/ClinicalCase.php tests/Feature/CaseVitalAndInvestigationTest.php
git commit -m "feat: add CaseVital and CaseInvestigation models and policies"
```

---

## Task 5: `CaseMedication` and `CaseClinicalActivity` models, enums, relations, policies

**Files:**
- Create: `app/Enums/MedicationStatus.php`
- Create: `app/Enums/ClinicalActivityType.php`
- Create: `app/Models/CaseMedication.php`
- Create: `app/Models/CaseClinicalActivity.php`
- Create: `app/Policies/CaseMedicationPolicy.php`
- Create: `app/Policies/CaseClinicalActivityPolicy.php`
- Modify: `app/Models/ClinicalCase.php` (add `medications()`, `clinicalActivities()` relations)
- Test: `tests/Feature/CaseMedicationAndClinicalActivityTest.php`

**Interfaces:**
- Consumes: `ClinicalCase`, `CaseStatus`, `BelongsToInstitution`.
- Produces: `App\Enums\MedicationStatus` (`Active`, `Stopped`, `OnHold`, `Completed`, `Prn`), `App\Enums\ClinicalActivityType` (`Intervention`, `Adr`, `Counselling`, `Monitoring`), `App\Models\CaseMedication` (fillable: `institution_id`, `clinical_case_id`, `medication_context`, `generic_name`, `brand_name`, `indication`, `dose_amount`, `dose_unit`, `dosage_form`, `route`, `frequency`, `start_reference`, `stop_reference`, `status`, `prn_indication`, `notes`, `recorded_by`), `App\Models\CaseClinicalActivity` (fillable: `institution_id`, `clinical_case_id`, `activity_type`, `status`, `details`, `recorded_by`), `ClinicalCase::medications(): HasMany`, `ClinicalCase::clinicalActivities(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Enums\MedicationStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseMedicationAndClinicalActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_medication_or_activity_rows_exist_until_deliberately_saved(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->medications);
        $this->assertCount(0, $case->fresh()->clinicalActivities);
    }

    public function test_medication_row_persists_with_its_status_enum_value(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'chart',
            'generic_name' => 'Paracetamol',
            'dose_amount' => '500',
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'TID',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $fresh = $medication->fresh();
        $this->assertSame(MedicationStatus::Active->value, $fresh->status);
        $this->assertTrue($case->fresh()->medications->first()->is($medication));
        $this->assertTrue(Gate::forUser($student)->allows('view', $medication));
    }

    public function test_clinical_activity_stores_a_typed_json_details_payload(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Adr->value,
            'status' => 'suspected_adr_yes',
            'details' => ['event' => 'Rash', 'seriousness' => 'non_serious'],
            'recorded_by' => $student->id,
        ]);

        $fresh = $activity->fresh();
        $this->assertSame(['event' => 'Rash', 'seriousness' => 'non_serious'], $fresh->details);
        $this->assertTrue($case->fresh()->clinicalActivities->first()->is($activity));
        $this->assertTrue(Gate::forUser($student)->allows('view', $activity));
    }

    public function test_a_different_student_cannot_view_medications_or_activities(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Amoxicillin',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);
        $activity = CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => ClinicalActivityType::Counselling->value,
            'status' => 'performed',
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $medication));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $activity));
    }

    public function test_a_user_from_another_institution_cannot_view_medications_or_activities(): void
    {
        [$institution, $student, $case] = $this->makeCase();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Ibuprofen',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $medication));
    }

    public function test_student_cannot_update_medications_or_activities_once_the_case_is_submitted(): void
    {
        [$institution, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $medication = CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'generic_name' => 'Metformin',
            'status' => MedicationStatus::Active->value,
            'recorded_by' => $student->id,
        ]);

        $this->assertFalse(Gate::forUser($student)->allows('update', $medication));
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

Run (PowerShell): `php artisan test --filter=CaseMedicationAndClinicalActivityTest`
Expected: FAIL — `App\Models\CaseMedication` not found.

- [ ] **Step 3: Write the enums**

```php
<?php

namespace App\Enums;

enum MedicationStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Prn = 'prn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Stopped => 'Stopped',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Prn => 'PRN',
        };
    }
}
```

```php
<?php

namespace App\Enums;

enum ClinicalActivityType: string
{
    case Intervention = 'intervention';
    case Adr = 'adr';
    case Counselling = 'counselling';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return match ($this) {
            self::Intervention => 'Pharmacist intervention',
            self::Adr => 'Suspected ADR',
            self::Counselling => 'Patient counselling',
            self::Monitoring => 'Monitoring follow-up',
        };
    }
}
```

- [ ] **Step 4: Write `CaseMedication`**

```php
<?php

namespace App\Models;

use App\Enums\MedicationStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $medication_context
 * @property string $generic_name
 * @property string|null $brand_name
 * @property string|null $indication
 * @property string|null $dose_amount
 * @property string|null $dose_unit
 * @property string|null $dosage_form
 * @property string|null $route
 * @property string|null $frequency
 * @property string|null $start_reference
 * @property string|null $stop_reference
 * @property MedicationStatus $status
 * @property string|null $prn_indication
 * @property string|null $notes
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'medication_context',
    'generic_name',
    'brand_name',
    'indication',
    'dose_amount',
    'dose_unit',
    'dosage_form',
    'route',
    'frequency',
    'start_reference',
    'stop_reference',
    'status',
    'prn_indication',
    'notes',
    'recorded_by',
])]
class CaseMedication extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => MedicationStatus::class,
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

- [ ] **Step 5: Write `CaseClinicalActivity`**

```php
<?php

namespace App\Models;

use App\Enums\ClinicalActivityType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property ClinicalActivityType $activity_type
 * @property string|null $status
 * @property array<string, mixed>|null $details
 * @property int $recorded_by
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'activity_type',
    'status',
    'details',
    'recorded_by',
])]
class CaseClinicalActivity extends Model
{
    use BelongsToInstitution, HasUlids;

    protected function casts(): array
    {
        return [
            'activity_type' => ClinicalActivityType::class,
            'details' => 'array',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

- [ ] **Step 6: Add relations to `ClinicalCase`**

Add after `investigations()`:

```php
/** @return HasMany<CaseMedication, $this> */
public function medications(): HasMany
{
    return $this->hasMany(CaseMedication::class);
}

/** @return HasMany<CaseClinicalActivity, $this> */
public function clinicalActivities(): HasMany
{
    return $this->hasMany(CaseClinicalActivity::class);
}
```

- [ ] **Step 7: Write `CaseMedicationPolicy`**

```php
<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseMedication;
use App\Models\User;

class CaseMedicationPolicy
{
    public function view(User $user, CaseMedication $medication): bool
    {
        if ($medication->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $medication->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseMedication $medication): bool
    {
        if (! $this->view($user, $medication)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($medication->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
```

- [ ] **Step 8: Write `CaseClinicalActivityPolicy`**

```php
<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseClinicalActivity;
use App\Models\User;

class CaseClinicalActivityPolicy
{
    public function view(User $user, CaseClinicalActivity $activity): bool
    {
        if ($activity->institution_id !== $user->institution_id) {
            return false;
        }

        $case = $activity->clinicalCase;

        if ($user->role === UserRole::Student) {
            return $case->student_id === $user->id;
        }

        if ($user->role === UserRole::Faculty) {
            return $case->rotationAssignment->primary_preceptor_id === $user->id;
        }

        return false;
    }

    public function update(User $user, CaseClinicalActivity $activity): bool
    {
        if (! $this->view($user, $activity)) {
            return false;
        }

        if ($user->role !== UserRole::Student) {
            return false;
        }

        return in_array($activity->clinicalCase->status, [CaseStatus::Draft, CaseStatus::Returned]);
    }
}
```

- [ ] **Step 9: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseMedicationAndClinicalActivityTest`
Expected: PASS (6 tests).

- [ ] **Step 10: Run the full suite**

Run (PowerShell): `php artisan test`

- [ ] **Step 11: Commit**

```bash
git add app/Enums/MedicationStatus.php app/Enums/ClinicalActivityType.php app/Models/CaseMedication.php app/Models/CaseClinicalActivity.php app/Policies/CaseMedicationPolicy.php app/Policies/CaseClinicalActivityPolicy.php app/Models/ClinicalCase.php tests/Feature/CaseMedicationAndClinicalActivityTest.php
git commit -m "feat: add CaseMedication and CaseClinicalActivity models and policies"
```

---

## Task 6: Request validation for the new fields, wired into case creation

**Files:**
- Create: `app/Http/Requests/Student/StoreClinicalCaseRequest.php`
- Create: `app/Http/Requests/Student/UpdateCaseClinicalProfileRequest.php`
- Create: `app/Http/Requests/Student/StoreCaseVitalRequest.php`
- Create: `app/Http/Requests/Student/StoreCaseInvestigationRequest.php`
- Create: `app/Http/Requests/Student/StoreCaseMedicationRequest.php`
- Create: `app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php`
- Modify: `app/Http/Controllers/Student/CaseController.php` (`store()`)
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend), `tests/Unit/Requests/CaseDetailRequestRulesTest.php` (new)

**Interfaces:**
- Consumes: `ClinicalCase`, `CaseFormVersion` (Task 1), `ClinicalActivityType`, `MedicationStatus` (Task 5).
- Produces: `StoreClinicalCaseRequest::rules(): array` (no `form_version` key — never client-settable), the other five `FormRequest::rules()` methods, each `authorize()` delegating to `$this->user()?->can('update', $this->route('case'))`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ClinicalCaseWorkflowTest.php` (new test method inside the existing class, after `test_student_can_resubmit_returned_case`):

```php
    public function test_creating_a_case_persists_new_context_fields_and_always_uses_the_server_form_version(): void
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

        $this->actingAs($student);

        $response = $this->post(route('student.cases.store'), [
            'rotation_assignment_id' => $assignment->id,
            'care_setting' => 'inpatient',
            'hospital_day_at_first_review' => 3,
            'information_source' => 'case sheet',
            'weight_kg' => 65.25,
            'height_cm' => 168,
            'form_version' => 'spoofed-version',
        ]);

        $response->assertRedirect();

        $case = \App\Models\ClinicalCase::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(\App\Enums\CaseFormVersion::PharmdV1->value, $case->form_version);
        $this->assertSame('inpatient', $case->care_setting);
        $this->assertSame(3, $case->hospital_day_at_first_review);
        $this->assertSame('65.25', $case->weight_kg);
    }
```

Create `tests/Unit/Requests/CaseDetailRequestRulesTest.php`:

```php
<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Student\StoreCaseClinicalActivityRequest;
use App\Http\Requests\Student\StoreCaseInvestigationRequest;
use App\Http\Requests\Student\StoreCaseMedicationRequest;
use App\Http\Requests\Student\StoreCaseVitalRequest;
use App\Http\Requests\Student\UpdateCaseClinicalProfileRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CaseDetailRequestRulesTest extends TestCase
{
    public function test_vital_request_requires_observation_type(): void
    {
        $rules = (new StoreCaseVitalRequest())->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make([
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'unit' => 'beats/min',
        ], $rules)->passes());
    }

    public function test_investigation_request_rejects_an_unknown_result_type(): void
    {
        $rules = (new StoreCaseInvestigationRequest())->rules();

        $this->assertTrue(Validator::make([
            'test_name' => 'Haemoglobin',
            'result_type' => 'not_a_real_type',
            'result_value' => '13.5',
        ], $rules)->fails());

        $this->assertTrue(Validator::make([
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
        ], $rules)->passes());
    }

    public function test_medication_request_rejects_an_unknown_status(): void
    {
        $rules = (new StoreCaseMedicationRequest())->rules();

        $this->assertTrue(Validator::make([
            'generic_name' => 'Paracetamol',
            'status' => 'not_a_real_status',
        ], $rules)->fails());

        $this->assertTrue(Validator::make([
            'generic_name' => 'Paracetamol',
            'status' => 'active',
        ], $rules)->passes());
    }

    public function test_clinical_activity_request_requires_a_known_activity_type(): void
    {
        $rules = (new StoreCaseClinicalActivityRequest())->rules();

        $this->assertTrue(Validator::make(['activity_type' => 'not_real'], $rules)->fails());
        $this->assertTrue(Validator::make(['activity_type' => 'adr'], $rules)->passes());
    }

    public function test_clinical_profile_update_request_rejects_an_unknown_allergy_status(): void
    {
        $rules = (new UpdateCaseClinicalProfileRequest())->rules();

        $this->assertTrue(Validator::make(['allergy_status' => 'not_real'], $rules)->fails());
        $this->assertTrue(Validator::make(['allergy_status' => 'no_known_allergy'], $rules)->passes());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell):
```
php artisan test --filter=ClinicalCaseWorkflowTest
php artisan test --filter=CaseDetailRequestRulesTest
```
Expected: FAIL — new test method fails because `form_version` isn't yet settable/stored via the controller (still the migration-level default only, but `care_setting` etc. aren't accepted by the current inline `$request->validate()`), and `CaseDetailRequestRulesTest` fails because the `App\Http\Requests\Student\*` classes don't exist.

- [ ] **Step 3: Write `StoreClinicalCaseRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Models\ClinicalCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ClinicalCase::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $institutionId = $this->user()->institution_id;

        return [
            'rotation_assignment_id' => [
                'required',
                Rule::exists('rotation_assignments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)->where('student_id', $this->user()->id)->where('status', 'active')),
            ],
            'encounter_date' => ['nullable', 'date'],
            'case_category' => ['nullable', 'string', 'max:80'],
            'age_value' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_unit' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', Rule::in(['male', 'female', 'intersex', 'unknown'])],
            'clinical_site_id' => ['nullable', Rule::exists('clinical_sites', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId))],
            'care_setting' => ['nullable', Rule::in(['inpatient', 'outpatient', 'emergency', 'other'])],
            'hospital_day_at_first_review' => ['nullable', 'integer', 'min:1', 'max:999'],
            'information_source' => ['nullable', 'string', 'max:60'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'pregnancy_lactation_status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
```

- [ ] **Step 4: Write `UpdateCaseClinicalProfileRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseClinicalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chief_complaints' => ['nullable', 'array'],
            'chief_complaints.*.complaint' => ['required_with:chief_complaints', 'string', 'max:255'],
            'chief_complaints.*.duration' => ['nullable', 'string', 'max:60'],
            'history_present_illness' => ['nullable', 'string', 'max:5000'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.label' => ['required_with:diagnoses', 'string', 'max:255'],
            'diagnoses.*.type' => ['nullable', Rule::in(['provisional', 'confirmed', 'comorbidity'])],
            'past_medical_history' => ['nullable', 'string', 'max:5000'],
            'past_medical_history_none' => ['boolean'],
            'past_surgical_history' => ['nullable', 'string', 'max:5000'],
            'adherence_status' => ['nullable', Rule::in(['adherent', 'partially_adherent', 'non_adherent', 'unable_to_assess'])],
            'family_history' => ['nullable', 'string', 'max:5000'],
            'substance_history' => ['nullable', 'string', 'max:5000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'allergy_status' => ['required', Rule::in(['no_known_allergy', 'known_allergy', 'unknown'])],
            'allergy_substance' => ['nullable', 'required_if:allergy_status,known_allergy', 'string', 'max:1000'],
            'allergy_reaction' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Write `StoreCaseVitalRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'observation_type' => ['required', 'string', 'max:40'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_text' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:20'],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'source' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 6: Write `StoreCaseInvestigationRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'test_name' => ['required', 'string', 'max:120'],
            'result_type' => ['required', Rule::in(['numeric', 'qualitative', 'narrative'])],
            'result_value' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'reference_range' => ['nullable', 'string', 'max:120'],
            'reported_flag' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical', 'not_stated'])],
            'observed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'observed_at_time' => ['nullable', 'date_format:H:i'],
            'interpretation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 7: Write `StoreCaseMedicationRequest`**

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
            'stop_reference' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_map(fn (MedicationStatus $s) => $s->value, MedicationStatus::cases()))],
            'prn_indication' => ['nullable', 'required_if:status,prn', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 8: Write `StoreCaseClinicalActivityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Enums\ClinicalActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseClinicalActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in(array_map(fn (ClinicalActivityType $t) => $t->value, ClinicalActivityType::cases()))],
            'status' => ['nullable', 'string', 'max:30'],
            'details' => ['nullable', 'array'],
        ];
    }
}
```

- [ ] **Step 9: Wire `StoreClinicalCaseRequest` into `CaseController::store`**

In `app/Http/Controllers/Student/CaseController.php`, replace the `store()` method (lines 34–78):

```php
public function store(StoreClinicalCaseRequest $request, AuditTrail $audit): RedirectResponse
{
    $data = $request->validated();

    $case = DB::transaction(function () use ($request, $audit, $data): ClinicalCase {
        $user = $request->user();
        $lastCaseNumber = ClinicalCase::query()
            ->where('student_id', $user->id)
            ->max('case_number') ?? 0;

        $case = ClinicalCase::query()->create([
            ...$data,
            'institution_id' => $user->institution_id,
            'student_id' => $user->id,
            'case_number' => $lastCaseNumber + 1,
            'status' => CaseStatus::Draft,
            'current_revision_number' => 0,
            'form_version' => CaseFormVersion::PharmdV1->value,
        ]);

        $audit->record($user, $case, 'clinical_case.created', [
            'case_id' => $case->id,
            'case_number' => $case->case_number,
        ]);

        return $case;
    });

    return redirect()->route('student.cases.show', $case);
}
```

Update the top of the file: remove the now-unused `use Illuminate\Support\Facades\Gate;` and `use Illuminate\Validation\Rule;` imports (no longer referenced in this class — `index()` still needs `Gate`, so check before removing: `index()` still calls `Gate::authorize('viewAny', ClinicalCase::class)`, so **keep** the `Gate` import; only drop `Illuminate\Validation\Rule`). Add:

```php
use App\Enums\CaseFormVersion;
use App\Http\Requests\Student\StoreClinicalCaseRequest;
```

- [ ] **Step 10: Run the tests to verify they pass**

Run (PowerShell):
```
php artisan test --filter=ClinicalCaseWorkflowTest
php artisan test --filter=CaseDetailRequestRulesTest
```
Expected: PASS.

- [ ] **Step 11: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: all tests pass (existing 73 + all new tests from Tasks 1–6).

- [ ] **Step 12: Run static analysis and formatting**

Run (PowerShell):
```
vendor/bin/phpstan analyse
vendor/bin/pint --test
```
Fix any reported issues (in particular: PHPStan level 7 may flag the `array_map(fn (...) => ..., Enum::cases())` closures — add explicit param/return types if flagged) before proceeding.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Requests/Student app/Http/Controllers/Student/CaseController.php tests/Feature/ClinicalCaseWorkflowTest.php tests/Unit/Requests/CaseDetailRequestRulesTest.php
git commit -m "feat: add Pharm.D detail request validation and fix form_version server-side"
```

---

## Task 7: `CaseCompletenessService`

**Files:**
- Create: `app/Services/CaseCompletenessService.php`
- Test: `tests/Unit/Services/CaseCompletenessServiceTest.php`

**Interfaces:**
- Consumes: `ClinicalCase` with its `clinicalProfile`, `vitals`, `investigations`, `medications`, `currentSoap` relations (Tasks 1, 3, 4, 5).
- Produces: `CaseCompletenessService::sectionCompletion(ClinicalCase $case): array<string, bool>` (keys: `case_context`, `history_and_diagnosis`, `vitals`, `investigations`, `medication_chart`, `soap`), `CaseCompletenessService::missingSections(ClinicalCase $case): array<int, string>`, `CaseCompletenessService::isReadyForSubmission(ClinicalCase $case): bool`. This is a presence-only check for this slice — full completeness/technical validation (date ordering, SpO₂ bounds, "vitals or justified reason") is Slice 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalProfile;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\Institution;
use App\Models\SoapNote;
use App\Models\User;
use App\Services\CaseCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseCompletenessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_case_is_missing_every_section(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $service = new CaseCompletenessService();

        $this->assertSame([
            'case_context' => false,
            'history_and_diagnosis' => false,
            'vitals' => false,
            'investigations' => false,
            'medication_chart' => false,
            'soap' => false,
        ], $service->sectionCompletion($case->fresh()));
        $this->assertFalse($service->isReadyForSubmission($case->fresh()));
    }

    public function test_a_fully_documented_case_has_every_section_complete(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update(['care_setting' => 'inpatient', 'age_value' => 34, 'sex' => 'female']);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'history_present_illness' => 'Three-day headache.',
            'diagnoses' => [['label' => 'Tension headache']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'recorded_by' => $student->id,
        ]);

        \App\Models\CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'chart',
            'generic_name' => 'Paracetamol',
            'status' => 'active',
            'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S',
            'objective' => 'O',
            'assessment' => 'A',
            'plan' => 'P',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $service = new CaseCompletenessService();
        $fresh = $case->fresh();

        $this->assertSame([
            'case_context' => true,
            'history_and_diagnosis' => true,
            'vitals' => true,
            'investigations' => true,
            'medication_chart' => true,
            'soap' => true,
        ], $service->sectionCompletion($fresh));
        $this->assertTrue($service->isReadyForSubmission($fresh));
        $this->assertSame([], $service->missingSections($fresh));
    }

    public function test_missing_sections_lists_only_the_incomplete_ones(): void
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update(['care_setting' => 'inpatient', 'age_value' => 34, 'sex' => 'female']);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S',
            'objective' => 'O',
            'assessment' => 'A',
            'plan' => 'P',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $service = new CaseCompletenessService();

        $this->assertSame(
            ['history_and_diagnosis', 'vitals', 'investigations', 'medication_chart'],
            $service->missingSections($case->fresh()),
        );
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

Run (PowerShell): `php artisan test --filter=CaseCompletenessServiceTest`
Expected: FAIL — `App\Services\CaseCompletenessService` not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use App\Models\ClinicalCase;

class CaseCompletenessService
{
    /** @return array<string, bool> */
    public function sectionCompletion(ClinicalCase $case): array
    {
        $profile = $case->clinicalProfile;
        $soap = $case->currentSoap;

        return [
            'case_context' => filled($case->care_setting) && $case->age_value !== null && filled($case->sex),
            'history_and_diagnosis' => $profile !== null
                && filled($profile->history_present_illness)
                && filled($profile->diagnoses)
                && ($profile->past_medical_history_none || filled($profile->past_medical_history))
                && filled($profile->allergy_status),
            'vitals' => $case->vitals()->exists(),
            'investigations' => $case->investigations()->exists(),
            'medication_chart' => $case->medications()->where('medication_context', 'chart')->exists(),
            'soap' => $soap !== null
                && filled($soap->subjective)
                && filled($soap->objective)
                && filled($soap->assessment)
                && filled($soap->plan),
        ];
    }

    /** @return list<string> */
    public function missingSections(ClinicalCase $case): array
    {
        return array_values(array_keys(array_filter(
            $this->sectionCompletion($case),
            fn (bool $complete): bool => ! $complete,
        )));
    }

    public function isReadyForSubmission(ClinicalCase $case): bool
    {
        return $this->missingSections($case) === [];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseCompletenessServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Run the full suite**

Run (PowerShell): `php artisan test`

- [ ] **Step 6: Commit**

```bash
git add app/Services/CaseCompletenessService.php tests/Unit/Services/CaseCompletenessServiceTest.php
git commit -m "feat: add CaseCompletenessService for section presence checks"
```

---

## Task 8: Full verification and wrap-up

**Files:** none created; this task only runs checks and, if needed, makes small fixes surfaced by them.

- [ ] **Step 1: Run the full automated test suite**

Run (PowerShell): `php artisan test`
Expected: every test passes (baseline 73 + all tests added in Tasks 1–7). Record the exact `passed`/`skipped`/`assertions` counts for the PR description.

- [ ] **Step 2: Run PHPStan**

Run (PowerShell): `vendor/bin/phpstan analyse`
Expected: no errors at level 7. Fix any that appear (most likely candidates: missing generic annotations on the new `HasMany`/`HasOne`/`BelongsTo` relations, or the `array_map` closures in `StoreCaseMedicationRequest`/`StoreCaseClinicalActivityRequest`).

- [ ] **Step 3: Run Pint**

Run (PowerShell): `vendor/bin/pint --test`
If it reports files needing formatting, run `vendor/bin/pint` (without `--test`) to fix them, then re-run `--test` to confirm clean.

- [ ] **Step 4: Run the frontend checks (unaffected by this slice, but part of the accepted CI gate)**

Run (PowerShell):
```
npm run check
npm run types:check
npm run build
```
Expected: all pass unchanged — this slice touches no frontend files, so these should be a no-op confirmation.

- [ ] **Step 5: Run a fresh migration to prove the accepted baseline migrates cleanly**

Run (PowerShell): `php artisan migrate:fresh --env=testing --force` against a scratch SQLite file, or rely on the `RefreshDatabase` trait already exercising this on every test run in Steps 1. If running manually: `php artisan migrate:fresh` is destructive to the local dev database — only run it against a disposable database, never the developer's working database, and ask before running it against anything but the in-memory test connection.

- [ ] **Step 6: Commit any fixes from Steps 2–4**

```bash
git add -A
git commit -m "chore: fix static analysis and formatting findings for Slice 1"
```

(Skip this commit if Steps 2–4 found nothing to fix.)

- [ ] **Step 7: Report Slice 1 completion**

Do not edit `PROJECT_STATE.md` as part of this task — per its maintenance rule, it is updated only when a milestone starts, completes and is accepted, a decision changes, or a blocker is found/resolved. Slice 1 completing is progress within the still-active `DIRECT-DOCUMENTATION-IMPL-01` gate, not gate completion, so `PROJECT_STATE.md` stays as-is until the whole gate (all 6 slices) is accepted and merged. Summarize for the human partner: which tables/models/policies/services were added, the final test/assertion counts, and that Slice 2 (mobile case editor UI) is the next piece of work per `docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`.
