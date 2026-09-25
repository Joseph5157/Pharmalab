# Pharmalab Project State

This file is the canonical source of truth for current project status, accepted decisions, active work, and the next milestone. Detailed specifications remain under `docs/`.

> Read `PROJECT_STATE.md` completely before taking any action.  
> Treat it as the authoritative project status and decision record.  
> Verify the repository state against the baseline recorded here.  
> Work only on the active milestone.

## 1. Current position

| Item                                             | Current value                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Repository                                       | `Joseph5157/Pharmalab`                                                                                                                                                                                                                                                                                   |
| Accepted baseline branch                         | `main`                                                                                                                                                                                                                                                                                                   |
| Accepted feature baseline                        | `c167e9e` — accepted `WALKING-SKELETON-01`                                                                                                                                                                                                                                                               |
| Repository HEAD before this documentation change | `ba58646` — merge of PR #8, `DIRECT-DOCUMENTATION-IMPL-01` Slice 1                                                                                                                                                                                                                                       |
| Last completed and accepted gate                 | Walking skeleton — `WALKING-SKELETON-01`                                                                                                                                                                                                                                                                 |
| Active gate                                      | `DIRECT-DOCUMENTATION-IMPL-01`                                                                                                                                                                                                                                                                           |
| Current milestone                                | Direct clinical/practical documentation and faculty review                                                                                                                                                                                                                                               |
| Milestone status                                 | Slice 1 (domain and schema reconciliation) accepted and merged via PR #8; Slice 2 (mobile case editor) active                                                                                                                                                                                            |
| Exact next action                                | Implement Slice 2 (mobile case editor) on a short-lived feature branch from `docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md`, including the explicit-absence schema fields (vitals/investigations unavailable, no current medicines) scoped to this slice on PR #8 before building the form UI |
| Next gate after this milestone                   | Detailed review/comments or the next approved fixed record type                                                                                                                                                                                                                                          |

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

- **Status:** Active; Slice 1 (domain and schema reconciliation) accepted and merged. Slice 2 (mobile case editor) not started.
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
    - No schema field yet for "vitals unavailable" / "investigations unavailable" / "no current medicines" (explicit-absence) answers — **belongs to Slice 2, before that slice's form UI implementation.**
- **Deferred minors (no owning slice assigned):** policy logic duplicated across 7 classes (`SoapNotePolicy`, `CaseVersionPolicy` and the five new Slice 1 policies); some validation rules looser than the field catalogue's enumerated values; `ClinicalCase`'s docblock not updated for the new columns.

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

| Order | Gate                                 | Status                                        | Exit condition                                                                                                                                                                  |
| ----: | ------------------------------------ | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|     1 | Academic/rotation administration     | Accepted — `ADM-FOUNDATION-01`                | Admin can create the minimum programme, cohort, site, department, ward and rotation structure and assign one student and faculty member, with authorization and audit coverage. |
|     2 | Walking skeleton                     | Accepted — `WALKING-SKELETON-01`              | The admin → student case/SOAP submission → faculty approval → portfolio loop passes end-to-end.                                                                                 |
|     3 | Direct documentation scope           | Accepted through PR #6                        | The client-aligned fixed-form workflow, data exclusions, screens and acceptance criteria are recorded.                                                                          |
|     4 | Direct documentation implementation  | Active — not started                          | One approved fixed record passes start, draft, submit, return, correction, resubmission and approval with immutable history.                                                    |
|     5 | Supporting clinical records          | Not started                                   | Individually approved intervention, ADR, counselling, drug-information or posting records pass their direct workflows.                                                          |
|     6 | Detailed review workflow             | Blocked by review-policy and rubric decisions | Approved comments, comparison, assessment and exceptional reopen paths pass.                                                                                                    |
|     7 | Additional B.Pharm practical records | Blocked by faculty-approved record structures | Each requested fixed practical record is added without introducing curriculum tables or automatic curriculum assignment.                                                        |
|     8 | Portfolio/reporting                  | Not started                                   | Required authorized record history, approved portfolios and exports pass privacy review.                                                                                        |
|     9 | PWA resilience                       | Not started; sync spike informs it            | Supported-device tests prove controlled caching, recovery and no silent data loss or overwrite.                                                                                 |
|    10 | Pilot hardening                      | Not started                                   | Accessibility, security, performance, backup/restore and usability release gates pass.                                                                                          |

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
