# DIRECT-DOCUMENTATION-IMPL-01 — Slice 2C (SOAP Integration, Conditional Clinical Activities, Full Editor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (Native execution, chosen for the whole Slice 2 sequence). Steps use checkbox (`- [ ]`) syntax for tracking. **Depends on Slice 2A and Slice 2B being merged first** — this plan reuses every shared piece they built: `Syncable`, `SyncsWithLockVersion`, `SectionSyncService` (including `create()` from 2B), `HasSyncEnvelope`, `outboxStore.ts`, `useSectionSync.ts`, `DeidentificationNotice.vue`, and `CaseEditor.vue`.

**Goal:** Complete the six-section mobile case editor by folding SOAP into the same offline-sync engine as every other section (retiring the old plain-Inertia SOAP page), adding the four Conditional Clinical Activities (Pharmacist intervention, Suspected ADR, Patient counselling, Monitoring follow-up), and running full end-to-end device verification across all six sections together.

**Architecture:** The field catalogue's four "Conditional Clinical Activities" split into two genuinely different shapes, and the implementation follows that split rather than treating all four uniformly: **Suspected ADR** and **Patient counselling** are each a single case-level answer with conditional detail fields ("The student first answers Suspected ADR: Yes, No or Unable to assess... ADR details are required only for Yes"; "Every case records a status... capture counselling topics [only] when Performed or Planned is selected") — these reuse the exact lazy-singleton pattern Slice 2A built for `CaseClinicalProfile` (`firstOrCreate` inside the first `sync()` call), via two dedicated routes (`.../clinical-activities/adr`, `.../clinical-activities/counselling`). **Pharmacist intervention** and **Monitoring follow-up** are genuinely repeatable ("A separate follow-up result is created only when the student actually follows the case") and reuse Slice 2B's repeatable-row pattern (`store`/`sync`/`destroy` against `App\Models\CaseClinicalActivity`, `SectionSyncService::create()` for idempotent creation). Both flavors share one `CaseClinicalActivityController` and one underlying table (`case_clinical_activities`, already created in Slice 1 with an `activity_type` discriminator), but the generic `{activity}` route explicitly refuses to serve the two singleton types (`abort_unless(in_array($activity->activity_type, [Intervention, Monitoring]))`) so a request can never reach a row through the wrong door. SOAP folds into the same engine as any other section: `SoapNote` (which already carries an unused `lock_version` column from the original walking skeleton, and — like `CaseClinicalProfile` before this slice's fix — currently has `lock_version` mistakenly mass-fillable) becomes `Syncable`, and the standalone `/student/cases/{case}/soap` Inertia page is retired in favor of an in-editor SOAP section, consistent with the "one logical section at a time" mobile editor the field catalogue specifies.

**Tech Stack:** Same as Slices 2A/2B — Laravel 13, Eloquent, PHPUnit, SQLite (`:memory:`)/PostgreSQL; Inertia.js + Vue 3 + TypeScript, native `fetch` + IndexedDB.

**Spec:** [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) (Slice 2 requirements), field catalogue in [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4.6–4.7, Slice 2A plan at [`2026-09-25-direct-documentation-impl-01-slice-2a.md`](2026-09-25-direct-documentation-impl-01-slice-2a.md), Slice 2B plan at [`2026-09-25-direct-documentation-impl-01-slice-2b.md`](2026-09-25-direct-documentation-impl-01-slice-2b.md).

## Global Constraints

All constraints from Slices 2A and 2B apply unchanged. In addition:

- Route registration order matters: `PUT student/cases/{case}/clinical-activities/adr` and `PUT student/cases/{case}/clinical-activities/counselling` **must** be registered before `PUT student/cases/{case}/clinical-activities/{activity}` in `routes/web.php`, or Laravel will attempt to resolve the literal segments `adr`/`counselling` as a `{activity}` ULID route-model-binding and fail with a 404/500 instead of reaching the intended singleton controller method.
- The generic `{activity}` `sync`/`destroy` actions must refuse to operate on an `adr` or `counselling` row (`abort_unless(in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true), 404)`) even though route ordering already prevents normal traffic from reaching them that way — this is defense in depth against a client that discovers a singleton row's ULID (e.g. from an earlier JSON response) and tries to hit the generic row route directly.
- `SoapNote`'s `#[Fillable([...])]` currently lists `'lock_version'` (a Slice 1 regression, same shape as the `CaseClinicalProfile` one Slice 2A fixed). Task 1 removes it.
- Retiring the standalone SOAP page (`resources/js/pages/student/SoapEditor.vue`, the `student.cases.soap` GET route, `SoapController::show`) is an explicit, deliberate part of this slice, not incidental cleanup — the mobile editor's "one section at a time" requirement is not met while a separate full-page SOAP editor still exists outside it. Update every place that links to the old page.

## Review Focus

- **Wrong-door access to a singleton activity row.** A request to `PUT /student/cases/{case}/clinical-activities/{activity}` where `{activity}` is actually the case's ADR or Counselling row must be rejected (404), not silently accepted by the generic row endpoint and left inconsistent with what the singleton endpoint's `firstOrCreate` logic expects. Task 5's tests exercise this directly.
- **SOAP `lock_version` mass-assignment regression.** Same shape as the `CaseClinicalProfile` bug Slice 2A fixed: `SoapNote`'s Fillable must not include `'lock_version'`, or a client could set an arbitrary starting lock version. Task 1's test asserts a client-supplied `lock_version` in a create payload is ignored.
- **Conditional field partial-save gaps.** `details.event`/`details.suspected_medicine` (ADR) and any counselling detail fields must not be silently required or silently erased by a partial autosave that only touches one field within an already-`status: 'yes'`/`'performed'` row — same class of bug the `allergy_substance` fix addressed in Slice 2A, now proven against nested `details.*` fields.
- **Losing the old SOAP page's data path.** Retiring `SoapEditor.vue` must not lose the ability to read/write `subjective`/`objective`/`assessment`/`plan` — Task 1's tests prove the new sync endpoint round-trips all four fields plus the two new drug-related-problem fields, and Task 6 proves `CaseShow.vue` no longer links anywhere dead.
- **Six-section progress/navigation regressions.** Adding the fifth and sixth section to `CaseEditor.vue`'s `sections` array must not break Previous/Next boundary logic or the nav-pill `aria-current` state for the sections Slices 2A/2B already shipped. Task 7's test and Task 9's manual pass both re-verify all six sections, not just the two new ones.

---

## Task 1: Fold SOAP into the sync engine

**Files:**
- Modify: `app/Models/SoapNote.php` (remove `'lock_version'` from Fillable, implement `Syncable`)
- Create: `app/Http/Requests/Student/UpdateSoapNoteRequest.php`
- Modify: `app/Http/Controllers/Student/SoapController.php` (replace `update()` with `sync()`)
- Test: `tests/Feature/SoapNoteSyncTest.php`

**Interfaces:**
- Produces: `PUT /student/cases/{case}/soap` (existing route/name kept, behavior replaced) returning `{ "section": {subjective, objective, assessment, plan, drug_related_problem_status, drug_related_problem_categories, lock_version, updated_at} }`. Lazily creates revision 1 of the `SoapNote` on first sync, exactly as `CaseClinicalProfileController` lazily creates the profile (Slice 2A Task 5) — never on page render.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
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

    public function test_first_sync_lazily_creates_the_soap_note_and_persists_all_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(),
            'base_lock_version' => 0,
            'subjective' => 'Patient reports headache for 3 days.',
            'drug_related_problem_status' => 'identified',
            'drug_related_problem_categories' => ['dose_too_low', 'monitoring_required'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('section.subjective', 'Patient reports headache for 3 days.');
        $this->assertNotNull($case->fresh()->currentSoap);
        $this->assertSame(['dose_too_low', 'monitoring_required'], $case->fresh()->currentSoap->drug_related_problem_categories);
    }

    public function test_a_single_field_edit_does_not_erase_other_saved_fields(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0,
            'subjective' => 'Headache.', 'objective' => 'BP 140/90.',
        ])->assertOk();

        $response = $this->putJson("/student/cases/{$case->id}/soap", [
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

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'First',
        ])->assertOk();

        $this->putJson("/student/cases/{$case->id}/soap", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'subjective' => 'Conflicting',
        ])->assertStatus(409);
    }

    public function test_a_different_student_cannot_sync_the_soap_note(): void
    {
        [$institution, , $case] = $this->makeCase();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherStudent);

        $this->putJson("/student/cases/{$case->id}/soap", [
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
Expected: FAIL — old `SoapController::update` doesn't return `{ "section": ... }` JSON, has no envelope/lock handling.

- [ ] **Step 3: Fix `SoapNote`'s Fillable and implement `Syncable`**

In `app/Models/SoapNote.php`, remove `'lock_version',` from the `#[Fillable([...])]` array. Add imports and apply the trait/interface:

```php
use App\Contracts\Syncable;
use App\Models\Concerns\SyncsWithLockVersion;
```

```php
class SoapNote extends Model implements Syncable
{
    use BelongsToInstitution, HasUlids, SyncsWithLockVersion;
```

- [ ] **Step 4: Write `UpdateSoapNoteRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSoapNoteRequest extends FormRequest
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
            'subjective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'drug_related_problem_status' => ['sometimes', 'nullable', 'in:none_identified,identified,unable_to_assess'],
            'drug_related_problem_categories' => ['sometimes', 'nullable', 'array'],
            'drug_related_problem_categories.*' => ['in:untreated_indication,medicine_without_indication,ineffective_medicine,dose_too_low,dose_too_high,adr,interaction,non_adherence,duplication,administration_problem,monitoring_required,other'],
        ];
    }
}
```

- [ ] **Step 5: Replace `SoapController`**

Replace the full contents of `app/Http/Controllers/Student/SoapController.php`:

```php
<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateSoapNoteRequest;
use App\Models\ClinicalCase;
use App\Models\SoapNote;
use App\Services\SectionSyncService;
use Illuminate\Http\JsonResponse;

class SoapController extends Controller
{
    public function sync(UpdateSoapNoteRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $soap = $case->currentSoap;

        if ($soap === null) {
            $soap = SoapNote::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'revision_number' => 1,
                'author_id' => $request->user()->id,
                'last_saved_by' => $request->user()->id,
            ]);
        }

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
            'drug_related_problem_status' => $soap->drug_related_problem_status,
            'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            'lock_version' => $soap->lock_version,
            'updated_at' => $soap->updated_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Update the route**

In `routes/web.php`, replace the existing SOAP route pair:

```php
        Route::get('student/cases/{case}/soap', [SoapController::class, 'show'])->name('student.cases.soap'),
        Route::put('student/cases/{case}/soap', [SoapController::class, 'update'])->name('student.cases.soap.update'),
```

with just:

```php
        Route::put('student/cases/{case}/soap', [SoapController::class, 'sync'])->name('student.cases.soap.update');
```

(The `GET .../soap` route and `SoapEditor.vue` page are retired in Task 6, once the in-editor SOAP section exists to replace them — do not delete the page yet in this task, only repoint the `PUT` route's behavior, so the app stays functional between tasks. For now, this leaves the old page temporarily unable to save via `useForm`'s Inertia PUT, since the response shape changed from a redirect to JSON — this is expected and is resolved in Task 6.)

- [ ] **Step 7: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 8: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=SoapNoteSyncTest`
Expected: PASS (5 tests).

- [ ] **Step 9: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: previous Slice 2A+2B tests (187 passed / 2 skipped) plus 5 new ones — 192 passed / 2 skipped. (The old `SoapController` test coverage, if any existed in `ClinicalCaseWorkflowTest`, exercises the model layer directly and is unaffected; if any test directly posts to the old `SoapController::update`'s Inertia-redirect contract, it will now fail — find and update it as part of this step rather than leaving it broken.)

- [ ] **Step 10: Commit**

```bash
git add app/Models/SoapNote.php app/Http/Requests/Student/UpdateSoapNoteRequest.php app/Http/Controllers/Student/SoapController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/SoapNoteSyncTest.php
git commit -m "feat: fold SOAP into the sync engine and fix its lock_version mass-assignment regression"
```

---

## Task 2: `lock_version` and `Syncable` for `CaseClinicalActivity`

**Files:**
- Create: `database/migrations/2026_09_30_000000_add_lock_version_to_case_clinical_activities.php`
- Modify: `app/Models/CaseClinicalActivity.php` (implement `Syncable`)
- Test: `tests/Feature/CaseClinicalActivityLockVersionTest.php`

Identical shape to Slice 2B Task 1, applied to the one remaining repeatable-schema table that didn't get `lock_version` there because it wasn't in scope yet.

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
        $this->assertSame(0, $activity->getLockVersion());
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

Add imports and apply the trait/interface, same shape as every other model in this series:

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
Expected: 192 previous + 2 new — 194 passed / 2 skipped.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_30_000000_add_lock_version_to_case_clinical_activities.php app/Models/CaseClinicalActivity.php tests/Feature/CaseClinicalActivityLockVersionTest.php
git commit -m "feat: add lock_version and Syncable to CaseClinicalActivity"
```

---

## Task 3: Suspected ADR (singleton) backend

**Files:**
- Create: `app/Http/Requests/Student/UpdateAdrActivityRequest.php`
- Create: `app/Http/Controllers/Student/CaseClinicalActivityController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdrActivitySyncTest.php`

**Interfaces:**
- Produces: `PUT /student/cases/{case}/clinical-activities/adr` returning `{ "activity": {status, details, lock_version, updated_at} }`. Lazily creates the ADR row on first sync.

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

class AdrActivitySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_adr_row_exists_until_the_first_deliberate_sync(): void
    {
        [, , $case] = $this->makeCase();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
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

    public function test_a_single_field_edit_after_yes_does_not_re_require_the_others(): void
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
Expected: FAIL — route not found.

- [ ] **Step 3: Write `UpdateAdrActivityRequest`**

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasSyncEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdrActivityRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::in(['yes', 'no', 'unable_to_assess'])],
            'details' => ['sometimes', 'nullable', 'array'],
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

- [ ] **Step 4: Write `CaseClinicalActivityController` (ADR method only — Tasks 4–5 extend this same class)**

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

class CaseClinicalActivityController extends Controller
{
    public function syncAdr(UpdateAdrActivityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $activity = CaseClinicalActivity::query()->firstOrCreate(
            ['clinical_case_id' => $case->id, 'activity_type' => ClinicalActivityType::Adr->value],
            ['institution_id' => $case->institution_id, 'recorded_by' => $request->user()->id],
        );

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activity_adr',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
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

- [ ] **Step 5: Add the route**

In `routes/web.php`, add inside the `role:student` group, immediately after the Medication Chart routes. **This must come before Task 5's `{activity}` route** (see Global Constraints):

```php
        Route::put('student/cases/{case}/clinical-activities/adr', [CaseClinicalActivityController::class, 'syncAdr'])->name('student.cases.clinical-activities.adr.sync');
```

Add the import: `use App\Http\Controllers\Student\CaseClinicalActivityController;`

- [ ] **Step 6: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 7: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=AdrActivitySyncTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 194 previous + 6 new — 200 passed / 2 skipped.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Student/UpdateAdrActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/AdrActivitySyncTest.php
git commit -m "feat: add the Suspected ADR singleton section backend"
```

---

## Task 4: Patient counselling (singleton) backend

**Files:**
- Create: `app/Http/Requests/Student/UpdateCounsellingActivityRequest.php`
- Modify: `app/Http/Controllers/Student/CaseClinicalActivityController.php` (add `syncCounselling`)
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

    public function test_performed_with_topics_persists(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);

        $response = $this->putJson("/student/cases/{$case->id}/clinical-activities/counselling", [
            'client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0, 'status' => 'performed',
            'details' => ['topics' => 'Medicine purpose and dosing schedule.', 'understanding_checked' => true],
        ]);

        $response->assertOk();
        $this->assertSame('Medicine purpose and dosing schedule.', $case->fresh()->clinicalActivities->first()->details['topics']);
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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCounsellingActivityRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::in(['performed', 'planned', 'not_indicated', 'unable_to_perform'])],
            'details' => ['sometimes', 'nullable', 'array'],
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

Add the import `use App\Http\Requests\Student\UpdateCounsellingActivityRequest;` and this method (after `syncAdr`):

```php
    public function syncCounselling(UpdateCounsellingActivityRequest $request, ClinicalCase $case, SectionSyncService $sync): JsonResponse
    {
        $activity = CaseClinicalActivity::query()->firstOrCreate(
            ['clinical_case_id' => $case->id, 'activity_type' => ClinicalActivityType::Counselling->value],
            ['institution_id' => $case->institution_id, 'recorded_by' => $request->user()->id],
        );

        $envelope = $request->syncEnvelope();

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activity_counselling',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
    }
```

- [ ] **Step 5: Add the route**

Immediately after the ADR route, still before Task 5's `{activity}` route:

```php
        Route::put('student/cases/{case}/clinical-activities/counselling', [CaseClinicalActivityController::class, 'syncCounselling'])->name('student.cases.clinical-activities.counselling.sync');
```

- [ ] **Step 6: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 7: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=CounsellingActivitySyncTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 200 previous + 5 new — 205 passed / 2 skipped.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Student/UpdateCounsellingActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/CounsellingActivitySyncTest.php
git commit -m "feat: add the Patient counselling singleton section backend"
```

---

## Task 5: Pharmacist intervention and Monitoring follow-up (repeatable rows) backend

**Files:**
- Modify: `app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php` (restrict `activity_type`, add `client_operation_id`)
- Create: `app/Http/Requests/Student/UpdateCaseClinicalActivityRequest.php`
- Modify: `app/Http/Controllers/Student/CaseClinicalActivityController.php` (add `store`, `sync`, `destroy`)
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

    public function test_owning_student_can_create_an_intervention_row(): void
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

    public function test_a_single_field_edit_on_an_intervention_row_does_not_erase_others(): void
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
        $this->assertSame('accepted', $fresh->details['outcome']);
    }

    public function test_owning_student_can_delete_a_monitoring_row(): void
    {
        [, $student, $case] = $this->makeCase();
        $this->actingAs($student);
        $activity = $this->makeActivity($case, $student, ClinicalActivityType::Monitoring);

        $this->deleteJson("/student/cases/{$case->id}/clinical-activities/{$activity->id}")->assertNoContent();

        $this->assertCount(0, $case->fresh()->clinicalActivities);
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
            'client_operation_id' => ['required', 'uuid'],
            'activity_type' => ['required', Rule::in([ClinicalActivityType::Intervention->value, ClinicalActivityType::Monitoring->value])],
            'status' => ['nullable', 'string', 'max:30'],
            'details' => ['nullable', 'array'],
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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseClinicalActivityRequest extends FormRequest
{
    use HasSyncEnvelope;

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
            'details' => ['sometimes', 'nullable', 'array'],
        ];

        if ($this->route('activity')?->activity_type === ClinicalActivityType::Intervention) {
            return [
                ...$rules,
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
            'details.parameter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.result' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'details.observed_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'details.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Add `store`, `sync`, `destroy` to `CaseClinicalActivityController`**

Add imports:

```php
use App\Http\Requests\Student\StoreCaseClinicalActivityRequest;
use App\Http\Requests\Student\UpdateCaseClinicalActivityRequest;
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

        $result = $sync->sync(
            $activity,
            $request->user(),
            'clinical_activities',
            $envelope['client_operation_id'],
            $envelope['base_lock_version'],
            $request->sectionData(),
            $envelope['resolution'],
            $envelope['confirmed'],
        );

        return response()->json(['activity' => $this->payload($result['model'])], $result['httpStatus']);
    }

    public function destroy(ClinicalCase $case, CaseClinicalActivity $activity): Response
    {
        Gate::authorize('update', $activity);
        abort_unless($activity->clinical_case_id === $case->id, 404);
        abort_unless(in_array($activity->activity_type, [ClinicalActivityType::Intervention, ClinicalActivityType::Monitoring], true), 404);

        $activity->delete();

        return response()->noContent();
    }
```

- [ ] **Step 6: Add routes**

In `routes/web.php`, add **after** the ADR and Counselling routes (ordering matters — see Global Constraints):

```php
        Route::post('student/cases/{case}/clinical-activities', [CaseClinicalActivityController::class, 'store'])->name('student.cases.clinical-activities.store');
        Route::put('student/cases/{case}/clinical-activities/{activity}', [CaseClinicalActivityController::class, 'sync'])->name('student.cases.clinical-activities.sync');
        Route::delete('student/cases/{case}/clinical-activities/{activity}', [CaseClinicalActivityController::class, 'destroy'])->name('student.cases.clinical-activities.destroy');
```

- [ ] **Step 7: Regenerate Wayfinder files**

Run (PowerShell): `npm run build`

- [ ] **Step 8: Run the tests to verify they pass**

Run (PowerShell): `php artisan test --filter=RepeatableClinicalActivitySyncTest`
Expected: PASS (6 tests).

- [ ] **Step 9: Run the full suite**

Run (PowerShell): `php artisan test`
Expected: 205 previous + 6 new — 211 passed / 2 skipped.

- [ ] **Step 10: Static analysis and formatting**

Run (PowerShell): `vendor\bin\phpstan analyse`
Run (PowerShell): `vendor\bin\pint --test`
Expected: 0 errors; no diffs.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Student/StoreCaseClinicalActivityRequest.php app/Http/Requests/Student/UpdateCaseClinicalActivityRequest.php app/Http/Controllers/Student/CaseClinicalActivityController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/RepeatableClinicalActivitySyncTest.php
git commit -m "feat: add Pharmacist intervention and Monitoring follow-up repeatable-row backend"
```

---

## Task 6: Retire the standalone SOAP page; wire the SOAP section into the editor

**Files:**
- Delete: `resources/js/pages/student/SoapEditor.vue`
- Create: `resources/js/pages/student/case-editor/SoapSection.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue`
- Modify: `resources/js/pages/student/CaseShow.vue` (remove the "Edit SOAP" button; repoint "Start editing" to `/edit`)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass `soap` prop)
- Test: `tests/Feature/CaseEditorPageTest.php` (extend)

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: FAIL — `soap` prop missing.

- [ ] **Step 3: Extend `CaseEditorController`**

Add the import `use App\Models\SoapNote;`. Add a `soap` prop to the `Inertia::render(...)` array:

```php
            'soap' => $case->currentSoap === null ? null : [
                'subjective' => $case->currentSoap->subjective,
                'objective' => $case->currentSoap->objective,
                'assessment' => $case->currentSoap->assessment,
                'plan' => $case->currentSoap->plan,
                'drug_related_problem_status' => $case->currentSoap->drug_related_problem_status,
                'drug_related_problem_categories' => $case->currentSoap->drug_related_problem_categories,
                'lock_version' => $case->currentSoap->lock_version,
                'updated_at' => $case->currentSoap->updated_at->toIso8601String(),
            ],
```

Add `'currentSoap'` to any eager-loading if the controller doesn't already load it lazily — it doesn't need explicit eager-loading here since `$case->currentSoap` is accessed once.

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Write `SoapSection.vue`**

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
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Assessment</span>
            <textarea v-model="payload.assessment" rows="4" maxlength="5000" data-test="soap-assessment" class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" @input="edit" />
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

- [ ] **Step 6: Wire into `CaseEditor.vue`**

Add the import: `import SoapSection from './case-editor/SoapSection.vue';`

Extend the props type with `soap: (Record<string, unknown> & { lock_version: number; updated_at: string }) | null;`

Change the `soap` entry in `sections` to `available: true` and update `goTo()` — remove the special-case redirect (it's no longer needed once `soap` is a real in-editor section):

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
    drug_related_problem_status: null, drug_related_problem_categories: null,
    lock_version: 0, updated_at: new Date().toISOString(),
};
```

Add the branch after `MedicationChartSection`:

```vue
            <SoapSection v-else-if="activeSection.id === 'soap'" :case-id="clinicalCase.id" :user-id="userId" :initial="(soap ?? emptySoap) as any" />
```

- [ ] **Step 7: Update `CaseShow.vue`**

Remove the "Edit SOAP" `<Button>` entirely (both instances — the header action button and the "Start editing" link under "No SOAP note yet."), since "Continue documentation" (added in Slice 2A Task 6) now covers the same destination through the unified editor. Replace the "Start editing" `<Button variant="link">` with the same `router.get(`/student/cases/${clinicalCase.id}/edit`)` call `Continue documentation` already uses.

- [ ] **Step 8: Delete the standalone SOAP page**

Delete `resources/js/pages/student/SoapEditor.vue`.

- [ ] **Step 9: Type-check**

Run (PowerShell): `npm run types:check`
Expected: no errors. (Deleting `SoapEditor.vue` must not leave a dangling import anywhere — `grep`/search the codebase for `SoapEditor` before this step to confirm nothing else references it besides the route, already removed in Task 1.)

- [ ] **Step 10: Run the full backend suite**

Run (PowerShell): `php artisan test`
Expected: 211 previous + 1 (extended test) — 211 passed / 2 skipped (no new test count change since Step 1 extended an existing file).

- [ ] **Step 11: Commit**

```bash
git add resources/js/pages/student/case-editor/SoapSection.vue resources/js/pages/student/CaseEditor.vue resources/js/pages/student/CaseShow.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git rm resources/js/pages/student/SoapEditor.vue
git commit -m "feat: wire SOAP into the unified case editor and retire the standalone SOAP page"
```

---

## Task 7: Frontend — Conditional Clinical Activities section

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

Add the import `use App\Enums\ClinicalActivityType;` and `use App\Models\CaseClinicalActivity;` (if not already present from Task 5's usage). Add these props to `Inertia::render(...)`:

```php
            'adr' => $this->singletonActivityPayload($case, ClinicalActivityType::Adr),
            'counselling' => $this->singletonActivityPayload($case, ClinicalActivityType::Counselling),
            'interventions' => $this->repeatableActivityPayload($case, ClinicalActivityType::Intervention),
            'monitoringFollowUps' => $this->repeatableActivityPayload($case, ClinicalActivityType::Monitoring),
```

Add these two private methods to the controller:

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

Add `$case->load('clinicalActivities');` near the top of `show()` (alongside where `clinicalProfile`/other relations are accessed) so the four payload helpers above don't issue four separate queries.

- [ ] **Step 4: Run the test to verify it passes**

Run (PowerShell): `php artisan test --filter=CaseEditorPageTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Write `ActivityRow.vue`**

```vue
<script setup lang="ts">
import { Trash2, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';

type ActivityPayload = SyncedSection & {
    id: string;
    activity_type: 'intervention' | 'monitoring';
    status: string | null;
    details: Record<string, unknown> | null;
};

const props = defineProps<{ caseId: string; userId: number; initial: ActivityPayload }>();
const emit = defineEmits<{ removed: [id: string] }>();

const { payload, state, edit, online } = useSectionSync<ActivityPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'clinical_activities',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/${props.initial.id}`,
    initialPayload: props.initial,
});

const statusIcon = computed(() => ({
    saving: RefreshCw, server: Check, device: CloudOff, unsynced: FileClock, failed: AlertTriangle, conflict: AlertTriangle,
})[state.value]);

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
            <textarea :value="(payload.details?.problem as string) ?? ''" rows="2" maxlength="1000" placeholder="Problem" class="mb-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('problem', ($event.target as HTMLTextAreaElement).value)" />
            <textarea :value="(payload.details?.recommendation as string) ?? ''" rows="2" maxlength="1000" placeholder="Recommendation" class="mb-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('recommendation', ($event.target as HTMLTextAreaElement).value)" />
            <select :value="(payload.details?.outcome as string) ?? ''" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @change="updateDetail('outcome', ($event.target as HTMLSelectElement).value)">
                <option value="">Outcome…</option>
                <option value="accepted">Accepted</option>
                <option value="partially_accepted">Partially accepted</option>
                <option value="not_accepted">Not accepted</option>
                <option value="pending">Pending</option>
                <option value="not_communicated">Not communicated</option>
            </select>
        </template>

        <template v-else>
            <input :value="(payload.details?.parameter as string) ?? ''" type="text" maxlength="255" placeholder="Parameter" class="mb-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('parameter', ($event.target as HTMLInputElement).value)" />
            <textarea :value="(payload.details?.result as string) ?? ''" rows="2" maxlength="1000" placeholder="Result" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateDetail('result', ($event.target as HTMLTextAreaElement).value)" />
        </template>
    </div>
</template>
```

- [ ] **Step 6: Write `ConditionalClinicalActivitiesSection.vue`**

```vue
<script setup lang="ts">
import { Plus, RefreshCw, Check, CloudOff, FileClock, AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useSectionSync, type SyncedSection } from '@/composables/useSectionSync';
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

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function addRow(activityType: 'intervention' | 'monitoring') {
    const response = await fetch(`/student/cases/${props.caseId}/clinical-activities`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ client_operation_id: crypto.randomUUID(), activity_type: activityType }),
    });
    if (response.ok) {
        const body = (await response.json()) as { activity: RowPayload };
        if (activityType === 'intervention') interventions.value = [body.activity, ...interventions.value];
        else monitoringFollowUps.value = [body.activity, ...monitoringFollowUps.value];
    }
}

function removeIntervention(id: string) {
    interventions.value = interventions.value.filter((r) => r.id !== id);
}
function removeMonitoring(id: string) {
    monitoringFollowUps.value = monitoringFollowUps.value.filter((r) => r.id !== id);
}

function updateAdrDetail(key: string, value: unknown) {
    adrSync.payload.value.details = { ...(adrSync.payload.value.details ?? {}), [key]: value };
    adrSync.edit();
}
function updateCounsellingDetail(key: string, value: unknown) {
    counsellingSync.payload.value.details = { ...(counsellingSync.payload.value.details ?? {}), [key]: value };
    counsellingSync.edit();
}
</script>

<template>
    <section aria-labelledby="clinical-activities-heading" class="space-y-8">
        <h2 id="clinical-activities-heading" class="font-display text-lg text-[#0b2942] dark:text-white">Conditional Clinical Activities</h2>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Pharmacist intervention</h3>
            <div class="space-y-3">
                <ActivityRow v-for="row in interventions" :key="row.id" :case-id="caseId" :user-id="userId" :initial="row" @removed="removeIntervention" />
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addRow('intervention')"><Plus class="size-4" /> Add intervention</button>
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Suspected ADR</h3>
            <fieldset class="mb-3">
                <div class="flex flex-wrap gap-3 text-sm">
                    <label v-for="option in ['yes', 'no', 'unable_to_assess']" :key="option" class="flex items-center gap-1.5">
                        <input v-model="adrSync.payload.value.status" type="radio" :value="option" :data-test="`adr-status-${option}`" @change="adrSync.edit" />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div v-if="adrSync.payload.value.status === 'yes'" class="space-y-2">
                <input :value="(adrSync.payload.value.details?.event as string) ?? ''" type="text" maxlength="1000" placeholder="Event / reaction" data-test="adr-event" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('event', ($event.target as HTMLInputElement).value)" />
                <DeidentificationNotice :text="(adrSync.payload.value.details?.event as string) ?? null" />
                <input :value="(adrSync.payload.value.details?.suspected_medicine as string) ?? ''" type="text" maxlength="255" placeholder="Suspected medicine" data-test="adr-suspected-medicine" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateAdrDetail('suspected_medicine', ($event.target as HTMLInputElement).value)" />
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Patient counselling</h3>
            <fieldset class="mb-3">
                <div class="flex flex-wrap gap-3 text-sm">
                    <label v-for="option in ['performed', 'planned', 'not_indicated', 'unable_to_perform']" :key="option" class="flex items-center gap-1.5">
                        <input v-model="counsellingSync.payload.value.status" type="radio" :value="option" :data-test="`counselling-status-${option}`" @change="counsellingSync.edit" />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div v-if="counsellingSync.payload.value.status === 'performed' || counsellingSync.payload.value.status === 'planned'" class="space-y-2">
                <textarea :value="(counsellingSync.payload.value.details?.topics as string) ?? ''" rows="3" maxlength="2000" placeholder="Topics covered" data-test="counselling-topics" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" @input="updateCounsellingDetail('topics', ($event.target as HTMLTextAreaElement).value)" />
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200">Monitoring follow-up</h3>
            <div class="space-y-3">
                <ActivityRow v-for="row in monitoringFollowUps" :key="row.id" :case-id="caseId" :user-id="userId" :initial="row" @removed="removeMonitoring" />
                <button type="button" class="flex items-center gap-1 text-sm font-bold text-[#0b2942]" @click="addRow('monitoring')"><Plus class="size-4" /> Add follow-up result</button>
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
Expected: 211 previous + 1 (extended test) — 211 passed / 2 skipped.

- [ ] **Step 10: Commit**

```bash
git add resources/js/pages/student/case-editor/ActivityRow.vue resources/js/pages/student/case-editor/ConditionalClinicalActivitiesSection.vue resources/js/pages/student/CaseEditor.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git commit -m "feat: complete the six-section mobile case editor with Conditional Clinical Activities"
```

---

## Task 8: Authorization and institution-isolation tests

**Files:**
- Create: `tests/Feature/ClinicalActivityAuthorizationTest.php`

**Interfaces:**
- Consumes: `SoapController` (Task 1), `CaseClinicalActivityController` (Tasks 3–5). Closes cross-institution/post-submission/faculty gaps for the final set of endpoints, mirroring Slice 2A Task 7 and Slice 2B Task 6.

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
Expected: 211 previous + 3 new — 214 passed / 2 skipped.

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

**Files:** None (verification only).

**Interfaces:** None.

Same rationale and tooling as Slices 2A Task 8 and 2B Task 7. This is the comprehensive pass across the whole editor now that all six sections exist together.

- [ ] **Step 1: Start the app**

Run (PowerShell, background): `php artisan serve` and `npm run dev`.

- [ ] **Step 2: Phone viewport (390×844) — full create-to-portfolio-adjacent walkthrough**

As a student with an active rotation assignment:
1. Create a new case. Confirm it lands on the case show page, and "Continue documentation" opens the six-section editor.
2. Walk through all six sections in order via Next: Case Profile → History & Diagnosis → Vitals & Investigations → Medication Chart → SOAP → Conditional Clinical Activities. Confirm the nav pill for each becomes active (`aria-current="step"`) as you arrive, and Previous/Next disable correctly at both ends.
3. In SOAP, set "Drug-related problem status" to "Identified" and confirm the category chips appear; toggle a few and confirm they persist after a page refresh (re-open `/edit` and check the SOAP section reflects the saved categories).
4. In Conditional Clinical Activities: answer Suspected ADR "Yes" and confirm the event/suspected-medicine fields appear and a 10-digit number typed into "Event / reaction" shows the de-identification warning; answer Patient counselling "Performed" and confirm the topics field appears; add one Pharmacist intervention row and one Monitoring follow-up row and confirm both appear in their own lists.
5. Confirm the old `/student/cases/{id}/soap` URL no longer renders a usable page (route removed in Task 1/6) and that no visible link in the app points there anymore (check `CaseShow.vue`).

- [ ] **Step 3: Offline recovery and conflict — SOAP and one singleton activity**

Repeat the offline-edit / reconnect / two-tab-conflict script from Slice 2A Task 8 Step 3 and Slice 2B Task 7 Step 2, this time against a SOAP field and against the ADR singleton's `status` field. Confirm identical behavior (this proves the engine generalizes correctly to the last two consumers, singleton and SOAP alike).

- [ ] **Step 4: Tablet (820×1180) and Desktop (1280×900, Chrome/Edge)**

Repeat Step 2's walkthrough at both viewports. Pay particular attention to the Conditional Clinical Activities section's four subsections stacking sensibly rather than feeling cramped at phone width or oddly sparse at desktop width — if visual polish issues are found here, record them as a known-limitations note for the pull request rather than scope-creeping a fix into this verification task.

- [ ] **Step 5: Record the result and update `PROJECT_STATE.md`**

This is the point where Slice 2 as a whole (2A + 2B + 2C) is complete. Once this slice's PR is reviewed and merged, update `PROJECT_STATE.md`'s "Active work" section for `DIRECT-DOCUMENTATION-IMPL-01`, following the exact structure Slice 1's acceptance used (`ba58646` merge record): record the merge commit, the final test count from Task 8, the verification evidence from this task, any deferred minors found along the way (e.g. visual polish items from Step 4), and update "Exact next action" to point at Slice 3 (submission and completeness). Do not perform this `PROJECT_STATE.md` update as part of any earlier task — only once the whole gate is genuinely done, per the file's own maintenance rule (§9).

---

## Self-Review Notes

- **Spec coverage:** Requirement 2 (mobile section editor) — completed here; all six sections now live in one `CaseEditor.vue`. Requirement 3 (repeatable rows) — Intervention and Monitoring follow-up added (Task 5), completing the full set across 2B+2C. Requirement 5 (sync engine) — proven against its two remaining consumer shapes (a revisioned singleton with lazy creation for SOAP, and a second lazy-singleton pattern for ADR/Counselling) without any new locking mechanism. Requirement 6 (conditional allergy and ADR fields) — ADR's Yes/No/Unable-to-assess gate with required-only-on-Yes details (Task 3); counselling's parallel structure (Task 4). Requirement 7 (de-identification warnings) — extended to SOAP Subjective and the ADR event field. Requirement 8 (authorization tests) — Task 8. Requirement 9 (device verification) — Task 9, covering the full six-section flow end to end, closing out Slice 2.
- **Placeholder scan:** No task defers real logic. The visual-polish deferrals in Task 9 Step 4 are explicitly named as recorded limitations, not silently skipped work.
- **Type consistency:** `CaseClinicalActivityController::payload()`/`activityPayload()` return the same field set (`id`, `activity_type`, `status`, `details`, `lock_version`, `updated_at`) whether the row came from `store()`, `sync()`, `syncAdr()`, or `syncCounselling()`, so `ActivityRow.vue` and `ConditionalClinicalActivitiesSection.vue` consume one consistent shape regardless of which endpoint produced it. `SoapController::payload()` matches `UpdateSoapNoteRequest`'s validated field set exactly, mirroring the discipline established for every other section since Slice 2A.
