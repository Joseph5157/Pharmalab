# Pharmalab Project State

This file is the canonical source of truth for current project status, accepted decisions, active work, and the next milestone. Detailed specifications remain under `docs/`.

> Read `PROJECT_STATE.md` completely before taking any action.  
> Treat it as the authoritative project status and decision record.  
> Verify the repository state against the baseline recorded here.  
> Work only on the active milestone.

## 1. Current position

| Item                             | Current value                                                                                                                  |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Repository                       | `Joseph5157/Pharmalab`                                                                                                         |
| Accepted baseline branch         | `main`                                                                                                                         |
| Accepted baseline commit         | `84c3320` — `FND-01 — Laravel application foundation`                                                                          |
| Current working branch           | `sync-spike-01`                                                                                                                |
| Sync implementation commit       | `a532d87` — `SYNC-SPIKE-01 experimental offline autosave and conflict handling`                                                |
| Last completed and accepted gate | Build Gate 1 — Foundation                                                                                                      |
| Active gate                      | Build Gate 2 — Early sync spike                                                                                                |
| Current milestone                | `SYNC-SPIKE-01`                                                                                                                |
| Milestone status                 | Implemented and verified; awaiting review, acceptance, and merge                                                               |
| Exact next milestone             | Accept and merge `SYNC-SPIKE-01`, then start the minimum academic/rotation administration slice needed by the walking skeleton |

The sync implementation commit is a review candidate, not yet the accepted baseline. Later documentation-only commits may exist on the branch. Until the sync spike is accepted, agents must branch from or compare against `84c3320` as directed by the task owner and must not describe `a532d87` as merged.

## 2. Completed and accepted work

### Build Gate 1 — Foundation

- **Status:** Accepted and closed
- **Commit:** `84c3320`
- **Delivered:** Laravel, Inertia.js, Vue 3, TypeScript, PostgreSQL and Redis foundation; authentication and password reset; institution scoping; student, faculty/preceptor and institution-administrator roles; role dashboards; Laravel authorization policies; responsive navigation; CI; automated tests; local-only demo users and seed data; local setup documentation.
- **Verification:** Clean-install workflow, authentication, role-route isolation, institution isolation, responsive/mobile navigation, automated application tests, frontend checks, static analysis, formatting and production build were verified before acceptance.
- **Important limitation:** This baseline does not contain the academic administration workflow, clinical case workflow, review workflow, or production offline support.

## 3. Active work

### `SYNC-SPIKE-01` — Experimental offline autosave and conflict handling

- **Status:** Implemented and verified on `sync-spike-01`; awaiting acceptance and merge
- **Candidate commit:** `a532d87`
- **Objective:** Prove that a student can edit one de-identified case-note section during unreliable connectivity without silently losing or overwriting data.
- **Scope:** One experimental `Case Draft Note`; this is not the complete case module or clinical schema.
- **Implemented:** IndexedDB draft/outbox, stable `client_operation_id`, idempotent synchronization, server `lock_version`, optimistic concurrency checks, manual retry, foreground reconnect handling, persistent save states, mobile section-level conflict resolution, audit events, logout draft clearing, and owner/institution authorization.
- **Automated verification:** 54 application tests run, 52 passed, 2 skipped, with 205 assertions. The focused sync suite passed 7 tests with 29 assertions. PHPStan, Pint, frontend checks, TypeScript checks, production build, and `git diff --check` passed.
- **Browser verification:** Headless Chromium at 390 × 844 verified online autosave, network failure, IndexedDB persistence, offline refresh recovery after reconnect, foreground synchronization, stale-version conflict handling, mobile conflict choices, and logout clearing.
- **Findings:** See [`docs/SYNC_SPIKE_01_FINDINGS.md`](docs/SYNC_SPIKE_01_FINDINGS.md).

#### Known spike boundaries

- A cold load or refresh while fully offline shows the browser network error because clinical-page service-worker caching is deferred. The IndexedDB draft survives and is recovered after reconnection and reopening.
- Browser storage may be evicted; permanent offline storage is not guaranteed.
- Background Sync, automatic field-level merging, faculty review, submission, and full clinical forms are not included.
- Archived device copies are proven technically, but a complete draft-management screen and the final retention policy remain open.
- The repository's existing `composer test` script invokes bare `pint` and fails on Windows path resolution. Its underlying checks pass when the vendor binaries are invoked directly.

#### Acceptance action

Review the spike findings and implementation. If accepted, merge `sync-spike-01`, record the merge commit as the new accepted baseline in this file, close Build Gate 2, and activate the academic/rotation administration milestone.

## 4. Accepted decisions and reasons

| Decision                                               | Status                 | Reason                                                                                                                                                                           |
| ------------------------------------------------------ | ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel + Inertia.js + Vue 3 + TypeScript + PostgreSQL | Accepted               | Provides a modern interface with cohesive server-side authorization and a relational domain model.                                                                               |
| Modular monolith                                       | Accepted               | Phase 1 does not need external API or microservice complexity.                                                                                                                   |
| Server-side policies and institution-scoped queries    | Accepted               | Client capability flags are presentation helpers, not security controls.                                                                                                         |
| De-identification by design                            | Accepted               | The educational record must not collect direct patient identifiers in standard forms.                                                                                            |
| Immutable submitted and approved versions              | Accepted               | Faculty decisions must remain tied to the exact work reviewed.                                                                                                                   |
| Early sync spike before clinical forms                 | Accepted               | Autosave, retry, idempotency and concurrency affect every later case section.                                                                                                    |
| Offline support limited to de-identified drafts        | Accepted               | The server remains authoritative; submission, return and approval require a current online state.                                                                                |
| Foreground reconnect and manual retry are required     | Accepted               | Browser Background Sync is not reliable enough to be the only recovery mechanism.                                                                                                |
| Section-level conflict handling                        | Accepted for the spike | It prevents silent overwrite without introducing automatic field/text merge complexity. Reuse in clinical forms remains subject to spike acceptance and device-retention policy. |
| AI-generated clinical recommendations                  | Deferred               | Complete the governed normal workflow and collect suitable data before evaluating AI.                                                                                            |
| Comprehensive drug monographs and DDI checking         | Deferred               | Licensing, content governance and update processes require separate work.                                                                                                        |

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

## 7. Remaining gates

| Order | Gate                             | Status                                        | Exit condition                                                                                                                                                  |
| ----: | -------------------------------- | --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|     1 | Sync spike                       | Active — implemented and awaiting acceptance  | Findings and approach are reviewed, the branch is merged, and the accepted baseline is updated.                                                                 |
|     2 | Academic/rotation administration | Not started                                   | Admin can create the minimum programme, hospital, ward and rotation structure and assign one student and faculty member, with authorization and audit coverage. |
|     3 | Walking skeleton                 | Not started                                   | The admin → student case/SOAP submission → faculty approval → portfolio loop passes end-to-end.                                                                 |
|     4 | Clinical documentation           | Blocked by institutional forms                | Faculty-approved sections support structured entry, validation and the accepted sync approach.                                                                  |
|     5 | Complete review workflow         | Blocked by review-policy and rubric decisions | Comment, correction, resubmission, comparison, rubric, approval and exceptional reopen paths pass.                                                              |
|     6 | Portfolio/reporting              | Not started                                   | Required authorized progress views and approved exports pass privacy review.                                                                                    |
|     7 | PWA resilience                   | Not started; spike informs it                 | Supported-device tests prove controlled caching, recovery and no silent data loss or overwrite.                                                                 |
|     8 | Pilot hardening                  | Not started                                   | Accessibility, security, performance, backup/restore and usability release gates pass.                                                                          |

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
