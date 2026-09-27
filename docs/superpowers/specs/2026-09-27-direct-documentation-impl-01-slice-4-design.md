# DIRECT-DOCUMENTATION-IMPL-01 — Slice 4 design: faculty review, correction and reopening

**Status:** Design approved in chat; awaiting written-spec review before `writing-plans`.
**Decision baseline:** `docs/DECISIONS.md` DEC-028–DEC-031; `docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md` §Slice 4.
**Depends on:** Slice 3 (accepted, PR #14) — immutable `CaseVersion` snapshots, the corrected submit/review authorization contract, `ClinicalCasePresenter`.
**Extends (does not replace):** `ReviewController`, `ApproveCase`, `ReturnCase`, `ClinicalCasePolicy`, `CaseVersion`, `CaseStatusTransition`, `AuditTrail`.

## 1. Goal

Deliver the master plan's Slice 4 scope exactly as written:

> Review queue remains assignment- and institution-scoped. Show the exact submitted snapshot and prior versions. Add overall feedback. Add section flags and section-level comments. Require an actionable section comment/reason to return. Unlock all student sections after return and highlight flags. Resubmission creates a new immutable snapshot. Assigned faculty may reopen an approved case with a mandatory reason and audit event. Exclude field comments, rubrics, marks and second review.

Exit evidence (unchanged from the master plan): return → edit → resubmit → approve passes end to end; reopen → correction → resubmit preserves the earlier approved version; unauthorized faculty/admin/student attempts are rejected.

**Out of scope for this slice** (restating DEC-030 and the master plan's exclusion list explicitly, so no later agent mistakes silence for permission): no rubric model, no scoring scale, no faculty-approved assessment criteria, no marks, no field-level annotations, no second reviewer, no comment edit/delete/resolve threads, no automatic rotation-end handling. Slice 4 delivers feedback, correction, resubmission, approval and reopening only. Rubric/marks design starts only after faculty supplies an assessment structure, as a later gate.

## 2. Decisions settled this session

| # | Decision | Chosen |
| - | -------- | ------ |
| 1 | How reopening fits the status machine | Reuse `CaseStatus::Returned` (transition `Approved → Returned`), distinguished in the audit/status-transition trail by `from_status`, not a new enum member. |
| 2 | When faculty submit section comments | Bundled into the return/approve request as one atomic action — no standalone comment-CRUD endpoint. |
| 3 | Whether return requires a flagged section | Yes — returning a case requires at least one section comment with `is_flagged = true`, in addition to the existing free-text `reason`. |
| 4 (added on review) | What a comment belongs to | The exact `CaseStatusTransition` (the Return or Approve event), not just the case version — see §3. |
| 5 (added on review) | Comments per section per event | Exactly one row per `(case_status_transition_id, section)` — DB-enforced. |
| 6 (added on review) | Reopen visibility | The mandatory reopen reason must be surfaced prominently to the student, since reopen carries no section flags — see §6. |

## 3. Data model

One new table, **`case_review_comments`**, following this codebase's established append-only pattern (`case_versions`, `case_status_transitions`, `audit_events` are all insert-only, never mutated after creation):

| Column | Type | Notes |
| --- | --- | --- |
| `id` | ulid PK | |
| `institution_id` | FK | tenancy column, matches every other table in this domain |
| `clinical_case_id` | FK, restrict-on-delete | |
| `case_status_transition_id` | FK, **required**, restrict-on-delete | The exact Return or Approve event this comment was written as part of. This is the authoritative link — a comment belongs to a review *event*, not merely to a version. Reopen transitions never carry comments (see §6), so this column is never null. |
| `section` | string, `CaseReviewSection` enum values | One of the six editor sections (below) |
| `body` | text, required | |
| `is_flagged` | boolean, required | Forced `false` when written from `ApproveCase` (§5) |
| `created_by` | FK to `users` | The reviewing faculty member |
| `created_at` | timestamp | No `updated_at` semantics are relied on — rows are never updated |

**Constraint:** a unique index on `(case_status_transition_id, section)` — exactly one comment per section per review event, matching the UI's one-row-per-section composer. The controller also validates the incoming array has no duplicate section keys before insert, so a client bug fails with a clean 422 rather than a DB constraint violation.

**No separate `case_version_id` column.** A `CaseStatusTransition` already carries `case_version_id` (set by `SubmitCase` and `ApproveCase` today; `ReturnCase` and the new `ReopenCase` are extended to set it too — see §4). Since every comment-bearing transition has a version, the version is always reachable via `comment->caseStatusTransition->case_version_id` without duplicating the foreign key on every comment row.

**`CaseReviewSection` enum** (new, `app/Enums/CaseReviewSection.php`, mirroring the existing `CaseFormVersion` enum's style): `CaseProfile`, `HistoryDiagnosis`, `VitalsInvestigations`, `MedicationChart`, `Soap`, `ClinicalActivities` — the same six groupings the mobile case editor already presents as sections. Vitals and Investigations share one key because the editor presents them as one combined section (Slice 2's "Vitals and Investigations" section), even though the submission snapshot stores them as two separate keys.

## 4. Status machine and reopen

No new `CaseStatus` value. `ReopenCase` (new action, mirroring `ApproveCase`'s shape) transitions `Approved → Returned`:

```php
final class ReopenCase
{
    public function __invoke(User $actor, ClinicalCase $case, string $reason): void
    {
        // Approved-only precondition (ClinicalCasePolicy::reopen), inside DB::transaction():
        //   - CaseStatusTransition::create([... from_status: 'approved', to_status: 'returned',
        //       reason: $reason, case_version_id: $latestApprovedVersion->id ...])
        //   - $case->update(['status' => CaseStatus::Returned])
        //   - $this->audit->record($actor, $case, 'clinical_case.reopened', [...])
        // No CaseReviewComment rows are created.
    }
}
```

Because the prior `CaseVersion` row is never mutated by anything in this slice, "reopen → correction → resubmit preserves the earlier approved version" falls out automatically: the old row keeps its `approved_by`/`approved_at`/`snapshot` untouched, and `SubmitCase` (unchanged) creates a new, separate `CaseVersion` row with the next `version_number` on resubmission.

**Distinguishing an ordinary return from a reopen** uses the existing `from_status` column — no new flag needed:

- Ordinary return: `from_status ∈ {submitted, under_review}`, `to_status = returned`.
- Reopen: `from_status = approved`, `to_status = returned`.

`ReturnCase` is extended to set `case_version_id` on its `CaseStatusTransition` row (a real gap today — it currently omits this field, unlike `ApproveCase`/`SubmitCase`) and to accept the section-comments array (§5).

**Flag highlighting requires no clearing step.** A flagged comment is only ever "current" when it belongs to the transition that produced the case's present `Returned` status. The moment the student resubmits, `SubmitCase` creates a new `CaseVersion` and the case leaves `Returned`; the old comments become pure history (still fully visible, §6) without any code needing to mark them resolved or delete them.

## 5. Actions and authorization

- **`ReturnCase`**: signature gains `array<int, array{section: string, body: string, is_flagged: bool}> $sectionComments`. Controller validates: `reason` required (unchanged), `section_comments` required array with ≥1 entry, each entry's `section` one of the six enum values and unique within the request, `body` required, `is_flagged` boolean, and at least one entry has `is_flagged === true`. Inside one `DB::transaction()`: create the `CaseStatusTransition` first, then bulk-insert the `CaseReviewComment` rows referencing its id — matching the requirement that a comment belongs to the exact transition.
- **`ApproveCase`**: signature gains the same optional `$sectionComments` (default `[]`, no minimum). Every inserted row is forced `is_flagged = false` server-side regardless of what the client sends — an approved case has no next correction round for a flag to mean anything against.
- **`ReopenCase`** (new): gated by new `ClinicalCasePolicy::reopen(User $user, ClinicalCase $case): bool`, true only when the same institution/assigned-faculty check `review()` already uses, and `$case->status === CaseStatus::Approved`. No comments accepted.
- **No new policy class for comments.** They're authorized transitively through the parent action (`review`/`approve`/`returnCase`/`reopen` already gate case-level access) and read through the existing `ClinicalCasePolicy::view()` when eager-loaded as a relation. They are not an independently addressable/editable resource in this slice.

## 6. Read/visibility model

Nothing in this slice ever hides, edits or deletes a `CaseReviewComment` once written — old feedback remains permanently visible, exactly like every other immutable record in this domain.

- **Faculty** (`ReviewController::show`): eager-load `versions.reviewComments.author` (via the transition) and `statusTransitions.actor` for the full version-by-version history — every past Return/Approve event and its section comments, across every version, with no filtering. This is what "show the exact submitted snapshot and prior versions" means for the reviewer.
- **Student** (case editor / case-show page): two distinct things are surfaced, not one:
  1. **Current correction state**, while `status === Returned`: the flagged sections from the transition that produced the current `Returned` status (drives the per-section highlight badges, §7), *and*, if that transition's `from_status === 'approved'` (i.e. a reopen, which carries no flags), a prominent banner reading **"Case reopened: {reason}"** so the student isn't left guessing what changed.
  2. **Full prior-version feedback history**: same as faculty — every past transition and its comments, for the student's own case only, always visible (not gated to only-while-Returned), so a student can review how a case was corrected even after it reaches `Approved`.

Both views read the same rows through the same `institution_id`/`clinical_case_id` scoping already enforced by `BelongsToInstitution` and `ClinicalCasePolicy::view()` — no separate comment-visibility policy is needed.

## 7. Endpoints

One new route, same controller, same pattern as `approve`/`return`:

```
POST /faculty/reviews/{case}/reopen  → ReviewController::reopen
```

`ReviewController::index()`'s query already includes `CaseStatus::Approved` in its `whereIn`, so no route/query change is needed there. `show()`'s eager-load list gains `versions.reviewComments.author` (§6); `statusTransitions` is already loaded and needs no change beyond what §4 adds to the rows themselves.

## 8. Frontend surfaces

- **Faculty `CaseReview` page**: a section-comment composer — one flag-checkbox + body textarea per section — feeding a `section_comments` array into the existing return/approve request payloads. A "Reopen case" action, visible only when the case is `Approved`, behind a mandatory-reason prompt (no section composer shown, per §4/§6).
- **Student case editor / case-show page**: `CaseEditorController`/`ClinicalCasePresenter` computes `flaggedSections: string[]` (current-transition, `is_flagged = true` comments) and, when applicable, `reopenedReason: string|null`, passed as Inertia props. Each section's nav entry gets a highlight badge when its key is in `flaggedSections`; a reopened case shows the "Case reopened: …" banner at the top of the editor. A read-only feedback-history panel (reusing the same version list the submission-review screen already renders) lists every past transition with its reason and section comments — available to the student at any status, not just while `Returned`.

## 9. Testing plan

- **Authorization:** only the assigned faculty member can return/approve/reopen; cross-institution denial on all three; wrong-role denial (student/admin cannot call any of the three); `reopen` denied when case is not `Approved`; `returnCase`/`approve` unaffected by the new comment requirement's absence of a case-version mismatch.
- **Validation:** return without any flagged section comment → 422; return with a duplicate `section` key in the request → 422; return/approve with an invalid section value → 422; the DB unique constraint as a backstop (attempt a raw duplicate insert in a unit test and confirm it fails).
- **Immutability:** after reopen → correction → resubmit → approve, assert the original approved `CaseVersion` row (snapshot, hash, `approved_by`, `approved_at`) is byte-identical to before, and a new `CaseVersion` row exists with the next `version_number`.
- **Status-machine round trips:** submit → return (with a flagged section + reason) → resubmit → approve; approve → reopen (reason only) → correct → resubmit → approve again — both passing end to end per the master plan's exit evidence.
- **Visibility:** faculty review page shows every past version's comments; student sees current flagged sections only while `Returned` from an ordinary return, sees the reopened banner (not flags) when `from_status = approved`, and can still see full historical feedback after the case reaches `Approved` again.

## 10. Files touched (for the implementation plan to size, not exhaustive)

New: migration for `case_review_comments`; `CaseReviewComment` model; `CaseReviewSection` enum; `ReopenCase` action; `ReviewController::reopen`; route; frontend composer/badge/banner/history-panel components.
Extended: `ReturnCase`, `ApproveCase` (comments param), `ClinicalCasePolicy` (`reopen`), `ReviewController::show`/`index` eager-loads, `CaseEditorController`/`ClinicalCasePresenter` (flagged-sections/reopened-reason props), student and faculty Inertia pages.

## Self-review

- **Placeholders:** none — every section states a concrete mechanism, not a TBD.
- **Internal consistency:** §3's removal of a per-comment `case_version_id` is consistent with §4's requirement that `ReturnCase`/`ReopenCase` set `case_version_id` on the transition; §6's student-visibility rule is consistent with §4's `from_status` distinction; §9's tests cover every decision in §2.
- **Scope:** matches the master plan's Slice 4 bullets and exit evidence exactly; §1 restates the exclusion list so a later agent can't read silence as permission to add rubrics/marks.
- **Ambiguity:** "one comment per section per review event" is now DB-enforced, not just a UI convention; "prominently" for the reopen banner is made concrete (a page-level banner reading the literal reopen reason, not folded into the flagged-sections mechanism it doesn't have).
