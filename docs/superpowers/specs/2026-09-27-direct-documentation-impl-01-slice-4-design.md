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
| 7 (added on second review) | Read path | `CaseVersion → statusTransitions → reviewComments → author`, three plain relations, no `hasManyThrough` — see §3. |
| 8 (added on second review) | Concurrency | All three review actions lock the row and re-check status after acquiring the lock, matching `SubmitCase`'s existing pattern — see §5. |
| 9 (added on second review) | History surface | The permanent feedback history lives on the student case-detail page (`CaseShow`), not the editor, because an `Approved` case can't open the editor — see §6. |

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

**Read path (no `hasManyThrough` needed).** `case_review_comments` has no direct `case_version_id`, so `versions.reviewComments` is not a native relation — every read path goes **`CaseVersion → statusTransitions → reviewComments → author`**, three ordinary hops, each a plain relation:

- `CaseVersion::statusTransitions(): HasMany` (new — `CaseStatusTransition::where('case_version_id', ...)`, the reverse of the existing `CaseStatusTransition::caseVersion()` belongsTo)
- `CaseStatusTransition::reviewComments(): HasMany` (new)
- `CaseReviewComment::author(): BelongsTo` (new, to `User` via `created_by`)

Every eager-load in this spec uses the dotted string `'versions.statusTransitions.reviewComments.author'` — Eloquent resolves that three-hop chain without any `hasManyThrough` boilerplate. Use this exact path consistently in both the faculty and student history queries (§6, §7); do not introduce a second, differently-shaped path for one side.

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

**Overall-feedback contract, stated precisely so nothing here accidentally changes the existing approval contract:**

| Action | `reason`/summary | Section comments |
| --- | --- | --- |
| Return | `reason` **required** (unchanged) | **Required**, ≥1 array entry, and at least one `is_flagged === true` |
| Approve | `summary` stays **optional/nullable** — exactly today's behaviour, untouched | Optional, `[]` allowed, always inserted with `is_flagged = false` regardless of client input |
| Reopen | `reason` **required** (new) | None accepted — the endpoint does not take a `section_comments` field at all |

**Row-lock discipline.** `ApproveCase` and `ReturnCase` today run inside `DB::transaction()` but never lock the row first, unlike `SubmitCase`, which already does `ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first(); $case->refresh();` before re-checking status. All three review actions are extended (or, for `ReopenCase`, written from the start) to follow that same pattern: acquire the row lock inside the transaction, refresh the in-memory model, and re-check the permitted status *after* the lock is held, aborting with 409 if another request already moved the case (e.g. two faculty tabs, or a return racing an approve) — exactly the concurrency guard `SubmitCase` already relies on for the student side.

- **`ReturnCase`**: signature gains `array<int, array{section: string, body: string, is_flagged: bool}> $sectionComments`. Controller validates: `reason` required, `section_comments` required array with ≥1 entry, each entry's `section` one of the six enum values and unique within the request, `body` required, `is_flagged` boolean, and at least one entry has `is_flagged === true`. Inside one `DB::transaction()` (after the lock/status recheck above): create the `CaseStatusTransition` first, then bulk-insert the `CaseReviewComment` rows referencing its id — matching the requirement that a comment belongs to the exact transition. If any step throws (including a duplicate-section DB constraint violation), the whole transaction rolls back: neither the transition nor any comment row persists.
- **`ApproveCase`**: signature gains the same optional `$sectionComments` (default `[]`, no minimum), inserted the same way, in the same transaction as the approval's `CaseStatusTransition`/`CaseVersion` update. Every inserted row is forced `is_flagged = false` server-side regardless of what the client sends — an approved case has no next correction round for a flag to mean anything against.
- **`ReopenCase`** (new): gated by new `ClinicalCasePolicy::reopen(User $user, ClinicalCase $case): bool`, true only when the same institution/assigned-faculty check `review()` already uses, and `$case->status === CaseStatus::Approved`. No comments accepted.
- **No new policy class for comments.** They're authorized transitively through the parent action (`review`/`approve`/`returnCase`/`reopen` already gate case-level access) and read through the existing `ClinicalCasePolicy::view()` when eager-loaded as a relation. They are not an independently addressable/editable resource in this slice.

## 6. Read/visibility model

Nothing in this slice ever hides, edits or deletes a `CaseReviewComment` once written — old feedback remains permanently visible, exactly like every other immutable record in this domain.

**Two different student surfaces, on purpose — the editor cannot carry the permanent history.** An `Approved` case fails `ClinicalCasePolicy::update()` by design (editing is Draft/Returned-only), so the mobile case editor is simply unreachable once a case is approved. The permanent, read-only feedback history therefore cannot live in the editor alone — it must be rendered on the student case-detail page (`CaseController::show`, `student/CaseShow.vue`), which is reachable at every status because it only requires `view()`, not `update()`.

- **Faculty** (`ReviewController::show`): eager-load `'versions.statusTransitions.reviewComments.author'` (§3's read path) for the full version-by-version history — every past Return/Approve event and its section comments, across every version, with no filtering. `statusTransitions.actor` (already loaded) supplies who acted and when. This is what "show the exact submitted snapshot and prior versions" means for the reviewer.
- **Student, case-detail page (`CaseController::show` / `student/CaseShow.vue`)**: the permanent history — same `'versions.statusTransitions.reviewComments.author'` eager load, for the student's own case only, rendered as a read-only feedback-history panel available at **every** status (Draft through Approved), not just while `Returned`.
- **Student, case editor (`CaseEditorController` / `student/CaseEditor.vue`), reachable only while `Draft`/`Returned`**: the *current correction state* only — the flagged sections from the transition that produced the case's present `Returned` status (drives the per-section highlight badges, §8), *and*, if that transition's `from_status === 'approved'` (i.e. a reopen, which carries no flags), a prominent banner reading **"Case reopened: {reason}"** so the student isn't left guessing what changed. The editor never needs the full history — that's the case-detail page's job — so it only queries the single most recent transition, not the whole chain.

All three views read the same rows through the same `institution_id`/`clinical_case_id` scoping already enforced by `BelongsToInstitution` and `ClinicalCasePolicy::view()`/`update()` — no separate comment-visibility policy is needed.

## 7. Endpoints

One new route, same controller, same pattern as `approve`/`return`:

```
POST /faculty/reviews/{case}/reopen  → ReviewController::reopen
```

`ReviewController::index()`'s query already includes `CaseStatus::Approved` in its `whereIn`, so no route/query change is needed there. `ReviewController::show()`'s eager-load list gains `'versions.statusTransitions.reviewComments.author'` (§6). `CaseController::show()` (`GET /student/cases/{case}`) gains the same eager load, for the permanent history panel. `CaseEditorController::show()` gains a narrower single-transition lookup for the current-correction-state props (§6, §8); it does not need the full history eager load.

## 8. Frontend surfaces

- **Faculty `CaseReview` page**: a section-comment composer — one flag-checkbox + body textarea per section — feeding a `section_comments` array into the existing return/approve request payloads. A "Reopen case" action, visible only when the case is `Approved`, behind a mandatory-reason prompt (no section composer shown, per §4/§5).
- **Student `CaseShow.vue`** (case-detail page, every status): a read-only feedback-history panel listing every past transition with its reason/summary and section comments, driven by the eager load added in §7. This is the *only* place a student can see this history once a case reaches `Approved`.
- **Student `CaseEditor.vue`** (Draft/Returned only): `CaseEditorController`/`ClinicalCasePresenter` computes `flaggedSections: string[]` (from the single transition that produced the current `Returned` status) and, when applicable, `reopenedReason: string|null`, passed as Inertia props. Each section's nav entry gets a highlight badge when its key is in `flaggedSections`; a reopened case shows the "Case reopened: …" banner at the top of the editor.

## 9. Testing plan

- **Authorization:** only the assigned faculty member can return/approve/reopen; cross-institution denial on all three; wrong-role denial (student/admin cannot call any of the three); `reopen` denied when case is not `Approved`.
- **Concurrency:** a second return/approve/reopen attempt on a case already moved out of its permitted status by a concurrent request (simulated by changing status between the authorization check and the lock, per `SubmitCase`'s existing test pattern) is rejected with 409, not silently applied — proving the §5 row-lock/recheck actually guards all three actions, not just `SubmitCase`.
- **Validation:** return without any flagged section comment → 422; return with a duplicate `section` key in the request → 422; return/approve with an invalid section value → 422; the DB unique constraint as a backstop (attempt a raw duplicate insert in a unit test and confirm it fails).
- **Atomicity:** force a failure partway through `ReturnCase`/`ApproveCase` (e.g. a raw duplicate-section insert past validation, or an injected exception before the comment insert) and assert **neither** the `CaseStatusTransition` row **nor** any `CaseReviewComment` row persists afterward — the whole action is one transaction, so a partial write is never left behind for either action.
- **Immutability:** after reopen → correction → resubmit → approve, assert the original approved `CaseVersion` row (snapshot, hash, `approved_by`, `approved_at`) is byte-identical to before, and a new `CaseVersion` row exists with the next `version_number`.
- **Status-machine round trips:** submit → return (with a flagged section + reason) → resubmit → approve; approve → reopen (reason only) → correct → resubmit → approve again — both passing end to end per the master plan's exit evidence.
- **History correctness:** after two full round trips (a return round and a reopen round on the same case), assert each `CaseReviewComment` is retrieved under its own correct `CaseVersion`/`CaseStatusTransition` — not merged, not attributed to the wrong round — in both the faculty review page's data and the student `CaseShow` history panel.
- **Visibility:** faculty review page shows every past version's comments; student `CaseShow` shows full historical feedback at every status, including after the case reaches `Approved`; student `CaseEditor` shows current flagged sections only while `Returned` from an ordinary return, and shows the reopened banner (not flags) when `from_status = approved`; the editor route itself 403s/redirects on an `Approved` case (unchanged existing behaviour), confirming the history panel is genuinely the only path once approved.

## 10. Files touched (for the implementation plan to size, not exhaustive)

New: migration for `case_review_comments`; `CaseReviewComment` model (with `author()`); `CaseVersion::statusTransitions()` and `CaseStatusTransition::reviewComments()` relations; `CaseReviewSection` enum; `ReopenCase` action; `ReviewController::reopen`; route; frontend composer/badge/banner/history-panel components.
Extended: `ReturnCase`, `ApproveCase` (comments param, row-lock/recheck), `ClinicalCasePolicy` (`reopen`), `ReviewController::show`/`index` eager-loads, `CaseController::show` eager-load (student `CaseShow`), `CaseEditorController`/`ClinicalCasePresenter` (flagged-sections/reopened-reason props), student and faculty Inertia pages.

## Self-review

- **Placeholders:** none — every section states a concrete mechanism, not a TBD.
- **Internal consistency:** §3's removal of a per-comment `case_version_id` is consistent with §4's requirement that `ReturnCase`/`ReopenCase` set `case_version_id` on the transition, and with §3/§6's shared `versions.statusTransitions.reviewComments.author` read path (defined once, reused everywhere, not redefined differently per screen); §5's row-lock/recheck rule applies identically to all three actions, not just the new one; §6's editor-vs-CaseShow split follows directly from `ClinicalCasePolicy::update()`'s existing Draft/Returned-only gate, not a new rule invented for this slice; §9's tests cover every decision in §2 including the two added on this review pass.
- **Scope:** matches the master plan's Slice 4 bullets and exit evidence exactly; §1 restates the exclusion list so a later agent can't read silence as permission to add rubrics/marks; §5's overall-feedback contract table makes explicit that `ApproveCase`'s existing optional-summary behaviour is untouched, not silently tightened.
- **Ambiguity:** "one comment per section per review event" is DB-enforced, not just a UI convention; "prominently" for the reopen banner is concrete (a page-level banner reading the literal reopen reason); "permanent" history is concrete (lives on `CaseShow`, reachable at every status, not just Draft/Returned like the editor).
