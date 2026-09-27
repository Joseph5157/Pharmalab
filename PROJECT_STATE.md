# Pharmalab Project State

This file is the canonical source of truth for current project status, accepted decisions, active work, and the next milestone. Detailed specifications remain under `docs/`.

> Read `PROJECT_STATE.md` completely before taking any action.  
> Treat it as the authoritative project status and decision record.  
> Verify the repository state against the baseline recorded here.  
> Work only on the active milestone.

## 1. Current position

| Item                                             | Current value                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Repository                                       | `Joseph5157/Pharmalab`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| Accepted baseline branch                         | `main`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| Accepted feature baseline                        | `c167e9e` — accepted `WALKING-SKELETON-01`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Repository HEAD before this documentation change | `9efd69d` — merge of PR #14, `DIRECT-DOCUMENTATION-IMPL-01` Slice 3                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| Last completed and accepted gate                 | Walking skeleton — `WALKING-SKELETON-01`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| Active gate                                      | `DIRECT-DOCUMENTATION-IMPL-01`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Current milestone                                | Direct clinical/practical documentation and faculty review                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Milestone status                                 | Slice 1 (domain and schema reconciliation) accepted and merged via PR #8; Slice 2A (sync engine + Case Profile/History & Diagnosis sections) accepted and merged via PR #10; Slice 2B (Vitals, Investigations, Medication Chart) accepted and merged via PR #12; Slice 2C (SOAP integration, conditional clinical activities, full six-section editor) accepted and merged via PR #13 — the mobile case editor (master plan Slice 2) is now complete; Slice 3 (submission and completeness) accepted and merged via PR #14 — the submission/review workflow is now on `main` |
| Exact next action                                | Plan and implement Slice 4 (faculty review, correction and reopening) per the implementation plan's Slice 4 definition, beginning with the review-policy, comments/corrections and rubric decisions that slice depends on                                                                                                                                                                                                                                                                                                                                                    |
| Next gate after this milestone                   | Slice 4 — faculty review, correction and reopening                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |

## 2. Completed and accepted work

### Build Gate 1 — Foundation

- **Status:** Accepted and closed
- **Commit:** `84c3320`
- **Delivered:** Laravel, Inertia.js, Vue 3, TypeScript, PostgreSQL and Redis foundation; authentication and password reset; institution scoping; student, faculty/preceptor and institution-administrator roles; role dashboards; Laravel authorization policies; responsive navigation; CI; automated tests; local-only demo users and seed data; local setup documentation.
- **Verification:** Clean-install workflow, authentication, role-route isolation, institution isolation, responsive/mobile navigation, automated application tests, frontend checks, static analysis, formatting and production build were verified before acceptance.
- **Important limitation:** This baseline does not contain the academic administration workflow, clinical case workflow, review workflow, or production offline support.

### Build Gate 2 — `SYNC-SPIKE-01`

- **Status:** Accepted and closed
- **Implementation commit:** `a532d87`
- **Accepted merge commit:** `4306c88`
- **Pull request:** GitHub PR #1
- **Objective:** Prove that a student can edit one de-identified case-note section during unreliable connectivity without silently losing or overwriting data.
- **Scope:** One experimental `Case Draft Note`; this is not the complete case module or clinical schema.
- **Implemented:** IndexedDB draft/outbox, stable `client_operation_id`, idempotent synchronization, server `lock_version`, optimistic concurrency checks, manual retry, foreground reconnect handling, persistent save states, mobile section-level conflict resolution, audit events, logout draft clearing, and owner/institution authorization.
- **PR verification:** GitHub CI passed after aligning the declared/runtime PHP version with the PHP 8.4-compatible lockfile and correcting formatter/static-analysis findings.
- **Merged-main verification:** 54 application tests run, 52 passed, 2 skipped, with 205 assertions. PHPStan, Pint, Composer validation, frontend checks, TypeScript checks, production build, and `git diff --check` passed.
- **Browser verification:** Headless Chromium at 390 × 844 passed online autosave, network failure, IndexedDB persistence, offline refresh recovery after reconnect, foreground synchronization, stale-version conflict handling, mobile conflict choices, and logout clearing.
- **Findings:** See [`docs/SYNC_SPIKE_01_FINDINGS.md`](docs/SYNC_SPIKE_01_FINDINGS.md).

#### Accepted limitations

- A cold load or refresh while fully offline shows the browser network error because clinical-page service-worker caching is deferred. The IndexedDB draft survives and is recovered after reconnection and reopening.
- Browser storage may be evicted; permanent offline storage is not guaranteed.
- Background Sync, automatic field-level merging, faculty review, submission, and full clinical forms are not included.
- Archived device copies are proven technically, but a complete draft-management screen and the final retention policy remain open.

### Build Gate 3 — `ADM-FOUNDATION-01`

- **Status:** Accepted and closed
- **Implementation commit:** `3991f2c`
- **Accepted merge commit:** `ceacdf7`
- **Pull request:** GitHub PR #2
- **Objective:** Provide only the academic structure and assignments required to support the next walking-skeleton gate.
- **In scope:** Programmes and cohorts; clinical sites, departments and wards; student and faculty accounts; rotation creation; student/preceptor assignment; assignment authorization; audit events.
- **Required quality:** Institution-scoped queries and policies, server-side validation, negative authorization tests, responsive administration flows, and auditable configuration changes.
- **Explicitly excluded:** Full template builder, advanced reports, final clinical schema, clinical forms, faculty case review, submission workflow, and later PWA features.
- **PR verification:** Code review confirmed 3-layer authorization isolation (policies, scoped `exists` validation, model global scopes), proper compound unique constraints, `restrictOnDelete` foreign keys, and negative authorization test coverage.
- **Merged-main verification:** 63 application tests run, 61 passed, 2 skipped, with 297 assertions. PHPStan, Pint, Composer validation, frontend formatting/lint, Vue TypeScript checks, production build, and `git diff --check` passed.
- **Browser verification:** Headless Chromium at 390 × 844 and 1280 × 900 proved an administrator can create the full academic/site structure, create student and faculty accounts, create a rotation and assign the student/preceptor without database commands or seed files.
- **Implementation record:** See [`docs/ADM_FOUNDATION_01_IMPLEMENTATION.md`](docs/ADM_FOUNDATION_01_IMPLEMENTATION.md).
- **Scope reconciliation:** `docs/DECISIONS.md` records that CSV import, assignment notifications, overlap scheduling, template/rubric references, reports and clinical workflow remain deferred from this bounded gate.

#### Accepted limitations

- CSV import, assignment notifications, overlap scheduling, template/rubric references, reports and clinical workflow are deferred.
- Walking skeleton uses only a basic review summary/attestation and approval decision (no multi-reviewer approval).
- Programme-specific case quotas are not enforced; case status is shown without quota calculations.

### Build Gate 4 — `WALKING-SKELETON-01`

- **Status:** Accepted and closed
- **Implementation commit:** `7422388`
- **Accepted merge commit:** `c167e9e`
- **Pull request:** GitHub PR #3
- **Objective:** Deliver the admin → student case/SOAP submission → faculty approval → portfolio loop end-to-end.
- **Delivered:** Minimal de-identified clinical cases and SOAP notes; draft, submitted, under-review, returned and approved statuses; immutable case versions and status transitions; student submission and portfolio flows; faculty review, return and approval flows; institution-scoped policies and controllers; and responsive Vue pages.
- **Verification:** 73 tests run: 71 passed, 2 skipped, 322 assertions. PHPStan, Pint and Vue TypeScript checks were clean; production build succeeded. Authorization coverage includes role isolation, cross-institution access and ownership; workflow coverage includes create → SOAP → submit → approve and return → resubmit.
- **Exit condition:** Satisfied: a student can create a case, fill a SOAP note, submit it; an assigned faculty member can review and approve it; and the approved case appears in the student's portfolio.

#### Accepted limitations

- This remains a minimal clinical record, not the faculty-approved complete clinical documentation schema.
- Review has no rubric, comments/corrections UI, multi-reviewer approval, exceptional reopen path or reports.
- A minimal return/resubmission path was included and tested; its full policy (including flagged-section editing) remains deferred to the complete review workflow.

## 3. Active work

### `DIRECT-DOCUMENTATION-IMPL-01` — direct workflow implementation

- **Status:** Active; Slice 1 (domain and schema reconciliation) accepted and merged via PR #8. Slice 2A (sync engine + first two mobile case-editor sections) accepted and merged via PR #10. Slice 2B (Vitals, Investigations, Medication Chart) accepted and merged via PR #12. Slice 2C (SOAP integration, conditional clinical activities, full six-section editor) accepted and merged via PR #13 — master plan Slice 2 (mobile case editor) is complete. Slice 3 (submission and completeness) accepted and merged via PR #14 — the submission/review workflow is now on `main`; Slice 4 (faculty review, correction and reopening) is the next slice.
- **Objective:** Extend the accepted walking skeleton with one faculty-approved fixed clinical or practical record structure and the complete direct documentation, return, resubmission and approval experience.
- **Depends on:** `WALKING-SKELETON-01` (accepted), the accepted sync protocol and faculty approval of the first fixed record structure.
- **Exit condition:** One authorized student can start a fixed record, save, submit, receive feedback, correct, resubmit and obtain approval from the assigned faculty member while immutable history, privacy, sync and tenant authorization tests pass.
- **Scope boundary:** Do not add curriculum versions, subjects, academic periods, regulatory mappings, curriculum activity requirements, automatic template resolution or a dynamic template builder. Drug databases, AI, public APIs and native applications remain deferred.
- **Institutional boundary:** Product/faculty decisions for the first Pharm.D form are accepted. Hospital/privacy validation, backup/restore procedure and rotation-end draft policy remain required before production pilot.
- **Detailed direction:** See [`docs/decisions/2026-09-24_DIRECT_DOCUMENTATION_PHASE1_DIRECTION.md`](docs/decisions/2026-09-24_DIRECT_DOCUMENTATION_PHASE1_DIRECTION.md) and [`docs/research/DIRECT_DOCUMENTATION_WORKFLOW_SPEC.md`](docs/research/DIRECT_DOCUMENTATION_WORKFLOW_SPEC.md).

#### Slice 1 — Domain and compatibility (accepted and merged)

- **Pull request:** GitHub PR #8
- **Merge commit:** `ba58646` (fast-forward merge to `main`)
- **Plan:** [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md`](docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-1.md), including its execution ledger recording every deviation from the plan as a ruling.
- **Delivered:** Pharm.D baseline fields and a server-fixed, non-mass-assignable `form_version` on `clinical_cases`/`soap_notes`; five new detail tables (`case_clinical_profiles`, `case_vitals`, `case_investigations`, `case_medications`, `case_clinical_activities`) with matching Eloquent models, authorization policies (student/assigned-faculty/same-institution-administrator view, student-only edit while draft or returned) and `FormRequest` validation; `CaseCompletenessService` (section-presence checks only). No new routes, controllers or frontend changes — only the existing `POST /student/cases` route was extended.
- **Verification:** 131 tests, 129 passed, 2 skipped, 453 assertions. PHPStan 0 errors. Pint clean. Frontend checks/build unchanged (no frontend touched). GitHub CI passed on PR #8 (run #52) and again on merged `main`. Cross-institution/faculty authorization and the "no eager child rows" rule verified by mutation testing (temporarily removing the tenancy trait and neutering policy checks, confirming tests catch it). A legacy walking-skeleton `clinical_cases` row was proven to migrate forward and back cleanly.
- **Pre-merge blockers fixed before merge:** same-institution administrators can view (not edit) new clinical detail records, cross-institution administrators remain denied; a missing/institution-mismatched parent case or rotation assignment now denies safely instead of returning a 500; `form_version` was removed from `ClinicalCase`'s mass-assignable fields entirely (set via `forceFill` only).
- **Scope decisions recorded on merge (owning slice assigned, not yet implemented):**
    - `CaseCompletenessService`'s presence checks are incomplete against the full field catalogue (e.g. omit `information_source`, `chief_complaints`; a vital with neither `value_numeric` nor `value_text` still counts as present) — **belongs to Slice 3**, alongside the full submission-completeness validation policy that slice already owns.
    - No schema field yet for "vitals unavailable" / "investigations unavailable" / "no current medicines" (explicit-absence) answers — **belongs to Slice 2, before that slice's form UI implementation.** **Delivered in Slice 2A Task 1** (see below).
- **Deferred minors (no owning slice assigned):** policy logic duplicated across 7 classes (`SoapNotePolicy`, `CaseVersionPolicy` and the five new Slice 1 policies); some validation rules looser than the field catalogue's enumerated values; `ClinicalCase`'s docblock not updated for the new columns.

#### Slice 2A — Sync engine and first two mobile case-editor sections (accepted and merged)

- **Pull request:** GitHub PR #10
- **Merge commit:** `2998159` (merge commit to `main`)
- **Plan:** [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2a.md`](docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2a.md), including its execution ledger recording every deviation from the plan as a ruling.
- **Delivered:** Explicit-absence schema fields and independent per-section lock columns (closing Slice 1's deferred item, above); a reusable offline-sync engine generalized from the accepted `SYNC-SPIKE-01` protocol (`Syncable` contract, `SectionSyncService`, `outboxStore.ts`, `useSectionSync.ts`); the first two real mobile case-editor sections — Case Profile (generated Case ID, derived rotation/site/ward, unit-dependent age validation) and History & Diagnosis (full field set, allergy-field clearing on status change, de-identification warnings) — with partial field-level autosave, IndexedDB-backed offline queueing, and three-way conflict resolution (use server version / keep local draft as a copy / replace server version); the mobile section-based case editor shell.
- **Verification:** 188 tests, 186 passed, 2 skipped (final head, after every correction below). PHPStan 0 errors. Pint clean. `npm run check`, `npm run types:check`, and the production build clean. `git diff --check` clean. GitHub CI passed on every push to PR #10 and again on merged `main`. Manual device verification at phone/tablet/desktop viewports, plus live browser re-verification against real HTTP requests and database state (not just UI), covered every correction below.
- **Correction rounds before merge (all fixed and re-verified live; none deferred):**
    - Task 8 manual device verification found three `useSectionSync.ts` engine bugs invisible to PHPUnit (which posts curated payloads directly to controllers, never through the real fetch call): a Vue-reactive Proxy breaking `structuredClone()` in two places (blanking Case Profile on load; stalling every edit at "Unsynced changes"), and unknown read-only fields leaking into outgoing sync bodies, causing spurious 422s on every real edit.
    - Two rounds of whole-branch review found a generic "Sync failed" UX gap (fixed to surface real validation messages) and two age value/unit pairing gaps (a partial update validated only the field sent, ignoring the case's stored value of the omitted field; the pair could also be split entirely, orphaning one side in the database).
    - An independent fresh `/code-review` pass (no context from this branch's own history) found five further issues: the new age-pair validation firing as a false-positive mid-entry, before the student finished picking a unit; `CaseEditorController::show()` authorizing `view` instead of `update`, letting a student silently open and edit a Submitted/UnderReview/Approved case into a stuck offline outbox; the same orphan-pairing gap on allergy substance/status, in both directions; numeric fields left as `''` instead of `null` client-side after clearing (a type-safety gap, not a live data bug — Laravel's own middleware already normalized it server-side); and "no known past medical history" disagreeing with free-text history when only one side was edited.
    - A final human re-review of the merged diff found two more `useSectionSync.ts` defects: a normal successful save never adopted the server's normalized response section (only conflict-resolution paths did), so a server-side field clear — e.g. allergy substance/reaction on a status change — could resurface stale on the next edit; and a second edit made while an earlier request was still in flight could be silently stranded unsynced whenever that earlier request resolved with a conflict, a validation error, or a network failure rather than success (the success path already rescheduled it; the others did not).
- **Deferred minors (no owning slice assigned):**
    - Dropping `NOT NULL` on `sync_operations`'s legacy foreign key without adding a `CHECK` constraint requiring one of the two references (polymorphic or legacy) to be present — no current code path can insert a fully orphaned row, so this is defense-in-depth for a bug that doesn't exist yet, not a live gap.
    - Desktop's `max-w-2xl` case-editor section container reads narrow on a wide viewport — anticipated by the plan itself as a Slice 2C polish item, not a regression.

#### Slice 2B — Vitals, Investigations, Medication Chart (accepted and merged)

- **Pull request:** GitHub PR #12
- **Merge commit:** `4645937` (merge commit to `main`)
- **Plan:** [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2b.md`](docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2b.md), including its execution ledger recording every deviation from the plan as a ruling.
- **Delivered:** Per-row `lock_version`, BP/SpO2 fields, not-stated flags, and `Syncable` for `CaseVital`/`CaseInvestigation`/`CaseMedication`; `SectionSyncService::create()` (idempotent-by-`client_operation_id` row creation, extending `sync()`'s replay-safety) plus the Vitals, Investigations, and Medication Chart backends (BP pairing, unit/reference-range not-stated flags, `indication_unclear`, status auto-sync on record/clear); offline-capable row creation (IndexedDB outbox, optimistic pending rows, reconnect replay) and per-row three-way conflict resolution for all three sections, reusing `useSectionSync` unchanged for edits; authorization/institution-isolation tests; a mobile fix for the case editor's Previous/Next bar being fully obscured by the global bottom nav below the `md` breakpoint.
- **Verification:** 271 tests passed, 2 skipped (final head). PHPStan 0 errors. Pint clean. `npm run check`, `npm run types:check`, and the production build clean. `git diff --check` clean. GitHub CI passed on every push to PR #12 and again on merged `main`. Manual device verification at phone/tablet/desktop viewports (local Docker), plus live verification on an isolated `pharmalab-staging` Railway project (never production) — bare-add/edit/delete/availability-toggle for all three row types, the mandatory stale-client conflict test, offline row creation and reconnect replay, and every correction below.
- **Correction rounds before merge (all fixed and re-verified; none deferred):**
    - A fresh whole-branch `/code-review high` found 10 candidates; triaged against current source rather than trusted as-is (one dismissed as explicitly out of scope per the plan's own text — submission completeness is Slice 3's job, not this slice's; one downgraded after confirming it isn't reachable through the real frontend, only a raw API). Fixed: a missing BP systolic/diastolic pairing check on update (present on create); medication-chart "none documented" missing its required-reason rule; medication `status` accepting an explicit null against its NOT-NULL column; `SectionSyncService::create()`'s idempotent-replay path never checking the replayed row belongs to the current case; create-time availability-lock-version bumps missing the row lock `sync()`/`delete()` already take. Two further issues were reproduced live and fixed with `useSectionSync`'s existing error-surfacing convention: availability-toggle 422s silently hiding already-recorded rows with no visible error, and offline row-creation 422/409s retrying forever with no visible error.
    - A targeted `/code-review medium` of just that hardening commit found one more real regression it had itself introduced: a retry of a previously-failed offline row-creation draft could strand the row with no visible error if the retry then failed for a different (transient) reason.
    - A human PR review caught a real, live-reachable gap this session's own test-writing had noticed but wrongly treated as an accepted shared limitation: Laravel's `sometimes` rule skips _all_ rules for a key — including implicit ones like `required_if` — when that key is entirely absent from the request, not merely sent as null. This let a raw client bypass the "reason required" rule on all three availability sections (medication, vitals, investigations) simply by omitting the reason key. Fixed across all three by dropping `sometimes` from each reason field's rules.
- **Deferred follow-ups (no owning slice assigned):**
    - `resources/js/pages/student/Cases.vue`'s "New case" button submits an empty body with no `rotation_assignment_id` against `StoreClinicalCaseRequest`, which requires it — silently blocks a student from ever creating a second case through the UI. Pre-existing, unrelated to this slice's repeatable-row work; not a blocker for Slice 2B.
    - `useRepeatableRowCreate`'s IndexedDB-outboxed local drafts (pending or permanently-failed offline row creates) are never rehydrated into a section's rows array on page load — only on a live `online` reconnect event within the same browser session. A reload before a failed/pending create resolves makes that draft invisible until some future code path also loads pending outbox entries on mount. Pre-dates this branch (Task 5's original design); not a blocker for Slice 2B.

#### Slice 2C — SOAP integration, conditional clinical activities, full six-section editor (accepted and merged)

- **Pull request:** GitHub PR #13
- **Merge commit:** `92b07325` (merge commit to `main`)
- **Plan:** [`docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2c.md`](docs/superpowers/plans/2026-09-25-direct-documentation-impl-01-slice-2c.md), including its execution ledger recording every deviation from the plan as a ruling.
- **Delivered:** SOAP note migrated onto the shared sync engine (`Syncable`, monitoring-plan fields including a real `monitoring_plan_not_applicable` boolean column) via `PUT /student/cases/{case}/soap`; `lock_version` and `Syncable` for `CaseClinicalActivity`; a concurrency-safe ADR/Counselling singleton backend (partial unique DB index, parent-row `lockForUpdate()`, JSON detail merge-not-replace with clear-on-state-exit) at `PUT /student/cases/{case}/clinical-activities/{adr|counselling}`; repeatable Pharmacist Intervention / Monitoring Follow-up rows reusing Slice 2B's offline-capable-creation pattern (`POST`/`PUT`/`DELETE /student/cases/{case}/clinical-activities[/{activity}]`); the standalone SOAP page and the SYNC-SPIKE-01 `CaseDraftNote` experiment both atomically retired once their functionality was fully covered by the shared engine; all six mobile case-editor sections (Case Profile, History & Diagnosis, Vitals & Investigations, Medication Chart, SOAP, Conditional Clinical Activities) now wired live into `CaseEditor.vue` — **the mobile case editor (master plan Slice 2) is complete.**
- **Verification:** 318 tests, 316 passed, 2 skipped (final head, after the post-merge audit-trail fix below). PHPStan 0 errors. Pint clean. `npm run check`, `npm run types:check`, and the production build clean. `git diff --check` clean. GitHub CI passed (`ci` check, 1m1s) before merge. Manual device verification on an isolated `pharmalab-staging` Railway project (phone/tablet/desktop) covered the full six-section walkthrough, offline row creation and reconnect replay, a genuine simulated two-client ADR conflict with all three resolution choices, the old `/soap` GET route confirmed 405, and logout clearing the `pharmalab-section-outbox` IndexedDB database.
- **Correction rounds before merge (all fixed and re-verified; none deferred):** live device verification found and fixed four defects — the SOAP "Not applicable" monitoring checkbox never persisting the unchecked branch (root-caused to Laravel's `ConvertEmptyStringsToNull` middleware, fixed with a real boolean column instead of reason-nullability); `CaseClinicalActivityController`'s ADR/Counselling/generic `sync()` methods returning `{"activity": ...}` while the shared `useSectionSync` composable hardcodes `body.section`, silently showing "Sync failed" on every successful edit since Task 3; the same `readonlyFields` gap Task 7 fixed for `ActivityRow.vue` also missing from `ConditionalClinicalActivitiesSection.vue`'s ADR/Counselling edit payloads; and ADR/Counselling having no conflict-resolution UI at all. A post-merge code-review pass then found and fixed a fifth, subtler defect (commit `a8e437b`): the SOAP sync endpoint double-recorded its audit trail (`soap_note.updated`) whenever a client replayed an already-applied `client_operation_id` (e.g. after a lost response or offline-queue retry), fixed by threading an explicit `replayed: bool` through `SectionSyncService::sync()`'s return value and gating the audit call on it, with two new regression tests.
- **Deferred follow-ups (no owning slice assigned):**
    - `resources/js/pages/student/Cases.vue`'s "New case" button still submits an empty body missing `rotation_assignment_id` (pre-existing, first noted in Slice 2B) — remains unfixed, not a blocker for Slice 2C.
    - Pending/failed offline-create drafts for repeatable rows (Intervention/Monitoring) still don't rehydrate their visible pending/failed state after a page reload (data itself isn't lost, only the UI indicator) — same pre-existing `useRepeatableRowCreate` limitation noted in Slice 2B, not a blocker for Slice 2C.

#### Slice 3 — Submission and completeness (accepted and merged)

- **Pull request:** GitHub PR #14
- **Merge commit:** `9efd69d` (merge commit to `main`)
- **Plan:** [`docs/superpowers/plans/2026-09-27-direct-documentation-impl-01-slice-3.md`](docs/superpowers/plans/2026-09-27-direct-documentation-impl-01-slice-3.md), including its field-by-field completeness matrix.
- **Delivered:** `CaseCompletenessService` rebuilt against the field-by-field completeness matrix and enforced by `SubmitCase` at submission time only (drafts still save freely), with row-level vital/investigation/medication checks, mandatory Suspected ADR and Patient counselling answers, and a structured drug-related-problem → pharmacist-intervention trigger; the corrected submit/review authorization contract (idempotent retry while `Submitted`; `UnderReview`/`Approved` denied) with a narrower `reviewForSubmission` gate; the read-only submission-review endpoint and Inertia screen (`GET student/cases/{case}/submission-review`) with section deep-linking, de-identification attestation, offline awareness, and visible server validation/422 errors; an extracted shared `ClinicalCasePresenter` reused by the editor and the review screen; a complete, `form_version`-tagged immutable snapshot that records medication history and the medication chart separately from the real `medication_context` model and orders every repeatable collection deterministically (`created_at`, `id`); and submission-integrity hardening that blocks submitting while the same case has unsynced, pending or permanently-failed IndexedDB outbox work — including ambiguous legacy `caseId`-less drafts, with a specific message — without ever guessing ownership or deleting drafts. New cross-institution, wrong-role and wrong-status authorization tests cover both new routes, alongside a focused frontend outbox-classification spec.
- **Verification:** 357 tests, 355 passed, 2 skipped, 1161 assertions. PHPStan 0 errors. Pint clean. `npm run check`, `npm run types:check` and the production build clean. `git diff --check` clean. GitHub CI passed (`ci`, 1m11s) before merge and again on merged `main`. The exact head `6455917` was deployed to the isolated `pharmalab-staging` Railway project (never production) as deployment `4faa50b2-572c-4264-ac97-3b89556e0e8b` (SUCCESS; `GET /up` → 200), and a focused live headless-Chromium walkthrough passed 5/5: a complete case submitted through Review & submit → attestation → `Submitted`; the current-case unsynced outbox warning blocked submit; the legacy `caseId`-less outbox warning blocked submit with its specific message; a different-case outbox draft did not block; and the assigned faculty saw and opened the submitted case through the real `/faculty/reviews` UI. The `npm run check` `PROJECT_STATE.md` markdown-formatting failure that would have blocked CI was fixed before the PR.
- **Deferred follow-ups (no owning slice assigned):** the two pre-existing items carried over unchanged from Slices 2B/2C — `resources/js/pages/student/Cases.vue`'s "New case" button submits an empty body missing `rotation_assignment_id`, and `useRepeatableRowCreate`'s pending/failed offline-create drafts do not rehydrate their visible pending/failed state after a page reload (data is not lost, only the UI indicator).

## 4. Accepted decisions and reasons

| Decision                                               | Status   | Reason                                                                                                                                                                            |
| ------------------------------------------------------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel + Inertia.js + Vue 3 + TypeScript + PostgreSQL | Accepted | Provides a modern interface with cohesive server-side authorization and a relational domain model.                                                                                |
| Modular monolith                                       | Accepted | Phase 1 does not need external API or microservice complexity.                                                                                                                    |
| Server-side policies and institution-scoped queries    | Accepted | Client capability flags are presentation helpers, not security controls.                                                                                                          |
| De-identification by design                            | Accepted | The educational record must not collect direct patient identifiers in standard forms.                                                                                             |
| Immutable submitted and approved versions              | Accepted | Faculty decisions must remain tied to the exact work reviewed.                                                                                                                    |
| Early sync spike before clinical forms                 | Accepted | Autosave, retry, idempotency and concurrency affect every later case section.                                                                                                     |
| Offline support limited to de-identified drafts        | Accepted | The server remains authoritative; submission, return and approval require a current online state.                                                                                 |
| Foreground reconnect and manual retry are required     | Accepted | Browser Background Sync is not reliable enough to be the only recovery mechanism.                                                                                                 |
| Section-level conflict handling                        | Accepted | The spike proved it prevents silent overwrite without automatic field/text merging. Reuse the protocol through a shared sync service; final device-retention policy remains open. |
| AI-generated clinical recommendations                  | Deferred | Complete the governed normal workflow and collect suitable data before evaluating AI.                                                                                             |
| Comprehensive drug monographs and DDI checking         | Deferred | Licensing, content governance and update processes require separate work.                                                                                                         |
| Phase 1 student groups are B.Pharm and Pharm.D         | Accepted | The direct workflow may serve both groups through separately approved fixed record structures; no curriculum package is stored.                                                   |
| Direct assignment and documentation workflow           | Accepted | Phase 1 centers on authorized record start, documentation, submission, faculty return/resubmission and approval.                                                                  |
| No curriculum data model in Phase 1                    | Accepted | The client did not request curriculum versions, periods, subjects, regulatory mappings or automatic curriculum-to-form assignment.                                                |
| Fixed approved forms before a dynamic builder          | Accepted | Start with faculty-approved structures and stable form-version identifiers; defer the general template builder.                                                                   |
| Pharm.D fixed clinical form baseline                   | Accepted | Required sections, conditional activities, India-first units and server-authoritative validations are approved for implementation.                                                |
| Review, correction and reopening                       | Accepted | Assigned faculty reviews; returned cases unlock all sections with flagged highlights; section comments are supported; audited faculty reopening is exceptional.                   |
| Phase 1 assessment                                     | Accepted | No marks, rubric, pass score, second reviewer or field-level annotation in the pilot.                                                                                             |
| Targets, reports and retention                         | Accepted | Faculty-set rotation targets, role-scoped progress, de-identified PDF and retention through course completion plus one year are approved.                                         |
| Pilot boundary                                         | Accepted | English; Android Chrome phone/tablet and desktop Chrome/Edge; no student attachments.                                                                                             |
| B.Pharm delivery sequence                              | Accepted | B.Pharm practical records follow as a separate next gate after the Pharm.D clinical module.                                                                                       |
| Gate-level inspiration research                        | Accepted | Internet products and Mobbin may inform workflow and UX, but remain subordinate to privacy, faculty approval and the client-requested scope.                                      |

The full durable decision register is maintained in [`docs/DECISIONS.md`](docs/DECISIONS.md). If this summary and that file disagree, stop and reconcile the inconsistency before implementation.

## 5. Open decisions

The first Pharm.D form no longer has unresolved product-field, reviewer, correction, reporting, attachment, retention, language or device-scope decisions. The following items remain before production pilot and must not be silently hard-coded.

| Question                                                              | Why it matters                                                    | Owner                      | Current boundary                                                                    |
| --------------------------------------------------------------------- | ----------------------------------------------------------------- | -------------------------- | ----------------------------------------------------------------------------------- |
| What happens to unfinished drafts when a rotation ends?               | Determines grace period, read-only locking or explicit extension. | Faculty/product owner      | Do not add automatic rotation-end locking in the first implementation slice.        |
| Has the hospital/institution approved the de-identification boundary? | Confirms permitted case-date precision and local governance.      | Privacy owner and hospital | Implement the approved no-direct-identifier baseline; obtain sign-off before pilot. |
| What backup/restore procedure and operational owner apply?            | Required before retaining pilot educational cases.                | Institution IT/admin       | Rehearse backup/restore before pilot; do not implement unsafe automatic deletion.   |
| What final PDF branding/layout is approved?                           | Affects external academic record presentation.                    | Institution admin/faculty  | Generate only de-identified content; finalize branding before pilot release.        |

## 6. Changes and superseded decisions

### Sync work moved earlier

- **Previous:** Offline synchronization was planned after the clinical forms and walking skeleton as part of the PWA-resilience phase.
- **New:** A small experimental sync spike is performed immediately after the foundation and before the walking skeleton.
- **Reason:** Autosave, retry, idempotency and concurrency choices affect the architecture of every clinical form.
- **Status:** Previous sequencing superseded. Full PWA resilience remains a later gate.

### Broad first iteration narrowed

- **Previous:** The first iteration combined foundation, minimal academic administration and the complete walking skeleton.
- **New:** Foundation was closed independently; the sync spike follows; academic administration and the walking skeleton are separate subsequent gates.
- **Reason:** Each architectural risk should be proven and accepted before expanding the domain workflow.
- **Status:** Previous combined sequencing superseded.

### Walking skeleton accepted

- **Previous:** `WALKING-SKELETON-01` was recorded as not started from the `ceacdf7` academic-administration baseline.
- **New:** It was implemented on `walking-skeleton-01`, verified, merged through GitHub PR #3, and accepted at `c167e9e`.
- **Reason:** The end-to-end minimal case submission, faculty approval and portfolio exit condition is now evidenced.
- **Status:** The accepted feature baseline remains `c167e9e`; the next-gate direction recorded at closure has since been superseded by the PCI curriculum and template-foundation decisions below.

### Curriculum/template direction superseded

- **Previous:** Phase 1 planned a PCI curriculum data model, curriculum periods, subject/activity requirements, automatic template assignment and a reusable dynamic template engine.
- **New:** The client confirmed that curriculum-based activities and curriculum data are not required in Phase 1. No new curriculum tables or automatic curriculum resolution will be implemented.
- **Reason:** The requested value is the direct documentation and faculty-review workflow, not curriculum administration.
- **Status:** The 22 September curriculum/template direction and the draft PCI template specification are superseded. Existing accepted programme, cohort and rotation tables remain untouched.

### Direct documentation direction accepted

- **Previous:** The next gate was a broad curriculum/template foundation.
- **New:** The next gate is `DIRECT-DOCUMENTATION-IMPL-01`, using one faculty-approved fixed record structure and the direct submit, return, resubmit and approve lifecycle.
- **Reason:** This is the smallest client-aligned extension of the accepted walking skeleton.
- **Status:** Specification accepted through PR #6; implementation begins only after the first field set and assignment-start rule are confirmed.

## 7. Remaining gates

| Order | Gate                                 | Status                                                                                                                                                                | Exit condition                                                                                                                                                                  |
| ----: | ------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|     1 | Academic/rotation administration     | Accepted — `ADM-FOUNDATION-01`                                                                                                                                        | Admin can create the minimum programme, cohort, site, department, ward and rotation structure and assign one student and faculty member, with authorization and audit coverage. |
|     2 | Walking skeleton                     | Accepted — `WALKING-SKELETON-01`                                                                                                                                      | The admin → student case/SOAP submission → faculty approval → portfolio loop passes end-to-end.                                                                                 |
|     3 | Direct documentation scope           | Accepted through PR #6                                                                                                                                                | The client-aligned fixed-form workflow, data exclusions, screens and acceptance criteria are recorded.                                                                          |
|     4 | Direct documentation implementation  | Active — Slices 1/2A/2B/2C/3 accepted and merged (mobile editor and submission/review complete); Slice 4 (faculty review, correction and reopening) is the next slice | One approved fixed record passes start, draft, submit, return, correction, resubmission and approval with immutable history.                                                    |
|     5 | Supporting clinical records          | Not started                                                                                                                                                           | Individually approved intervention, ADR, counselling, drug-information or posting records pass their direct workflows.                                                          |
|     6 | Detailed review workflow             | Blocked by review-policy and rubric decisions                                                                                                                         | Approved comments, comparison, assessment and exceptional reopen paths pass.                                                                                                    |
|     7 | Additional B.Pharm practical records | Blocked by faculty-approved record structures                                                                                                                         | Each requested fixed practical record is added without introducing curriculum tables or automatic curriculum assignment.                                                        |
|     8 | Portfolio/reporting                  | Not started                                                                                                                                                           | Required authorized record history, approved portfolios and exports pass privacy review.                                                                                        |
|     9 | PWA resilience                       | Not started; sync spike informs it                                                                                                                                    | Supported-device tests prove controlled caching, recovery and no silent data loss or overwrite.                                                                                 |
|    10 | Pilot hardening                      | Not started                                                                                                                                                           | Accessibility, security, performance, backup/restore and usability release gates pass.                                                                                          |

Faculty and institutional validation run in parallel and must be completed before finalizing fixed record fields, review rules, reports and production device-retention behavior.

## 8. Agent instructions

Every coding agent must:

1. Read `PROJECT_STATE.md` completely before planning, editing, or running migrations.
2. Verify the current Git branch, HEAD commit and working tree against the values recorded here.
3. Stop and report any unexplained mismatch, uncommitted user changes, or conflicting status record before changing overlapping files.
4. Work only on the active milestone unless the user explicitly changes scope.
5. Treat open decisions as temporary assumptions only; do not encode them as irreversible schema or workflow choices.
6. Do not begin deferred features or later gates early.
7. Preserve institution scoping, server-side authorization, de-identification, immutability and auditability in every vertical slice.
8. Run verification proportionate to the change, including negative authorization tests where applicable.
9. Never mark work complete without recording concrete test evidence.
10. Update this file only under the maintenance rule below and never silently rewrite superseded decisions.
11. After an accepted milestone, record its commit, verification evidence, limitations, new baseline and exact next milestone.

Use this prefix for every future VS Code coding-agent prompt:

```text
Read PROJECT_STATE.md completely before taking any action.
Treat it as the authoritative project status and decision record.
Verify the repository state against the baseline recorded there.
Work only on the active milestone.
```

## 9. Maintenance rule

Update `PROJECT_STATE.md` only when:

- a milestone starts;
- a milestone is completed and accepted;
- a decision is accepted, changed or superseded;
- a blocker is discovered or resolved; or
- the accepted baseline commit changes.

An implementation commit that has not been accepted or merged may be recorded as a candidate under active work, but it must not replace the accepted baseline. Keep detailed specifications, research and test findings under `docs/`; keep this file concise enough to establish where the project is, what is accepted, what is active, what comes next, and why.
