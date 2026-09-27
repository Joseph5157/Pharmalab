# DIRECT-DOCUMENTATION-IMPL-01 Slice 3: Submission and Completeness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Revision note (this version):** Reviewed and corrected before approval. Changes from the first draft: Laravel version corrected; a field-by-field completeness matrix added (Task 1's per-row medication/investigation checks were previously too shallow — "at least one row exists" is not "the row is complete"); Suspected ADR is now mandatory-to-answer, not conditionally gated only when "Yes"; the SOAP monitoring-plan justification requirement is now stated explicitly as a decision, not left implicit in code; the Pharmacist Intervention trigger is now explicitly documented as structured-field-only (no free-text inference); the submit/idempotency contract is corrected to resolve a real contradiction (see "Submission status contract" below); and the `PROJECT_STATE.md` update is removed from this plan entirely — it happens once, separately, after this slice's PR is reviewed and merged, never while implementing on the feature branch.

**Goal:** Turn the already-complete six-section mobile case editor (Slices 2A/2B/2C) into a real submittable workflow: a read-only submission review screen that shows exactly what is missing, a de-identification attestation, server-authoritative completeness gating at submit time (never while drafting), and an immutable, form-version-tagged snapshot of the whole case on every submission.

**Architecture:** No new database tables or columns are required — Slice 1 already added `deidentification_attested_at`/`deidentification_attested_by` to `clinical_cases` and they have sat unused ever since; this slice wires them up. The existing but currently-unused `App\Services\CaseCompletenessService` (referenced only by its own unit test today — grep confirms no controller or action calls it) becomes the single source of truth for "is this case ready to submit," used by both the new read-only review screen (to explain what's missing) and the existing `App\Actions\SubmitCase` action (to actually block an incomplete submission — today `SubmitCase` performs no completeness check at all beyond "a SOAP note exists," which is the walking-skeleton behavior this slice replaces). A new `SubmissionReviewController` reuses a small extracted `ClinicalCasePresenter` service (pulled out of `CaseEditorController`, which currently inlines ~150 lines of per-section payload mapping) so the review screen and the editor render the exact same section data without duplicating it.

**Submission status contract (corrected):** `ClinicalCasePolicy::submit()` is broadened to allow **Draft, Returned, and Submitted** (Task 2) — a duplicated or retried POST to `/student/cases/{case}/submit` while the case is already Submitted reaches `SubmitCase`, which returns the existing version idempotently rather than erroring or being denied. A new, narrower `ClinicalCasePolicy::reviewForSubmission()` gates the read-only review screen and allows **only Draft/Returned** — once a case is Submitted there is nothing left to review-before-submitting, so `GET /submission-review` is correctly denied at that point even though `POST /submit` is not. **UnderReview and Approved are denied by both checks**: submission and review are a one-way door once a faculty member has started or finished acting on the case. This single, stated rule replaces the first draft's contradiction (which simultaneously claimed a retried submit is idempotent and that POST on Submitted must be denied).

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia.js, Vue 3 + TypeScript, PostgreSQL, PHPUnit, Pint, PHPStan (Larastan).

**Spec:** [`docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md`](../../research/PHARMD_CASE_FORM_CANDIDATE_01.md) §4 (field catalogue) and §5 (validation policy — this section's exact "Submission checks" bullet list is what `CaseCompletenessService::submissionErrors()` implements), and [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) §3 "Slice 3 — Submission and completeness."

## Global Constraints

- Incomplete drafts may always be saved; every completeness/validation check added by this slice runs **only** at submission time, never on the per-section autosave endpoints already shipped in Slices 2A/2B/2C. Do not add `required` rules to any `Update*Request` class.
- The server remains authoritative: the review screen's own missing-section list must come from the same `CaseCompletenessService` the submit action enforces, not a separate client-side recomputation of the rules.
- Submitted and approved snapshots are immutable and must be tied to the case's fixed `form_version` (Slice 1's `CaseFormVersion` enum) — the snapshot must record `form_version`, which the current `SubmitCase` snapshot omits entirely.
- Unknown request fields are rejected by the server on every new endpoint this slice adds, using the existing `App\Http\Requests\Concerns\RejectsUnknownFields` trait every other request in this codebase already uses.
- Completeness rules are implemented **exactly** as listed in the field-by-field matrix below — no rule may be added or loosened without updating that matrix first, since the matrix is what makes a "full catalogue" claim auditable.
- Every completeness rule must be satisfiable through the app the student actually has access to. A rule that gates on data no UI can ever produce is a bug, not a stricter check — see "Discovered gaps and scope reconciliations" below for one real case of this found while writing this plan.
- No curriculum tables, dynamic template builder, B.Pharm engine, attachments, marks/rubrics, multi-reviewer approval, or AI features — unchanged from every prior slice's exclusions.
- Institution scoping and owner-only access are preserved on every new route.

## Field-by-field completeness matrix

Legend: **M** mandatory before submission, **C** conditionally mandatory, **O** optional/never gated. "Section" is the `CaseEditor.vue`/`SubmissionReview.vue` id the error links to.

### Case context (spec §4.1) — section `case_profile`

| Field | Level | Submission rule |
| --- | --- | --- |
| Care setting | M | `care_setting` filled |
| Case documentation date | M | `encounter_date` not null |
| Information source | M | `information_source` filled |
| Age | M | `age_value` not null (age/unit pairing already enforced at input time by `UpdateClinicalCaseContextRequest`) |
| Sex | M | `sex` filled |
| Weight, Height, Pregnancy/lactation status, Hospital day at first review | C/O | **Not gated.** "Relevant to dosing/paediatrics/renal assessment" (weight) and "shown only when clinically relevant" (pregnancy/lactation) are clinical judgment calls with no reliable server-side trigger; gating them would force an answer the catalogue itself says is sometimes inapplicable. |
| De-identification attestation | M | Enforced separately, not as a section-completion field — `deidentification_attested` must be `true` in the submit request itself (Task 2). |

### History, diagnosis, allergies (spec §4.2) — section `history_diagnosis`

| Field | Level | Submission rule |
| --- | --- | --- |
| Chief complaints | M | `chief_complaints` non-empty array |
| History of present illness | M | `history_present_illness` filled |
| Diagnosis/active problem | M | `diagnoses` non-empty array |
| Past medical history | M | `past_medical_history_none === true` OR `past_medical_history` filled |
| Allergy status | M | `allergy_status` filled |
| Allergy substance | C | filled when `allergy_status === 'known_allergy'` |
| Medication history | M (catalogue) | **Satisfied by the Medication Chart section, not independently gated.** See "Discovered gaps" below. |
| Adherence status, Family history, Substance history, Examination findings, Past surgical history | C/O | Not gated — conditionally relevant with no reliable server-side trigger, or explicitly optional in the catalogue. |

### Vitals (spec §4.3) — section `vitals_investigations`

| Field | Level | Submission rule |
| --- | --- | --- |
| At least one relevant observation, or a reason | M | `vitals_status === 'recorded'` AND at least one `CaseVital` row exists, OR `vitals_status === 'unavailable'` |

### Investigations (spec §4.4) — section `vitals_investigations`

| Field | Level | Submission rule |
| --- | --- | --- |
| At least one relevant result, or a reason | M | `investigations_status === 'recorded'` AND at least one row exists AND **every row is row-complete** (below), OR `investigations_status === 'unavailable'` |
| Per row: test name, result type, result value | M | all three filled |
| Per row: unit, or "Unit not stated" | M | `unit` filled OR `unit_not_stated === true` |
| Per row: reference range, or "Reference range not provided" | M | `reference_range` filled OR `reference_range_not_provided === true` |
| Per row: hospital-reported flag, observed date/time, interpretation | O | Not gated — catalogue marks interpretation optional and does not list the flag/date as submission blockers. |

This closes the first draft's gap: the original service only checked `investigations()->exists()`, so a row with no unit and no "not stated" flag still counted as complete.

### Medication chart (spec §4.5) — section `medication_chart`

| Field | Level | Submission rule |
| --- | --- | --- |
| At least one medicine row, or explicit none | M | `medication_chart_status === 'documented'` AND at least one non-history row exists AND **every row is row-complete** (below), OR `medication_chart_status === 'none_documented'` |
| Per row: generic name | M | filled |
| Per row: indication | M | `indication` filled OR `indication_unclear === true` |
| Per row: dose amount and unit | M | both filled |
| Per row: route | M | filled |
| Per row: frequency | M | filled |
| Per row: status | M | always present (`case_medications.status` is `NOT NULL` with a default) |
| Per row: stop date when Stopped/Completed, PRN indication when PRN | C | already enforced at input time (`required_if` in `Store/UpdateCaseMedicationRequest`); not re-checked at submission since it cannot be bypassed |
| Per row: brand name, dosage form, start date, notes | O | Not gated |

This closes two gaps at once: (1) the original service only checked `medications()->exists()`, so a row with only a generic name (no indication/dose/route/frequency) counted as complete; (2) the original service filtered on `medication_context = 'chart'`, which — see below — matches **zero** real rows created through the shipped UI.

### SOAP (spec §4.6) — section `soap`

| Field | Level | Submission rule |
| --- | --- | --- |
| Subjective, Objective, Assessment, Plan | M | all four filled |
| Drug-related-problem status | M | `drug_related_problem_status` filled |
| DRP categories | C | non-empty when `drug_related_problem_status === 'identified'` |
| Monitoring plan, or justified not applicable | M | `monitoring_plan` filled, **OR** (`monitoring_plan_not_applicable === true` **AND** `monitoring_plan_not_applicable_reason` filled). **Explicit decision (corrected per review): the boolean alone never satisfies this rule.** The catalogue says "or justified not applicable" — "justified" is a written reason, not a checkbox. |

### Conditional clinical activities (spec §4.7) — section `clinical_activities`

| Field | Level | Submission rule |
| --- | --- | --- |
| Suspected ADR answered | **M (corrected per review)** | An `adr` activity row exists with `status` filled (`yes`/`no`/`unable_to_assess`). The first draft only blocked when `status === 'yes'` and treated null/unanswered as passing — that was wrong; the catalogue states every case answers this question. |
| ADR details | C | `details.event` and `details.suspected_medicine` filled when `status === 'yes'` |
| Patient counselling answered | M | A `counselling` activity row exists with `status` filled |
| Pharmacist intervention | C | At least one `intervention` row with `details.problem` and `details.recommendation` filled, **triggered only when `SoapNote::drug_related_problem_status === 'identified'`** — see "Discovered gaps" below for why the catalogue's alternate trigger ("a therapy-change recommendation is documented") is not implemented. |
| Monitoring follow-up | O | Never gated — the catalogue says it "is created only after the student follows the case," i.e. it may legitimately not exist. |

## Discovered gaps and scope reconciliations

Two real issues surfaced while building the matrix above, in the existing (already-merged) codebase, not in this slice's own new code. Both are recorded here rather than silently fixed or silently ignored, per the instruction not to pretend a gap doesn't exist.

1. **"Medication history" (§4.2) and "Medication chart" (§4.5) collapse to one implemented section, and the original service's row filter matched nothing.** The catalogue lists these as two separate mandatory items. The shipped Slice 2A–2C UI implements only one section, "Medication Chart" (`resources/js/pages/student/case-editor/MedicationChartSection.vue`), and that component's `emptyPayload()` sends `medication_context: null` on every row it creates — never `'chart'` or `'history'` — even though `Store/UpdateCaseMedicationRequest` accept both values (added in the Slice 2B-era migration `2026_09_29_000004_make_case_medication_context_nullable.php`, whose own docblock explains the column had to be made nullable because the real frontend sends an explicit `null`). Two consequences: (a) the pre-Slice-3 `CaseCompletenessService` filtered on `medication_context = 'chart'`, which never matches a single row created through the real app — a live bug this slice's rewrite must not reproduce (fixed by excluding only rows explicitly tagged `'history'`, not by requiring `'chart'`); (b) there is no UI path that ever creates a `'history'`-tagged row, so no completeness rule can honestly gate on "medication history" as its own section without making every case permanently unsubmittable. This slice treats the shipped Medication Chart section as satisfying both catalogue items. Building a genuine, separate Medication History UI is out of Slice 3's scope (it is new-form-section work — Slice 2's kind of work — not submission/completeness work) and is not implemented here.
2. **The Pharmacist Intervention trigger is structured-only.** The catalogue: "Required when a drug-related problem **or** therapy-change recommendation is documented." The first half is the structured `drug_related_problem_status` field, which the server can check reliably. The second half exists only as free prose inside `SoapNote::plan` (an unstructured `max:5000` text field). This service triggers the intervention gate only on the structured flag and makes no attempt to keyword-match or otherwise infer a "recommendation" from free text — a server-side heuristic over clinical prose would produce both false blocks (a plan mentioning "consider switching" without an intervention row) and false negatives (a real recommendation phrased in words the heuristic doesn't recognize), which is worse than not gating it at all. This is a permanent scope limit for server-side validation, not a temporary gap: a genuine recommendation without an identified DRP relies on the student's judgment and the faculty reviewer (who sees the full SOAP text), not an automated gate.

## Review Focus

- A medication row missing indication, dose, route, or frequency — or an investigation row missing both a unit and "Unit not stated" — must block submission even though the section otherwise has rows recorded. "At least one row exists" is not "the row is complete," and the first draft of this plan only checked the former. (Task 1)
- Suspected ADR left unanswered must block submission on its own, independent of what the student answers for counselling or DRP status — the catalogue requires every case to answer Yes/No/Unable-to-assess, not only cases where the answer happens to be Yes. (Task 1)
- A repeated POST to `/submit` after the case is already Submitted must succeed idempotently (return the existing version) — but the identical request once the case is UnderReview or Approved must be denied. These are two different statuses with two different, deliberately different contracts, not one uniform "already submitted → deny" rule. (Task 2, Task 5)
- Submitting with `deidentification_attested` omitted or `false` must block with a clear, specific error — never silently default to "attested." (Task 2)
- An unknown/extra field injected into the submit request body (for example an attempt to set `status` or `approved_at` directly) must be rejected server-side with a 422, not silently ignored or applied. (Task 2)

---

## Task 1: Rebuild `CaseCompletenessService` against the field-by-field matrix

**Files:**
- Modify: `app/Services/CaseCompletenessService.php`
- Test: `tests/Unit/Services/CaseCompletenessServiceTest.php` (full rewrite — the section-key shape changes, and row-level/ADR rules are new)

**Interfaces:**
- Consumes: `App\Models\ClinicalCase` (existing relations: `clinicalProfile`, `vitals()`, `investigations()`, `medications()`, `currentSoap`, `clinicalActivities`) and `App\Enums\ClinicalActivityType`.
- Produces (used by Task 2's `SubmitCase` and Task 3's `SubmissionReviewController`):
  - `sectionCompletion(ClinicalCase $case): array<string, bool>` with exactly these six keys, matching `CaseEditor.vue`'s existing `SectionId` values: `case_profile`, `history_diagnosis`, `vitals_investigations`, `medication_chart`, `soap`, `clinical_activities`.
  - `missingSections(ClinicalCase $case): list<string>`.
  - `isReadyForSubmission(ClinicalCase $case): bool`.
  - `submissionErrors(ClinicalCase $case): list<array{section: string, message: string}>` — one entry per failing rule from the matrix above. `isReadyForSubmission()` is defined as `submissionErrors($case) === []`.

The current service (`app/Services/CaseCompletenessService.php:1`) is dead code today — nothing in `app/` calls it, confirmed by grep. This task is the first thing that makes it load-bearing.

- [ ] **Step 1: Write the failing unit tests**

Replace the whole file with:

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
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
        [, , $case] = $this->makeCase();

        $service = new CaseCompletenessService;

        $this->assertSame([
            'case_profile' => false,
            'history_diagnosis' => false,
            'vitals_investigations' => false,
            'medication_chart' => false,
            'soap' => false,
            'clinical_activities' => false,
        ], $service->sectionCompletion($case->fresh()));
        $this->assertFalse($service->isReadyForSubmission($case->fresh()));
        $this->assertNotSame([], $service->submissionErrors($case->fresh()));
    }

    public function test_a_fully_documented_case_has_every_section_complete_and_no_submission_errors(): void
    {
        [, , $case] = $this->completeCase();

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertSame([
            'case_profile' => true,
            'history_diagnosis' => true,
            'vitals_investigations' => true,
            'medication_chart' => true,
            'soap' => true,
            'clinical_activities' => true,
        ], $service->sectionCompletion($fresh));
        $this->assertTrue($service->isReadyForSubmission($fresh));
        $this->assertSame([], $service->missingSections($fresh));
        $this->assertSame([], $service->submissionErrors($fresh));
    }

    public function test_missing_diagnosis_and_unanswered_allergy_status_produce_distinct_history_diagnosis_errors(): void
    {
        [$institution, , $case] = $this->completeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->update(['diagnoses' => null, 'allergy_status' => null]);

        $service = new CaseCompletenessService;
        $messages = collect($service->submissionErrors($case->fresh()))->pluck('message')->all();

        $this->assertContains('Enter at least one diagnosis or active problem.', $messages);
        $this->assertContains('Allergy status has not been answered.', $messages);
    }

    public function test_known_allergy_without_a_substance_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalProfile::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->update(['allergy_status' => 'known_allergy', 'allergy_substance' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['history_diagnosis']);
    }

    public function test_vitals_and_investigations_marked_unavailable_with_a_reason_count_as_complete(): void
    {
        [, , $case] = $this->completeCase();

        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        $case->update([
            'vitals_status' => 'unavailable',
            'vitals_unavailable_reason' => 'Ward chart unavailable at time of review.',
            'investigations_status' => 'unavailable',
            'investigations_unavailable_reason' => 'No investigations ordered.',
        ]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['vitals_investigations']);
    }

    public function test_an_investigation_row_missing_both_unit_and_unit_not_stated_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['unit' => null, 'unit_not_stated' => false]);

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['vitals_investigations']);
        $this->assertTrue(collect($service->submissionErrors($fresh))->contains(fn (array $e): bool => str_contains($e['message'], 'investigation')));
    }

    public function test_an_investigation_row_with_unit_not_stated_true_and_no_unit_is_complete(): void
    {
        [, , $case] = $this->completeCase();

        CaseInvestigation::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['unit' => null, 'unit_not_stated' => true]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['vitals_investigations']);
    }

    public function test_a_medication_row_missing_indication_dose_route_or_frequency_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        CaseMedication::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['indication' => null, 'indication_unclear' => false, 'route' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_medication_context_history_rows_do_not_count_toward_the_chart_and_are_not_independently_required(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        // The shipped UI never creates a 'history' row (see this plan's
        // "Discovered gaps" section) — this proves the service does not
        // silently start requiring one just because it exists in the schema.
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => 'history',
            'generic_name' => 'Old medicine, incomplete row',
            'status' => 'active',
            'recorded_by' => $student->id,
        ]);

        $service = new CaseCompletenessService;

        $this->assertTrue($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_medication_chart_status_recorded_but_no_chart_rows_still_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseMedication::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['medication_chart']);
    }

    public function test_identified_drug_related_problem_without_categories_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['soap']);
    }

    public function test_monitoring_plan_not_applicable_without_a_written_reason_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['monitoring_plan' => null, 'monitoring_plan_not_applicable' => true, 'monitoring_plan_not_applicable_reason' => null]);

        $service = new CaseCompletenessService;

        $this->assertFalse($service->sectionCompletion($case->fresh())['soap']);
    }

    public function test_identified_drug_related_problem_without_a_matching_intervention_row_blocks_submission(): void
    {
        [$institution, $student, $case] = $this->completeCase();

        SoapNote::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)
            ->update(['drug_related_problem_status' => 'identified', 'drug_related_problem_categories' => ['dose_too_low']]);

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['clinical_activities']);
        $this->assertContains(
            'A drug-related problem was identified — record a pharmacist intervention with problem and recommendation.',
            collect($service->submissionErrors($fresh))->pluck('message')->all(),
        );

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'intervention',
            'details' => ['problem' => 'Dose too low for renal function.', 'recommendation' => 'Increase to 500mg BD.'],
            'recorded_by' => $student->id,
        ]);

        $this->assertTrue($service->sectionCompletion($case->fresh())['clinical_activities']);
    }

    public function test_suspected_adr_must_be_answered_and_yes_requires_event_and_medicine(): void
    {
        [, , $case] = $this->completeCase();

        $adr = CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'adr')->first();

        $adr->update(['status' => null]);
        $this->assertFalse((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);

        $adr->update(['status' => 'yes', 'details' => null]);
        $this->assertFalse((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);

        $adr->update(['status' => 'no', 'details' => null]);
        $this->assertTrue((new CaseCompletenessService)->sectionCompletion($case->fresh())['clinical_activities']);
    }

    public function test_missing_adr_row_entirely_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'adr')->delete();

        $service = new CaseCompletenessService;
        $fresh = $case->fresh();

        $this->assertFalse($service->sectionCompletion($fresh)['clinical_activities']);
        $this->assertContains('Suspected ADR has not been answered.', collect($service->submissionErrors($fresh))->pluck('message')->all());
    }

    public function test_counselling_status_unanswered_blocks_submission(): void
    {
        [, , $case] = $this->completeCase();

        CaseClinicalActivity::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)->where('activity_type', 'counselling')
            ->update(['status' => null]);

        $service = new CaseCompletenessService;
        $this->assertFalse($service->sectionCompletion($case->fresh())['clinical_activities']);
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(): array
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
            'status' => CaseStatus::Draft,
        ]);

        return [$institution, $student, $case];
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function completeCase(): array
    {
        [$institution, $student, $case] = $this->makeCase();

        $case->update([
            'care_setting' => 'inpatient',
            'encounter_date' => '2026-10-15',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
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

        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
            'unit' => 'g/dL',
            'reference_range_not_provided' => true,
            'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'medication_context' => null,
            'generic_name' => 'Paracetamol',
            'indication' => 'Headache relief',
            'dose_amount' => '500',
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'TID',
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
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'adr',
            'status' => 'no',
            'recorded_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'activity_type' => 'counselling',
            'status' => 'not_indicated',
            'recorded_by' => $student->id,
        ]);

        return [$institution, $student, $case];
    }
}
```

Note the `medication_context => null` in the fixture's medicine row — this deliberately matches what the real `MedicationChartSection.vue` actually sends (confirmed by reading that component), not the `'chart'` value the pre-Slice-3 test fixture used, which never occurs in real data.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/CaseCompletenessServiceTest.php`
Expected: FAIL — the current service returns different section keys, has no row-level checks, no `submissionErrors()` method, and treats ADR as passing when unanswered.

- [ ] **Step 3: Rewrite the service**

Replace `app/Services/CaseCompletenessService.php` with:

```php
<?php

namespace App\Services;

use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\ClinicalCase;

class CaseCompletenessService
{
    /** @return array<string, bool> */
    public function sectionCompletion(ClinicalCase $case): array
    {
        return [
            'case_profile' => $this->caseProfileComplete($case),
            'history_diagnosis' => $this->historyDiagnosisComplete($case),
            'vitals_investigations' => $this->vitalsComplete($case) && $this->investigationsComplete($case),
            'medication_chart' => $this->medicationChartComplete($case),
            'soap' => $this->soapComplete($case),
            'clinical_activities' => $this->adrComplete($case) && $this->counsellingComplete($case) && $this->interventionComplete($case),
        ];
    }

    /** @return list<string> */
    public function missingSections(ClinicalCase $case): array
    {
        return array_keys(array_filter(
            $this->sectionCompletion($case),
            fn (bool $complete): bool => ! $complete,
        ));
    }

    public function isReadyForSubmission(ClinicalCase $case): bool
    {
        return $this->submissionErrors($case) === [];
    }

    /** @return list<array{section: string, message: string}> */
    public function submissionErrors(ClinicalCase $case): array
    {
        $errors = [];

        if (! $this->caseProfileComplete($case)) {
            $errors[] = ['section' => 'case_profile', 'message' => 'Complete the case profile: care setting, documentation date, information source, age and sex are all required.'];
        }

        $profile = $case->clinicalProfile;

        if ($profile === null || ! filled($profile->diagnoses)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter at least one diagnosis or active problem.'];
        }
        if ($profile === null || ! filled($profile->chief_complaints)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter at least one chief complaint.'];
        }
        if ($profile === null || ! filled($profile->history_present_illness)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'History of present illness is required.'];
        }
        if ($profile === null || (! $profile->past_medical_history_none && ! filled($profile->past_medical_history))) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter past medical history, or select "None known".'];
        }
        if ($profile === null || ! filled($profile->allergy_status)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Allergy status has not been answered.'];
        } elseif ($profile->allergy_status === 'known_allergy' && ! filled($profile->allergy_substance)) {
            $errors[] = ['section' => 'history_diagnosis', 'message' => 'Enter the allergy substance for the recorded known allergy.'];
        }

        if (! $this->vitalsComplete($case)) {
            $errors[] = ['section' => 'vitals_investigations', 'message' => 'Record at least one vital sign, or mark vitals unavailable with a reason.'];
        }
        if (! $this->investigationsComplete($case)) {
            $errors[] = ['section' => 'vitals_investigations', 'message' => 'Record at least one complete investigation result (test name, result, and a unit or "Unit not stated"), or mark investigations unavailable with a reason.'];
        }

        if (! $this->medicationChartComplete($case)) {
            $errors[] = ['section' => 'medication_chart', 'message' => 'Add at least one complete current medicine (generic name, indication or "Indication unclear", dose amount and unit, route and frequency), or select "No current medicines documented".'];
        }

        $soap = $case->currentSoap;
        if ($soap === null || ! filled($soap->subjective) || ! filled($soap->objective) || ! filled($soap->assessment) || ! filled($soap->plan)) {
            $errors[] = ['section' => 'soap', 'message' => 'All four SOAP areas (Subjective, Objective, Assessment, Plan) must be completed.'];
        }
        if ($soap !== null) {
            if (! filled($soap->drug_related_problem_status)) {
                $errors[] = ['section' => 'soap', 'message' => 'Select a drug-related-problem status in the Assessment.'];
            } elseif ($soap->drug_related_problem_status === 'identified' && ! filled($soap->drug_related_problem_categories)) {
                $errors[] = ['section' => 'soap', 'message' => 'Select at least one drug-related-problem category.'];
            }
            if ($soap->monitoring_plan_not_applicable) {
                if (! filled($soap->monitoring_plan_not_applicable_reason)) {
                    $errors[] = ['section' => 'soap', 'message' => 'Give a written justification for why a monitoring plan is not applicable.'];
                }
            } elseif (! filled($soap->monitoring_plan)) {
                $errors[] = ['section' => 'soap', 'message' => 'Enter a monitoring plan, or mark it not applicable with a written justification.'];
            }
        }

        $adr = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);
        if ($adr === null || ! filled($adr->status)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'Suspected ADR has not been answered.'];
        } elseif ($adr->status === 'yes') {
            $details = $adr->details ?? [];
            if (blank($details['event'] ?? null) || blank($details['suspected_medicine'] ?? null)) {
                $errors[] = ['section' => 'clinical_activities', 'message' => 'Suspected ADR is "Yes" but the ADR event and suspected medicine are not recorded.'];
            }
        }

        $counselling = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Counselling);
        if ($counselling === null || ! filled($counselling->status)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'Patient counselling status has not been answered.'];
        }

        if (! $this->interventionComplete($case)) {
            $errors[] = ['section' => 'clinical_activities', 'message' => 'A drug-related problem was identified — record a pharmacist intervention with problem and recommendation.'];
        }

        return $errors;
    }

    private function caseProfileComplete(ClinicalCase $case): bool
    {
        return filled($case->care_setting)
            && $case->encounter_date !== null
            && filled($case->information_source)
            && $case->age_value !== null
            && filled($case->sex);
    }

    private function historyDiagnosisComplete(ClinicalCase $case): bool
    {
        $profile = $case->clinicalProfile;

        return $profile !== null
            && filled($profile->chief_complaints)
            && filled($profile->history_present_illness)
            && filled($profile->diagnoses)
            && ($profile->past_medical_history_none || filled($profile->past_medical_history))
            && filled($profile->allergy_status)
            && ($profile->allergy_status !== 'known_allergy' || filled($profile->allergy_substance));
    }

    private function vitalsComplete(ClinicalCase $case): bool
    {
        return ($case->vitals_status === 'recorded' && $case->vitals()->exists())
            || $case->vitals_status === 'unavailable';
    }

    private function investigationsComplete(ClinicalCase $case): bool
    {
        if ($case->investigations_status === 'unavailable') {
            return true;
        }
        if ($case->investigations_status !== 'recorded') {
            return false;
        }
        $investigations = $case->investigations;

        return $investigations->isNotEmpty()
            && $investigations->every(fn (CaseInvestigation $i): bool => $this->investigationRowComplete($i));
    }

    private function investigationRowComplete(CaseInvestigation $investigation): bool
    {
        return filled($investigation->test_name)
            && filled($investigation->result_type)
            && filled($investigation->result_value)
            && (filled($investigation->unit) || $investigation->unit_not_stated)
            && (filled($investigation->reference_range) || $investigation->reference_range_not_provided);
    }

    private function medicationChartComplete(ClinicalCase $case): bool
    {
        if ($case->medication_chart_status === 'none_documented') {
            return true;
        }
        if ($case->medication_chart_status !== 'documented') {
            return false;
        }

        // medication_context accepts 'chart'/'history'/null at the request
        // layer, but the shipped Medication Chart section always sends null
        // (MedicationChartSection.vue's emptyPayload()) — filtering on the
        // literal string 'chart' (the pre-Slice-3 behavior) matched zero
        // real rows. See this plan's "Discovered gaps and scope
        // reconciliations" section. Every row not explicitly tagged
        // 'history' counts as a chart row.
        $medications = $case->medications->reject(fn (CaseMedication $m): bool => $m->medication_context === 'history');

        return $medications->isNotEmpty()
            && $medications->every(fn (CaseMedication $m): bool => $this->medicationRowComplete($m));
    }

    private function medicationRowComplete(CaseMedication $medication): bool
    {
        return filled($medication->generic_name)
            && (filled($medication->indication) || $medication->indication_unclear)
            && filled($medication->dose_amount)
            && filled($medication->dose_unit)
            && filled($medication->route)
            && filled($medication->frequency)
            && $medication->status !== null;
    }

    private function soapComplete(ClinicalCase $case): bool
    {
        $soap = $case->currentSoap;
        if ($soap === null || ! filled($soap->subjective) || ! filled($soap->objective) || ! filled($soap->assessment) || ! filled($soap->plan)) {
            return false;
        }
        if (! filled($soap->drug_related_problem_status)) {
            return false;
        }
        if ($soap->drug_related_problem_status === 'identified' && ! filled($soap->drug_related_problem_categories)) {
            return false;
        }

        return $soap->monitoring_plan_not_applicable
            ? filled($soap->monitoring_plan_not_applicable_reason)
            : filled($soap->monitoring_plan);
    }

    private function adrComplete(ClinicalCase $case): bool
    {
        $adr = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Adr);
        if ($adr === null || ! filled($adr->status)) {
            return false;
        }
        if ($adr->status !== 'yes') {
            return true;
        }
        $details = $adr->details ?? [];

        return filled($details['event'] ?? null) && filled($details['suspected_medicine'] ?? null);
    }

    private function counsellingComplete(ClinicalCase $case): bool
    {
        $counselling = $case->clinicalActivities->firstWhere('activity_type', ClinicalActivityType::Counselling);

        return $counselling !== null && filled($counselling->status);
    }

    private function interventionComplete(ClinicalCase $case): bool
    {
        $soap = $case->currentSoap;
        if ($soap === null || $soap->drug_related_problem_status !== 'identified') {
            return true;
        }

        return $case->clinicalActivities
            ->where('activity_type', ClinicalActivityType::Intervention)
            ->contains(fn (CaseClinicalActivity $activity): bool => filled($activity->details['problem'] ?? null) && filled($activity->details['recommendation'] ?? null));
    }
}
```

`$case->clinicalActivities`/`$case->medications`/`$case->investigations` are accessed as properties (not `()`) specifically so Eloquent lazy-loads and caches each collection once per `$case` instance — calling the same relation from multiple private methods on one instance (as `sectionCompletion()` and `submissionErrors()` both do) issues one query per relation, not one per call.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/CaseCompletenessServiceTest.php`
Expected: PASS, all 16 tests.

- [ ] **Step 5: Static analysis and formatting**

Run: `vendor/bin/phpstan analyse` — expect 0 errors.
Run: `vendor/bin/pint --dirty` — expect clean or auto-fixed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/CaseCompletenessService.php tests/Unit/Services/CaseCompletenessServiceTest.php
git commit -m "feat: rebuild CaseCompletenessService against the field-by-field completeness matrix"
```

---

## Task 2: Correct the submit/review authorization contract, gate `SubmitCase` on completeness and attestation, and build the full immutable snapshot

**Files:**
- Modify: `app/Policies/ClinicalCasePolicy.php` (broaden `submit()`, add `reviewForSubmission()`)
- Modify: `app/Actions/SubmitCase.php`
- Modify: `app/Http/Controllers/Student/SubmissionController.php`
- Create: `app/Http/Requests/Student/StoreSubmissionRequest.php`
- Create: `app/Exceptions/CaseNotReadyForSubmissionException.php`
- Modify: `tests/Feature/ClinicalCaseWorkflowTest.php` (its two `SubmitCase` tests build a SOAP-only case today — that is exactly the walking-skeleton shortcut this task removes; both are rewritten to build a fully-documented, matrix-complete case)
- Test: `tests/Feature/CaseSubmissionTest.php` (new)

**Interfaces:**
- Consumes: `App\Services\CaseCompletenessService::submissionErrors()` (Task 1).
- Produces:
  - `SubmitCase::__invoke(User $actor, ClinicalCase $case, bool $deidentificationAttested): CaseVersion` — the `bool` third parameter is **new**; every existing call site must be updated.
  - `CaseNotReadyForSubmissionException::errors(): list<array{section: string, message: string}>`.
  - `ClinicalCasePolicy::submit()` now allows `Draft`, `Returned`, **and `Submitted`** (was `Draft`/`Returned` only).
  - `ClinicalCasePolicy::reviewForSubmission()` — **new**, allows only `Draft`/`Returned`. Task 3's `SubmissionReviewController` uses this, not `submit`.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/CaseSubmissionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incomplete_case_cannot_be_submitted(): void
    {
        [, $student, , $case] = $this->completeCase();
        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->delete();
        $case->update(['vitals_status' => null]);

        $this->expectException(CaseNotReadyForSubmissionException::class);

        app(SubmitCase::class)->__invoke($student, $case->fresh(), true);
    }

    public function test_submission_without_deidentification_attestation_is_blocked(): void
    {
        [, $student, , $case] = $this->completeCase();

        try {
            app(SubmitCase::class)->__invoke($student, $case->fresh(), false);
            $this->fail('Expected CaseNotReadyForSubmissionException.');
        } catch (CaseNotReadyForSubmissionException $e) {
            $this->assertTrue(collect($e->errors())->contains(fn (array $error): bool => str_contains($error['message'], 'de-identification attestation')));
        }

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    public function test_a_complete_attested_case_submits_and_records_a_full_form_versioned_snapshot(): void
    {
        [, $student, , $case] = $this->completeCase();

        $version = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $fresh = $case->fresh();
        $this->assertNotNull($fresh->deidentification_attested_at);
        $this->assertSame($student->id, $fresh->deidentification_attested_by);

        $this->assertSame('pharmd-case-v1', $version->snapshot['form_version']);
        $this->assertSame('S', $version->snapshot['soap']['subjective']);
        $this->assertSame('Paracetamol', $version->snapshot['medication_chart']['entries'][0]['generic_name']);
        $this->assertSame('recorded', $version->snapshot['vitals']['status']);
    }

    public function test_resubmitting_an_already_submitted_case_at_the_action_level_is_idempotent(): void
    {
        [, $student, , $case] = $this->completeCase();

        $first = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);
        $second = app(SubmitCase::class)->__invoke($student, $case->fresh(), true);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->count());
    }

    public function test_a_repeated_http_submit_on_an_already_submitted_case_succeeds_idempotently(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $first = $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true]);
        $first->assertRedirect(route('student.cases.show', $case));

        $second = $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true]);
        $second->assertRedirect(route('student.cases.show', $case));

        $this->assertSame(1, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->count());
    }

    public function test_unknown_fields_are_rejected_when_submitting_through_the_http_endpoint(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $response = $this->post(route('student.cases.submit', $case), [
            'deidentification_attested' => true,
            'status' => 'approved',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    public function test_missing_attestation_is_rejected_when_submitting_through_the_http_endpoint(): void
    {
        [, $student, , $case] = $this->completeCase();
        $this->actingAs($student);

        $response = $this->post(route('student.cases.submit', $case), []);

        $response->assertSessionHasErrors('deidentification_attested');
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);
    }

    /** @return array{Institution, User, User, ClinicalCase} */
    private function completeCase(): array
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
            'status' => CaseStatus::Draft,
            'form_version' => 'pharmd-case-v1',
            'care_setting' => 'inpatient',
            'encounter_date' => '2026-10-15',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);

        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);

        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);

        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'S', 'objective' => 'O', 'assessment' => 'A', 'plan' => 'P',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        return [$institution, $student, $faculty, $case];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/CaseSubmissionTest.php`
Expected: FAIL — `SubmitCase::__invoke()` does not accept a third argument yet, `CaseNotReadyForSubmissionException` does not exist, `StoreSubmissionRequest` does not exist, and `ClinicalCasePolicy::submit()` still denies Submitted (so the repeated-HTTP-submit test 403s instead of redirecting).

- [ ] **Step 3: Add the exception class**

Create `app/Exceptions/CaseNotReadyForSubmissionException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class CaseNotReadyForSubmissionException extends RuntimeException
{
    /** @param list<array{section: string, message: string}> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Case is not ready for submission.');
    }

    /** @return list<array{section: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }
}
```

- [ ] **Step 4: Add the submission request**

Create `app/Http/Requests/Student/StoreSubmissionRequest.php`:

```php
<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSubmissionRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->route('case')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'deidentification_attested' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
```

- [ ] **Step 5: Rewrite `SubmitCase`**

Replace `app/Actions/SubmitCase.php` with:

```php
<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\CaseCompletenessService;
use Illuminate\Support\Facades\DB;

class SubmitCase
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly CaseCompletenessService $completeness,
    ) {}

    /**
     * Reachable while the case is Draft, Returned, or Submitted — see
     * ClinicalCasePolicy::submit(). A Draft/Returned case is validated and
     * submitted normally. A Submitted case (a duplicated or retried request)
     * returns the existing version unchanged, without re-validating or
     * re-recording anything — this is what makes a retried POST safe.
     * UnderReview/Approved never reach this method: the policy denies them
     * before the controller runs.
     */
    public function __invoke(User $actor, ClinicalCase $case, bool $deidentificationAttested): CaseVersion
    {
        return DB::transaction(function () use ($actor, $case, $deidentificationAttested): CaseVersion {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            if ($case->status === CaseStatus::Submitted) {
                $latest = $case->versions()->latest('version_number')->first();
                abort_unless($latest !== null, 500, 'Case is Submitted but has no version — this should be unreachable.');

                return $latest;
            }

            $errors = $this->completeness->submissionErrors($case);
            if (! $deidentificationAttested) {
                $errors[] = ['section' => 'submission_review', 'message' => 'Confirm the de-identification attestation before submitting.'];
            }
            if ($errors !== []) {
                throw new CaseNotReadyForSubmissionException($errors);
            }

            $case->forceFill([
                'deidentification_attested_at' => now(),
                'deidentification_attested_by' => $actor->id,
            ])->save();

            $snapshot = $this->buildSnapshot($case);
            $versionNumber = $case->current_revision_number + 1;

            $version = CaseVersion::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'version_number' => $versionNumber,
                'source_revision_number' => $case->currentSoap->revision_number,
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $fromStatus = $case->status;
            $case->update([
                'status' => CaseStatus::Submitted,
                'current_revision_number' => $versionNumber,
                'submitted_at' => now(),
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Submitted->value,
                'actor_id' => $actor->id,
                'case_version_id' => $version->id,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.submitted', [
                'case_id' => $case->id,
                'version_number' => $versionNumber,
            ]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    private function buildSnapshot(ClinicalCase $case): array
    {
        $profile = $case->clinicalProfile;
        $soap = $case->currentSoap;

        return [
            'form_version' => $case->form_version->value,
            'case_context' => [
                'encounter_date' => $case->encounter_date?->toDateString(),
                'case_category' => $case->case_category,
                'care_setting' => $case->care_setting,
                'hospital_day_at_first_review' => $case->hospital_day_at_first_review,
                'information_source' => $case->information_source,
                'age_value' => $case->age_value,
                'age_unit' => $case->age_unit,
                'sex' => $case->sex,
                'weight_kg' => $case->weight_kg,
                'height_cm' => $case->height_cm,
                'pregnancy_lactation_status' => $case->pregnancy_lactation_status,
            ],
            'history_diagnosis' => $profile === null ? null : [
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
            ],
            'vitals' => [
                'status' => $case->vitals_status,
                'unavailable_reason' => $case->vitals_unavailable_reason,
                'entries' => $case->vitals()->get()->map(fn (CaseVital $v): array => [
                    'observation_type' => $v->observation_type,
                    'value_numeric' => $v->value_numeric,
                    'value_text' => $v->value_text,
                    'value_systolic' => $v->value_systolic,
                    'value_diastolic' => $v->value_diastolic,
                    'unit' => $v->unit,
                    'observed_on' => $v->observed_on?->toDateString(),
                    'observed_at_time' => $v->observed_at_time,
                    'source' => $v->source,
                    'note' => $v->note,
                ])->all(),
            ],
            'investigations' => [
                'status' => $case->investigations_status,
                'unavailable_reason' => $case->investigations_unavailable_reason,
                'entries' => $case->investigations()->get()->map(fn (CaseInvestigation $i): array => [
                    'test_name' => $i->test_name,
                    'result_type' => $i->result_type,
                    'result_value' => $i->result_value,
                    'unit' => $i->unit,
                    'unit_not_stated' => $i->unit_not_stated,
                    'reference_range' => $i->reference_range,
                    'reference_range_not_provided' => $i->reference_range_not_provided,
                    'reported_flag' => $i->reported_flag,
                    'observed_on' => $i->observed_on?->toDateString(),
                    'observed_at_time' => $i->observed_at_time,
                    'interpretation' => $i->interpretation,
                ])->all(),
            ],
            'medication_chart' => [
                'status' => $case->medication_chart_status,
                'none_reason' => $case->medication_chart_none_reason,
                'entries' => $case->medications()->where(fn ($q) => $q->where('medication_context', '!=', 'history')->orWhereNull('medication_context'))->get()->map(fn (CaseMedication $m): array => [
                    'generic_name' => $m->generic_name,
                    'brand_name' => $m->brand_name,
                    'indication' => $m->indication,
                    'indication_unclear' => $m->indication_unclear,
                    'dose_amount' => $m->dose_amount,
                    'dose_unit' => $m->dose_unit,
                    'dosage_form' => $m->dosage_form,
                    'route' => $m->route,
                    'frequency' => $m->frequency,
                    'start_reference' => $m->start_reference,
                    'stop_reference' => $m->stop_reference,
                    'status' => $m->status?->value,
                    'prn_indication' => $m->prn_indication,
                    'notes' => $m->notes,
                ])->all(),
            ],
            'soap' => $soap === null ? null : [
                'subjective' => $soap->subjective,
                'objective' => $soap->objective,
                'assessment' => $soap->assessment,
                'plan' => $soap->plan,
                'monitoring_plan' => $soap->monitoring_plan,
                'monitoring_plan_not_applicable' => $soap->monitoring_plan_not_applicable,
                'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
                'drug_related_problem_status' => $soap->drug_related_problem_status,
                'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            ],
            'clinical_activities' => $case->clinicalActivities->map(fn (CaseClinicalActivity $activity): array => [
                'activity_type' => $activity->activity_type->value,
                'status' => $activity->status,
                'details' => $activity->details,
            ])->values()->all(),
        ];
    }
}
```

`submissionErrors()` (Task 1) guarantees `currentSoap` is non-null and the medication chart has at least one non-history row by the time this method reaches `buildSnapshot()` — both a null SOAP and an incomplete medication chart always produce an error and the method returns before this point — so `$case->currentSoap->revision_number` is accessed directly, not null-safely, and the snapshot's medication query mirrors the service's own `reject('history')` filter (as a query condition, since this runs against the database rather than an already-loaded collection).

- [ ] **Step 6: Broaden `ClinicalCasePolicy::submit()` and add `reviewForSubmission()`**

Replace `app/Policies/ClinicalCasePolicy.php`'s `submit()` method and add a new method directly after it:

```php
    /**
     * Allows Submitted in addition to Draft/Returned so a duplicated or
     * retried POST to /submit while the case is already Submitted reaches
     * SubmitCase, which returns the existing version idempotently instead of
     * erroring or being denied. UnderReview/Approved are intentionally
     * excluded: once a faculty member has started or finished reviewing,
     * submission is a one-way door and a POST at that point is a genuine
     * denial, not a no-op.
     */
    public function submit(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Student
            && $case->student_id === $user->id
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned, CaseStatus::Submitted], true);
    }

    /**
     * Narrower than submit(): the read-only submission-review screen only
     * makes sense while there is still something to review before
     * submitting. Once a case is Submitted (or later), there is nothing left
     * to review-before-submitting, so this denies where submit() now allows.
     */
    public function reviewForSubmission(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Student
            && $case->student_id === $user->id
            && in_array($case->status, [CaseStatus::Draft, CaseStatus::Returned], true);
    }
```

- [ ] **Step 7: Update `SubmissionController`**

Replace `app/Http/Controllers/Student/SubmissionController.php` with:

```php
<?php

namespace App\Http\Controllers\Student;

use App\Actions\SubmitCase;
use App\Exceptions\CaseNotReadyForSubmissionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubmissionRequest;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, ClinicalCase $case, SubmitCase $submitCase): RedirectResponse
    {
        try {
            $submitCase($request->user(), $case, $request->boolean('deidentification_attested'));
        } catch (CaseNotReadyForSubmissionException $e) {
            return redirect()
                ->route('student.cases.submission-review', $case)
                ->with('toast', ['type' => 'error', 'message' => 'This case is not ready to submit.'])
                ->withErrors(['submission' => collect($e->errors())->pluck('message')->all()]);
        }

        return redirect()->route('student.cases.show', $case)
            ->with('toast', ['type' => 'success', 'message' => 'Case submitted for review.']);
    }
}
```

The `CaseNotReadyForSubmissionException` catch branch is only reachable while the case is Draft/Returned (the policy already excludes UnderReview/Approved, and a Submitted case never re-validates, per Step 5) — so redirecting to `student.cases.submission-review` never targets a status that route itself would then deny.

This redirects to `student.cases.submission-review`, which Task 3 creates in this same slice — none of this task's own tests reach that redirect branch, so nothing here depends on the route existing yet; Step 9 confirms all of this task's tests still pass before Task 3 adds it.

- [ ] **Step 8: Fix the two pre-existing `SubmitCase` tests in `ClinicalCaseWorkflowTest.php`**

Both `test_student_can_create_case_and_submit_soap_note` and `test_student_can_resubmit_returned_case` build a case with only a SOAP note, which the new completeness gate now correctly rejects — this is the walking-skeleton shortcut this task exists to remove, matching the "pre-existing test whose assertion contradicts required behavior" precedent from Slice 2C Task 5.

Replace the file's import block with:

```php
use App\Actions\ApproveCase;
use App\Actions\ReturnCase;
use App\Actions\SubmitCase;
use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
```

Replace `test_student_can_create_case_and_submit_soap_note` with:

```php
    public function test_student_can_create_case_and_submit_soap_note(): void
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

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
            'form_version' => 'pharmd-case-v1',
            'encounter_date' => '2026-10-15',
            'case_category' => 'Drug Therapy Problem',
            'clinical_site_id' => $site->id,
            'care_setting' => 'inpatient',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Draft->value]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Patient reports headache for 3 days.',
            'objective' => 'BP 140/90, HR 80.',
            'assessment' => 'Tension-type headache.',
            'plan' => 'Recommend paracetamol 500mg TID.',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case, true);

        $this->assertNotNull($version);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', ['clinical_case_id' => $case->id, 'version_number' => 1]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Draft->value,
            'to_status' => CaseStatus::Submitted->value,
        ]);
    }
```

Replace `test_student_can_resubmit_returned_case` with:

```php
    public function test_student_can_resubmit_returned_case(): void
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
            'status' => CaseStatus::Returned,
            'current_revision_number' => 1,
            'form_version' => 'pharmd-case-v1',
            'care_setting' => 'inpatient',
            'encounter_date' => '2026-10-15',
            'information_source' => 'case sheet',
            'age_value' => 34,
            'age_unit' => 'years',
            'sex' => 'female',
            'vitals_status' => 'recorded',
            'investigations_status' => 'recorded',
            'medication_chart_status' => 'documented',
        ]);

        CaseClinicalProfile::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'chief_complaints' => [['complaint' => 'Headache', 'duration' => '3 days']],
            'history_present_illness' => 'Three-day headache, no red flags.',
            'diagnoses' => [['label' => 'Tension headache', 'type' => 'provisional']],
            'past_medical_history_none' => true,
            'allergy_status' => 'no_known_allergy',
            'last_saved_by' => $student->id,
        ]);
        CaseVital::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'observation_type' => 'pulse', 'value_numeric' => 80, 'recorded_by' => $student->id,
        ]);
        CaseInvestigation::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'test_name' => 'Haemoglobin', 'result_type' => 'numeric', 'result_value' => '13.5',
            'unit' => 'g/dL', 'reference_range_not_provided' => true, 'recorded_by' => $student->id,
        ]);
        CaseMedication::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'medication_context' => null, 'generic_name' => 'Paracetamol', 'indication' => 'Headache relief',
            'dose_amount' => '500', 'dose_unit' => 'mg', 'route' => 'oral', 'frequency' => 'TID',
            'status' => 'active', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'adr', 'status' => 'no', 'recorded_by' => $student->id,
        ]);
        CaseClinicalActivity::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id, 'clinical_case_id' => $case->id,
            'activity_type' => 'counselling', 'status' => 'not_indicated', 'recorded_by' => $student->id,
        ]);

        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'subjective' => 'Updated subjective.',
            'objective' => 'Updated objective.',
            'assessment' => 'Updated assessment with more detail.',
            'plan' => 'Updated plan.',
            'monitoring_plan' => 'Review pain score at 24h.',
            'drug_related_problem_status' => 'none_identified',
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        $this->actingAs($student);

        $submitCase = app(SubmitCase::class);
        $version = $submitCase($student, $case, true);

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
        $this->assertDatabaseHas('case_versions', ['clinical_case_id' => $case->id, 'version_number' => 2]);
    }
```

`test_faculty_can_approve_submitted_case`, `test_faculty_can_return_case_for_correction`, and `test_creating_a_case_persists_new_context_fields_and_always_uses_the_server_form_version` do not call `SubmitCase` and are unaffected — leave them exactly as they are.

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/CaseSubmissionTest.php tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS.

- [ ] **Step 10: Static analysis and formatting**

Run: `vendor/bin/phpstan analyse` — expect 0 errors.
Run: `vendor/bin/pint --dirty`

- [ ] **Step 11: Commit**

```bash
git add app/Policies/ClinicalCasePolicy.php app/Actions/SubmitCase.php app/Http/Controllers/Student/SubmissionController.php app/Http/Requests/Student/StoreSubmissionRequest.php app/Exceptions/CaseNotReadyForSubmissionException.php tests/Feature/CaseSubmissionTest.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: correct the submit/review authorization contract and gate submission on completeness and attestation"
```

---

## Task 3: Extract `ClinicalCasePresenter` and add the read-only submission review endpoint

**Files:**
- Create: `app/Services/ClinicalCasePresenter.php` (payload-building methods moved out of `CaseEditorController`)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (delegates to the presenter — pure refactor, no prop-shape change)
- Create: `app/Http/Controllers/Student/SubmissionReviewController.php`
- Modify: `routes/web.php` (add the `GET student/cases/{case}/submission-review` route)
- Test: `tests/Feature/SubmissionReviewPageTest.php` (new)

**Interfaces:**
- Consumes: `App\Services\CaseCompletenessService` (Task 1), `ClinicalCasePolicy::reviewForSubmission()` (Task 2 — **not** `submit`, per the corrected status contract).
- Produces (consumed by Task 4's frontend page): the `student/SubmissionReview` Inertia component receives `clinicalCase`, `sectionCompletion: Record<string, boolean>`, `submissionErrors: {section: string, message: string}[]`, plus the identical `context`/`clinicalProfile`/`vitals`/`investigations`/`medications`/`soap`/`adr`/`counselling`/`interventions`/`monitoringFollowUps` shape `student/CaseEditor` already receives. Route name: `student.cases.submission-review`.

- [ ] **Step 1: Write the failing feature test for the new route**

Create `tests/Feature/SubmissionReviewPageTest.php`:

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
use App\Models\SoapNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionReviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owning_student_sees_missing_sections_on_an_incomplete_draft(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Draft);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student/SubmissionReview')
                ->where('sectionCompletion.soap', false)
                ->has('submissionErrors'));
    }

    public function test_review_is_forbidden_once_the_case_has_left_draft_or_returned_status(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Submitted);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertForbidden();
    }

    public function test_a_returned_case_can_still_be_reviewed(): void
    {
        [, $student, $case] = $this->makeCase(CaseStatus::Returned);

        $this->actingAs($student)->get(route('student.cases.submission-review', $case))
            ->assertOk();
    }

    /** @return array{Institution, User, ClinicalCase} */
    private function makeCase(CaseStatus $status): array
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
            'current_revision_number' => 1,
        ]);
        SoapNote::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'revision_number' => 1,
            'author_id' => $student->id,
            'last_saved_by' => $student->id,
        ]);

        return [$institution, $student, $case];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/SubmissionReviewPageTest.php`
Expected: FAIL — route `student.cases.submission-review` does not exist.

- [ ] **Step 3: Extract the presenter**

Create `app/Services/ClinicalCasePresenter.php` with the payload-building logic moved verbatim out of `CaseEditorController` (field lists exactly as they exist there today — see `app/Http/Controllers/Student/CaseEditorController.php:59-176`):

```php
<?php

namespace App\Services;

use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;

class ClinicalCasePresenter
{
    /** @return array<string, mixed> */
    public function context(ClinicalCase $case): array
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
            'vitals_status' => $case->vitals_status,
            'vitals_unavailable_reason' => $case->vitals_unavailable_reason,
            'vitals_availability_lock_version' => $case->vitals_availability_lock_version,
            'investigations_status' => $case->investigations_status,
            'investigations_unavailable_reason' => $case->investigations_unavailable_reason,
            'investigations_availability_lock_version' => $case->investigations_availability_lock_version,
            'medication_chart_status' => $case->medication_chart_status,
            'medication_chart_none_reason' => $case->medication_chart_none_reason,
            'medication_chart_availability_lock_version' => $case->medication_chart_availability_lock_version,
            'lock_version' => $case->lock_version,
            'updated_at' => $case->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function clinicalProfile(ClinicalCase $case): ?array
    {
        $profile = $case->clinicalProfile;

        return $profile === null ? null : [
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

    /** @return list<array<string, mixed>> */
    public function vitals(ClinicalCase $case): array
    {
        return $case->vitals()->orderByDesc('created_at')->get()->map(fn (CaseVital $vital): array => [
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
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function investigations(ClinicalCase $case): array
    {
        return $case->investigations()->orderByDesc('created_at')->get()->map(fn (CaseInvestigation $investigation): array => [
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
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function medications(ClinicalCase $case): array
    {
        return $case->medications()->orderByDesc('created_at')->get()->map(fn (CaseMedication $medication): array => [
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
        ])->all();
    }

    /** @return array<string, mixed>|null */
    public function soap(ClinicalCase $case): ?array
    {
        $soap = $case->currentSoap;

        return $soap === null ? null : [
            'subjective' => $soap->subjective,
            'objective' => $soap->objective,
            'assessment' => $soap->assessment,
            'plan' => $soap->plan,
            'monitoring_plan' => $soap->monitoring_plan,
            'monitoring_plan_not_applicable' => $soap->monitoring_plan_not_applicable,
            'monitoring_plan_not_applicable_reason' => $soap->monitoring_plan_not_applicable_reason,
            'drug_related_problem_status' => $soap->drug_related_problem_status,
            'drug_related_problem_categories' => $soap->drug_related_problem_categories,
            'lock_version' => $soap->lock_version,
            'updated_at' => $soap->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function singletonActivity(ClinicalCase $case, ClinicalActivityType $type): ?array
    {
        $activity = $case->clinicalActivities->firstWhere('activity_type', $type);

        return $activity === null ? null : $this->activityPayload($activity);
    }

    /** @return list<array<string, mixed>> */
    public function repeatableActivity(ClinicalCase $case, ClinicalActivityType $type): array
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
}
```

- [ ] **Step 4: Wire `CaseEditorController` to the presenter**

Replace `app/Http/Controllers/Student/CaseEditorController.php` with:

```php
<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Services\ClinicalCasePresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CaseEditorController extends Controller
{
    public function show(ClinicalCase $case, ClinicalCasePresenter $presenter): Response
    {
        Gate::authorize('update', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $case->load('clinicalActivities');

        return Inertia::render('student/CaseEditor', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'userId' => request()->user()->id,
            'context' => $presenter->context($case),
            'clinicalProfile' => $presenter->clinicalProfile($case),
            'vitals' => $presenter->vitals($case),
            'investigations' => $presenter->investigations($case),
            'medications' => $presenter->medications($case),
            'soap' => $presenter->soap($case),
            'adr' => $presenter->singletonActivity($case, ClinicalActivityType::Adr),
            'counselling' => $presenter->singletonActivity($case, ClinicalActivityType::Counselling),
            'interventions' => $presenter->repeatableActivity($case, ClinicalActivityType::Intervention),
            'monitoringFollowUps' => $presenter->repeatableActivity($case, ClinicalActivityType::Monitoring),
        ]);
    }
}
```

- [ ] **Step 5: Add `SubmissionReviewController`**

Create `app/Http/Controllers/Student/SubmissionReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClinicalActivityType;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use App\Services\CaseCompletenessService;
use App\Services\ClinicalCasePresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionReviewController extends Controller
{
    public function show(ClinicalCase $case, ClinicalCasePresenter $presenter, CaseCompletenessService $completeness): Response
    {
        // reviewForSubmission (not submit): once a case is Submitted there is
        // nothing left to review-before-submitting, even though submit()
        // itself now allows a Submitted case through for idempotent retry.
        // See this plan's "Submission status contract" note.
        Gate::authorize('reviewForSubmission', $case);

        $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
        $case->load('clinicalActivities');

        return Inertia::render('student/SubmissionReview', [
            'clinicalCase' => $case->only(['id', 'case_number', 'status']),
            'sectionCompletion' => $completeness->sectionCompletion($case),
            'submissionErrors' => $completeness->submissionErrors($case),
            'context' => $presenter->context($case),
            'clinicalProfile' => $presenter->clinicalProfile($case),
            'vitals' => $presenter->vitals($case),
            'investigations' => $presenter->investigations($case),
            'medications' => $presenter->medications($case),
            'soap' => $presenter->soap($case),
            'adr' => $presenter->singletonActivity($case, ClinicalActivityType::Adr),
            'counselling' => $presenter->singletonActivity($case, ClinicalActivityType::Counselling),
            'interventions' => $presenter->repeatableActivity($case, ClinicalActivityType::Intervention),
            'monitoringFollowUps' => $presenter->repeatableActivity($case, ClinicalActivityType::Monitoring),
        ]);
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add `use App\Http\Controllers\Student\SubmissionReviewController;` to the imports and, directly above the existing submit route, add:

```php
Route::get('student/cases/{case}/submission-review', [SubmissionReviewController::class, 'show'])->name('student.cases.submission-review');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/SubmissionReviewPageTest.php tests/Feature/CaseEditorPageTest.php tests/Feature/CaseSubmissionTest.php`
Expected: PASS — `CaseEditorPageTest` must still pass unchanged, confirming the presenter extraction preserved the exact prop shape; `CaseSubmissionTest`'s repeated-HTTP-submit test now passes too, since Task 2 already broadened `submit()`.

- [ ] **Step 8: Static analysis and formatting**

Run: `vendor/bin/phpstan analyse` — expect 0 errors.
Run: `vendor/bin/pint --dirty`

- [ ] **Step 9: Commit**

```bash
git add app/Services/ClinicalCasePresenter.php app/Http/Controllers/Student/CaseEditorController.php app/Http/Controllers/Student/SubmissionReviewController.php routes/web.php tests/Feature/SubmissionReviewPageTest.php
git commit -m "feat: add the read-only submission review endpoint, extracting a shared case presenter"
```

---

## Task 4: Submission review page, section deep-linking, and the case-show submit button

**Files:**
- Create: `resources/js/pages/student/SubmissionReview.vue`
- Modify: `resources/js/pages/student/CaseEditor.vue` (accept an optional `?section=` query param to open directly on a given section)
- Modify: `app/Http/Controllers/Student/CaseEditorController.php` (pass the query param through as `initialSection`)
- Modify: `resources/js/pages/student/CaseShow.vue` (Submit routes to the review page instead of posting directly)
- Test: `tests/Feature/CaseEditorPageTest.php` (one new test for the deep-link param)

**Interfaces:**
- Consumes: the `student/SubmissionReview` Inertia props from Task 3.
- Produces: none consumed by a later task — this is the user-facing surface for the whole slice.

- [ ] **Step 1: Write the failing test for section deep-linking**

Add to `tests/Feature/CaseEditorPageTest.php` (inside the existing class, reusing its `makeCase()` helper):

```php
    public function test_edit_page_passes_the_requested_section_through_as_initial_section(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->actingAs($student)->get(route('student.cases.edit', $case).'?section=soap')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('initialSection', 'soap'));
    }

    public function test_edit_page_has_a_null_initial_section_by_default(): void
    {
        [, $student, $case] = $this->makeCase();

        $this->actingAs($student)->get(route('student.cases.edit', $case))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('initialSection', null));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/CaseEditorPageTest.php`
Expected: FAIL — `initialSection` prop does not exist yet.

- [ ] **Step 3: Pass the query param through `CaseEditorController`**

In `app/Http/Controllers/Student/CaseEditorController.php`, add one key to the `Inertia::render()` array from Task 3's version:

```php
            'monitoringFollowUps' => $presenter->repeatableActivity($case, ClinicalActivityType::Monitoring),
            'initialSection' => request()->query('section'),
        ]);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/CaseEditorPageTest.php`
Expected: PASS.

- [ ] **Step 5: Consume `initialSection` in `CaseEditor.vue`**

In `resources/js/pages/student/CaseEditor.vue`, add `initialSection: string | null;` to the `defineProps<{...}>()` type (after `monitoringFollowUps`), and change:

```ts
const activeIndex = ref(0);
```

to:

```ts
const initialSectionIndex = sections.findIndex(
    (section) => section.id === props.initialSection,
);
const activeIndex = ref(initialSectionIndex >= 0 ? initialSectionIndex : 0);
```

- [ ] **Step 6: Build the submission review page**

Create `resources/js/pages/student/SubmissionReview.vue`:

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, CheckCircle2, WifiOff } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type SectionId =
    | 'case_profile'
    | 'history_diagnosis'
    | 'vitals_investigations'
    | 'medication_chart'
    | 'soap'
    | 'clinical_activities';

const sectionLabels: Record<SectionId, string> = {
    case_profile: 'Case Profile',
    history_diagnosis: 'History & Diagnosis',
    vitals_investigations: 'Vitals & Investigations',
    medication_chart: 'Medication Chart',
    soap: 'SOAP',
    clinical_activities: 'Conditional Clinical Activities',
};

const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    sectionCompletion: Record<SectionId, boolean>;
    submissionErrors: { section: SectionId; message: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
        ],
    },
});

const online = ref(navigator.onLine);
const handleOnline = () => {
    online.value = true;
};
const handleOffline = () => {
    online.value = false;
};
onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});
onUnmounted(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});

const attested = ref(false);
const isReady = computed(() => props.submissionErrors.length === 0);
const canSubmit = computed(() => isReady.value && attested.value && online.value);

const form = useForm({ deidentification_attested: false });
const submit = () => {
    form.deidentification_attested = true;
    form.post(`/student/cases/${props.clinicalCase.id}/submit`, { preserveScroll: true });
};

const goToSection = (section: SectionId) =>
    router.get(`/student/cases/${props.clinicalCase.id}/edit?section=${section}`);

const sectionIds: SectionId[] = [
    'case_profile',
    'history_diagnosis',
    'vitals_investigations',
    'medication_chart',
    'soap',
    'clinical_activities',
];
</script>

<template>
    <Head title="Submission review" />
    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10">
        <div>
            <p class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase">
                Case #{{ clinicalCase.case_number }}
            </p>
            <h1 class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white">
                Submission review
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Review every section before submitting. Nothing here has been sent to your faculty reviewer yet.
            </p>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="font-display text-lg font-semibold text-[#0b2942] dark:text-white">
                Section status
            </h2>
            <ul class="mt-4 space-y-2">
                <li
                    v-for="section in sectionIds"
                    :key="section"
                    class="flex items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <CheckCircle2
                            v-if="sectionCompletion[section]"
                            class="size-4 text-green-600"
                        />
                        <AlertCircle v-else class="size-4 text-amber-600" />
                        {{ sectionLabels[section] }}
                    </span>
                    <Button variant="link" class="h-auto p-0" @click="goToSection(section)">
                        {{ sectionCompletion[section] ? 'Review' : 'Complete' }}
                    </Button>
                </li>
            </ul>
        </section>

        <section
            v-if="submissionErrors.length"
            class="rounded-3xl border border-amber-300 bg-amber-50 p-5 sm:p-7 dark:border-amber-700 dark:bg-amber-950"
        >
            <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-amber-800 dark:text-amber-200">
                <AlertTriangle class="size-5" /> Before you can submit
            </h2>
            <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-amber-800 dark:text-amber-200">
                <li v-for="(error, index) in submissionErrors" :key="index">
                    <button type="button" class="underline underline-offset-2" @click="goToSection(error.section)">
                        {{ error.message }}
                    </button>
                </li>
            </ul>
        </section>

        <section
            v-else
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 class="font-display text-lg font-semibold text-[#0b2942] dark:text-white">
                De-identification attestation
            </h2>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <Checkbox v-model="attested" />
                <span>
                    I confirm this case contains no patient name, initials, UHID/MRN/IP/OP number, bed number,
                    Aadhaar, phone, address, email, full date of birth or photograph, and is ready for faculty
                    review.
                </span>
            </label>

            <p v-if="!online" class="mt-4 flex items-center gap-2 rounded-xl bg-slate-100 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <WifiOff class="size-4" /> You are offline. Reconnect to submit this case.
            </p>

            <Button
                class="mt-5 w-full bg-[#0b2942] text-white sm:w-auto"
                :disabled="!canSubmit || form.processing"
                @click="submit"
            >
                Submit for review
            </Button>
        </section>
    </main>
</template>
```

- [ ] **Step 7: Point `CaseShow.vue`'s Submit button at the review page**

In `resources/js/pages/student/CaseShow.vue`, remove `submitForm`/`submitCase` (the direct `POST /submit` call) and `canSubmit()`'s SOAP check, replacing:

```ts
const submitForm = useForm({});
const submitCase = () =>
    submitForm.post(`/student/cases/${props.clinicalCase.id}/submit`, {
        preserveScroll: true,
    });
```

and

```ts
const canSubmit = (): boolean => {
    return (
        (props.clinicalCase.status === 'draft' ||
            props.clinicalCase.status === 'returned') &&
        hasSoap(props.clinicalCase.current_soap)
    );
};
```

with:

```ts
const canReviewForSubmission = (): boolean =>
    props.clinicalCase.status === 'draft' || props.clinicalCase.status === 'returned';

const goToSubmissionReview = () =>
    router.get(`/student/cases/${props.clinicalCase.id}/submission-review`);
```

and update the template's Submit button:

```vue
                <Button
                    v-if="canReviewForSubmission()"
                    class="bg-[#0b2942] text-white"
                    @click="goToSubmissionReview"
                >
                    <Send class="mr-1 size-4" /> Review & submit
                </Button>
```

`hasSoap()` stays — it is still used by the "No SOAP note yet." message further down the template. If `useForm` was only imported for the removed `submitForm`, remove it from the `import { Head, router, useForm } from '@inertiajs/vue3';` line; keep it if the file uses it elsewhere.

This intentionally makes the button always available in Draft/Returned regardless of what is filled in — the review screen, not a disabled button, is now what tells the student what is missing.

- [ ] **Step 8: Manual verification**

Run (PowerShell, background): `php artisan serve` and `npm run dev`.

As a student with an active rotation assignment and a Draft case with no sections filled: open the case, click "Review & submit," confirm every section shows the amber "Complete" state and the error list names every missing item, **including "Suspected ADR has not been answered"** even before touching that section (the corrected mandatory-answer rule) and, for a medicine row with only a generic name typed in, a message naming the missing indication/dose/route/frequency rather than treating the row as done. Click one error's text and confirm it opens the editor directly on that section (`?section=...` in the URL) with that section's nav pill active. Fill in every section — including answering Suspected ADR "No" and Patient counselling "Not indicated," and every medicine row's indication/dose/route/frequency — return to the review page, confirm all six show green "Review," the error panel is replaced by the attestation panel, and the Submit button is disabled until the checkbox is checked. Toggle DevTools offline: confirm the button disables and the "You are offline" message appears; reconnect and confirm it re-enables. Check the box and submit; confirm redirect to the case-show page with a "Case submitted for review" toast and status badge now "Submitted." Click "Review & submit" is no longer available on that page (status is Submitted); confirm visiting `/student/cases/{id}/submission-review` directly now 403s.

- [ ] **Step 9: Frontend checks**

Run: `npm run check`
Run: `npm run types:check`
Run: `npm run build`

- [ ] **Step 10: Full backend suite**

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 11: Commit**

```bash
git add resources/js/pages/student/SubmissionReview.vue resources/js/pages/student/CaseEditor.vue resources/js/pages/student/CaseShow.vue app/Http/Controllers/Student/CaseEditorController.php tests/Feature/CaseEditorPageTest.php
git commit -m "feat: add the submission review screen with section deep-linking and de-identification attestation"
```

---

## Task 5: Cross-cutting authorization sweep

**Files:**
- Modify: `tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`

**Interfaces:** None — test-only task, verifying behavior Tasks 2–3 already implement via the corrected policy.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`, reusing its existing `setupInstitution()`/`createCase()` helpers:

```php
    public function test_student_cannot_open_another_students_submission_review(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherStudent = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $otherCase = $this->createCase($institution, $otherStudent, $assignment, CaseStatus::Draft);

        $this->get(route('student.cases.submission-review', $otherCase))->assertForbidden();
    }

    public function test_faculty_cannot_open_or_submit_the_student_submission_routes(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($faculty);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Draft);

        $this->get(route('student.cases.submission-review', $case))->assertForbidden();
        $this->post(route('student.cases.submit', $case))->assertForbidden();
    }

    public function test_submission_review_is_forbidden_once_a_case_leaves_draft_or_returned_status(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($student);

        foreach ([CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Approved] as $status) {
            $case = $this->createCase($institution, $student, $assignment, $status);

            $this->get(route('student.cases.submission-review', $case))->assertForbidden();
        }
    }

    public function test_submit_is_forbidden_once_a_case_is_under_review_or_approved(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($student);

        foreach ([CaseStatus::UnderReview, CaseStatus::Approved] as $status) {
            $case = $this->createCase($institution, $student, $assignment, $status);

            $this->post(route('student.cases.submit', $case), ['deidentification_attested' => true])->assertForbidden();
        }
    }
```

Deliberately not tested here: POSTing `/submit` on a bare `status => Submitted` fixture. `createCase()` only sets a status column — it does not create the `CaseVersion` row that a real Submitted case always has (every real Submitted case reached that status through `SubmitCase`, which creates one in the same transaction). Since `submit()` now allows Submitted through to the action, and the action's Submitted branch does `abort_unless($latest !== null, 500, ...)`, POSTing against this minimal fixture would hit that 500 guard — a fixture-validity problem, not an authorization question. The real "repeated submit on a genuinely-submitted case" contract is already covered by Task 2's `test_a_repeated_http_submit_on_an_already_submitted_case_succeeds_idempotently`, which submits for real first so a version genuinely exists.

- [ ] **Step 2: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`
Expected: PASS immediately for all four, with no production code changes. `EnsureUserHasRole::handle()` (`app/Http/Middleware/EnsureUserHasRole.php:20`) already does `abort_unless(..., 403)` for a logged-in user whose role isn't in the route group's allow-list — the same mechanism `test_faculty_cannot_access_student_case_routes` earlier in this same file already relies on — so a faculty user hitting either new-or-existing student route 403s before reaching any controller or FormRequest. `ClinicalCasePolicy::reviewForSubmission()`/`submit()` (Task 2) then separately cover same-role-wrong-case and wrong-status cases. If any of the four fails, that is a real gap this task exists to catch — do not weaken the assertion to make it pass.

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php
git commit -m "test: sweep cross-institution, cross-role and wrong-status access to submission routes"
```

---

## Task 6: Full end-to-end verification

**Files:** None — verification only. This task does **not** touch `PROJECT_STATE.md` (see the closing note below).

**Interfaces:** None.

- [ ] **Step 1: Full automated suite**

Run: `php artisan test` — record the final pass/skip count.
Run: `vendor/bin/phpstan analyse` — expect 0 errors.
Run: `vendor/bin/pint --test` — expect clean.
Run: `npm run check`, `npm run types:check`, `npm run build` — expect clean.
Run: `git diff --check` — expect no whitespace errors.

- [ ] **Step 2: Manual device verification — phone (390×844)**

As a student with an active rotation assignment:
1. Create a new case, fill every one of the six editor sections completely — including a medicine row with generic name, indication (or "Indication unclear"), dose amount/unit, route and frequency; an investigation with a unit or "Unit not stated"; ADR answered "No"; Counselling answered "Not indicated"; and a monitoring plan.
2. Open "Review & submit." Confirm all six sections show green and the attestation panel (not the error panel) is showing.
3. Submit without checking the attestation box — confirm the button stays disabled and no request is sent.
4. Check the box, submit, confirm the toast and the case-show page now reads "Submitted" with a version listed under "Submission history."
5. Attempt to revisit `/student/cases/{id}/submission-review` for that now-Submitted case directly by URL — confirm it 403s. Click Submit again via a duplicated tab/request against `/student/cases/{id}/submit` (e.g. resubmitting a stale form) — confirm it redirects successfully to the case-show page without creating a second version (check "Submission history" still shows exactly one entry).
6. Create a second, empty case. Open "Review & submit" and confirm the error list matches every missing item exactly, including "Suspected ADR has not been answered" and "Patient counselling status has not been answered" even though neither has been touched. Click three different error messages and confirm each opens the editor on the correct section.
7. In the new case's Medication Chart, add one row with only a generic name. Confirm the review screen's medication_chart section is still red/incomplete and the error message names indication/dose/route/frequency, not just "add a medicine." Complete the row and confirm the error clears.
8. Set SOAP's drug-related-problem status to "Identified" with at least one category, leave Conditional Clinical Activities' intervention section empty, and confirm the review screen lists the missing-intervention error; add one intervention row with problem and recommendation, confirm the error disappears.

- [ ] **Step 3: Offline gating**

Toggle DevTools offline on the review page for a fully-complete case: confirm the Submit button disables and the offline message appears; reconnect and confirm it re-enables without a page reload.

- [ ] **Step 4: Desktop (1280×900, Chrome/Edge)**

Repeat Step 2's walkthrough at desktop width; record any real layout defect as a known limitation rather than fixing it ad hoc mid-verification.

- [ ] **Step 5: Faculty side is unaffected**

As the assigned faculty member, open `faculty/reviews` and confirm the newly-submitted case appears with its correct status — this slice does not touch `ReviewController`/`CaseReview.vue` (Slice 4's job), so this step only confirms no regression.

## After this plan: what still has to happen, and when

This plan does **not** update `PROJECT_STATE.md`. That update happens exactly once, as its own standalone action, only after this slice's pull request has been reviewed and merged to `main` — never while implementing on this feature branch, and never as one of the tasks above. This mirrors what actually happened for Slice 2C (its `PROJECT_STATE.md` update was done as a separate action after PR #13 merged, then had to be corrected again days later because that update itself lagged main) and directly avoids repeating that lag: the update belongs to the merge event, not to any task's own commit.

When that time comes, follow `PROJECT_STATE.md`'s own maintenance rule (§9) and the Slice 2A/2B/2C entries' exact structure: record the merge commit, the final test count and verification evidence from Task 6, the "Discovered gaps and scope reconciliations" items above as deferred/recorded follow-ups (medication-history UI, free-text intervention inference), and move "Exact next action" to Slice 4 (faculty review, correction and reopening, per [`docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`](../../implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md) §3).
