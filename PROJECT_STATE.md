# Pharmalab Project State

This file is the canonical source of truth for current project status, accepted decisions, active work, and the next milestone. Detailed specifications remain under `docs/`.

> Read `PROJECT_STATE.md` completely before taking any action.  
> Treat it as the authoritative project status and decision record.  
> Verify the repository state against the baseline recorded here.  
> Work only on the active milestone.

## 1. Current position

| Item                             | Current value                                                                |
| -------------------------------- | ---------------------------------------------------------------------------- |
| Repository                       | `Joseph5157/Pharmalab`                                                       |
| Accepted baseline branch         | `main`                                                                       |
| Accepted baseline commit         | `c167e9e` — accepted WALKING-SKELETON-01                                     |
| Current working branch           | `main`                                                                       |
| Last completed and accepted gate | Walking skeleton — `WALKING-SKELETON-01`                                     |
| Active gate                      | Clinical documentation                                                       |
| Current milestone                | Clinical documentation — institutional form definition                       |
| Milestone status                 | Blocked by institutional forms                                               |
| Exact next action                | Obtain faculty-approved clinical forms, required fields and validation rules |
| Next gate after this milestone   | Complete review workflow                                                     |

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

### Clinical documentation — institutional form definition

- **Status:** Blocked by institutional forms
- **Objective:** Define the faculty-approved sections, structured fields, validation and sync requirements for clinical documentation.
- **Depends on:** `WALKING-SKELETON-01` (accepted), institutional faculty/curriculum and privacy decisions.
- **Exact next action:** Obtain the approved institutional forms and field requirements before designing or migrating the complete clinical schema.
- **Scope boundary:** Do not begin implementation until the institutional forms are approved; preserve the walking-skeleton record as the minimal temporary workflow.

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

The full durable decision register is maintained in [`docs/DECISIONS.md`](docs/DECISIONS.md). If this summary and that file disagree, stop and reconcile the inconsistency before implementation.

## 5. Open decisions

Do not turn these temporary assumptions into permanent schema or workflow rules.

| Question                                                                                         | Why it matters                                                    | Who must answer                             | Gate blocked                                | Current temporary assumption                                                                                          |
| ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------- | ------------------------------------------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Which exact fields and sections are required for Pharm.D, M.Pharm and B.Pharm cases?             | Determines clinical migrations, templates and completeness rules. | Faculty/curriculum owner                    | Full clinical documentation                 | Build only the minimal de-identified case details and SOAP note required by the walking skeleton.                     |
| What identifiers and demographics may be recorded?                                               | Defines privacy boundaries and validation.                        | Institution privacy owner and hospital      | Walking skeleton and clinical documentation | No patient name, full DOB, phone, address, government ID or hospital MRN; use age/age unit or age band provisionally. |
| Who may review, return, approve and exceptionally reopen a case? Is a second signature required? | Materially changes permissions, lifecycle and schema.             | Faculty programme owner                     | Complete review workflow                    | One assigned primary faculty/preceptor reviews each case; do not implement multi-reviewer approval yet.               |
| What rubric, scoring scale and pass rule apply?                                                  | Determines rubric versioning and approval prerequisites.          | Faculty/curriculum owner                    | Complete review workflow                    | Walking skeleton uses only a basic review summary/attestation and approval decision.                                  |
| After return, may students edit all sections or only flagged sections?                           | Determines correction authorization and UI.                       | Faculty programme owner                     | Complete review workflow                    | Provisional direction is flagged sections only, with an explicit faculty option to unlock all; do not finalize yet.   |
| What case counts and categories are required per rotation?                                       | Determines progress and portfolio calculations.                   | Faculty/curriculum owner                    | Portfolio/reporting                         | Show case status without enforcing programme-specific quotas.                                                         |
| Is ADR/Naranjo mandatory, optional, or used only for suspected ADR cases?                        | Determines form priority and completeness.                        | Faculty/pharmacovigilance owner             | Full clinical documentation                 | Defer it until the institutional form is reviewed.                                                                    |
| Is counselling/monitoring separate or part of SOAP Plan?                                         | Affects section design and grading.                               | Faculty/curriculum owner                    | Full clinical documentation                 | Keep it out of the walking skeleton and defer the final structure.                                                    |
| What reports and exports are mandatory, and who may access them?                                 | Determines data exposure and reporting scope.                     | Institution administrator and privacy owner | Portfolio/reporting                         | Implement only on-screen progress needed by the walking skeleton.                                                     |
| What are retention, backup and device-draft clearing periods?                                    | Determines IndexedDB retention and offline privacy behavior.      | Institution privacy/security owner          | Production PWA resilience                   | Clear local drafts on logout; make no permanent-storage guarantee; do not finalize retention duration.                |
| Which browsers, devices and connectivity conditions are typical in wards?                        | Determines the supported PWA matrix and fallback behavior.        | Institution IT and pilot users              | Production PWA resilience                   | Use mobile-first responsive design and foreground/manual recovery; validate the actual device matrix before release.  |
| Is multilingual UI/content required for the pilot?                                               | Affects layouts, content design and stored fields.                | Institution/faculty owner                   | Pilot hardening                             | English-only pilot UI until confirmed otherwise.                                                                      |

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
- **Status:** The accepted baseline is `c167e9e`; clinical documentation is the active, currently blocked gate.

## 7. Remaining gates

| Order | Gate                             | Status                                        | Exit condition                                                                                                                                                                  |
| ----: | -------------------------------- | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|     1 | Academic/rotation administration | Accepted — `ADM-FOUNDATION-01`                | Admin can create the minimum programme, cohort, site, department, ward and rotation structure and assign one student and faculty member, with authorization and audit coverage. |
|     2 | Walking skeleton                 | Accepted — `WALKING-SKELETON-01`              | The admin → student case/SOAP submission → faculty approval → portfolio loop passes end-to-end.                                                                                 |
|     3 | Clinical documentation           | Blocked by institutional forms                | Faculty-approved sections support structured entry, validation and the accepted sync approach.                                                                                  |
|     4 | Complete review workflow         | Blocked by review-policy and rubric decisions | Comment, correction, resubmission, comparison, rubric, approval and exceptional reopen paths pass.                                                                              |
|     5 | Portfolio/reporting              | Not started                                   | Required authorized progress views and approved exports pass privacy review.                                                                                                    |
|     6 | PWA resilience                   | Not started; sync spike informs it            | Supported-device tests prove controlled caching, recovery and no silent data loss or overwrite.                                                                                 |
|     7 | Pilot hardening                  | Not started                                   | Accessibility, security, performance, backup/restore and usability release gates pass.                                                                                          |

Institutional validation runs in parallel and must be completed before finalizing clinical forms, review rules, reports, and production device-retention behavior.

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
