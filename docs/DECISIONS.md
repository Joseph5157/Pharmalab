# Pharmalab Decision Log

## 1. How to use this file

Record durable product and engineering decisions here. Do not silently change an accepted decision in code. Add a new decision that supersedes it, including the reason and impact.

Statuses:

- **Accepted** — implementation may rely on it.
- **Provisional** — current direction; institutional validation still required.
- **Open** — blocks or may materially change implementation.
- **Deferred** — intentionally outside Phase 1.

## 2. Accepted decisions

| ID      | Decision                                                                                                                                                                          | Reason / consequence                                                                                                              |
| ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| DEC-001 | Use Laravel + Inertia.js + Vue 3 + TypeScript + PostgreSQL.                                                                                                                       | Delivers an app-like interface while retaining a cohesive server-side authorization and relational domain model.                  |
| DEC-002 | Build a modular monolith for Phase 1.                                                                                                                                             | Avoids premature API/microservice overhead; domain services can be exposed later.                                                 |
| DEC-003 | Phase 1 centers on clinical case documentation and faculty review.                                                                                                                | Provides a valuable complete learning loop before knowledge-engine or AI features.                                                |
| DEC-004 | The record is a student educational case, not a live patient EHR.                                                                                                                 | UI, permissions and language must not imply clinical ordering or official medical-record status.                                  |
| DEC-005 | Use de-identification by design.                                                                                                                                                  | Standard forms will not collect patient name, full DOB, phone, address, government ID or hospital MRN.                            |
| DEC-006 | Use a task-list case workspace with focused section screens.                                                                                                                      | Students can document in the order clinical information becomes available and resume easily.                                      |
| DEC-007 | Submitted and approved versions are immutable.                                                                                                                                    | Faculty decisions must remain tied to exactly what was reviewed.                                                                  |
| DEC-008 | Authorization is enforced with Laravel policies and scoped queries.                                                                                                               | Vue capability flags are presentation helpers only.                                                                               |
| DEC-009 | Offline support is limited to de-identified drafts.                                                                                                                               | Server remains source of truth; submission, return and approval require online/current state.                                     |
| DEC-010 | Do not rely solely on browser Background Sync.                                                                                                                                    | Browser support is incomplete; foreground/manual retry is required.                                                               |
| DEC-011 | Use structured records for labs, vitals, medicines, diagnoses and interventions.                                                                                                  | Supports comparison, validation, reporting and future standards mapping.                                                          |
| DEC-012 | Be FHIR-aligned where useful, but do not claim Phase 1 FHIR conformance.                                                                                                          | Full conformance requires profiling, terminology and interoperability testing.                                                    |
| DEC-013 | Use a faculty-approved local intervention taxonomy initially.                                                                                                                     | Full PCNE classification reuse requires licensing/permission review for commercial use.                                           |
| DEC-014 | AI-generated clinical recommendations are deferred.                                                                                                                               | Normal Phase 1 workflow and governed data must be established first.                                                              |
| DEC-015 | Comprehensive drug monographs and DDI checking are deferred.                                                                                                                      | Content sourcing, licensing, clinical governance and update processes need separate work.                                         |
| DEC-016 | Academic, site, rotation and assignment records carry explicit institution ownership and use restrictive foreign keys. Referenced records are deactivated rather than deleted.    | Keeps tenant boundaries queryable and preserves the academic context needed by later case records and audit history.              |
| DEC-017 | Phase 1 student groups are B.Pharm and Pharm.D; M.Pharm is deferred.                                                                                                              | Keeps the first release aligned to the product owner's confirmed users without introducing a curriculum package.                  |
| DEC-022 | Every implementation gate receives a bounded requirements and inspiration pass before UX approval and coding.                                                                     | Allows relevant internet products and Mobbin patterns to inform usability without replacing privacy, faculty or client requirements. |
| DEC-023 | Phase 1 uses direct student/rotation assignment and documentation; it does not store or resolve a regulatory curriculum model.                                                       | Aligns the build with the client-requested workflow and prevents speculative curriculum data from entering the database.           |
| DEC-024 | Do not add curriculum versions, periods, subjects, regulatory mappings, activity requirements or curriculum-based template assignments in Phase 1.                                      | Makes the database boundary explicit and testable.                                                                                  |
| DEC-025 | Begin with fixed faculty-approved form structures and a stable form-version identifier; defer the dynamic template builder.                                                               | Delivers the workflow with less complexity while preserving historical interpretation.                                             |
| DEC-026 | Extend the accepted walking skeleton rather than create a second generic record or assignment engine.                                                                                    | Preserves tested authorization, sync, immutable snapshots and audit behaviour.                                                      |
| DEC-027 | The first Pharm.D form uses the field catalogue and validation policy approved on 25 September 2026. | Converts the candidate into a fixed implementation baseline while preserving server-authoritative validation. |
| DEC-028 | Students start cases within an authorized rotation; the assigned faculty member is the single reviewer. | Fits ward-round practice and the existing assignment model without per-case faculty setup or a second signature. |
| DEC-029 | Returned or reopened cases unlock all sections and highlight faculty-flagged sections. | Allows clinically connected corrections without complex field locking. |
| DEC-030 | Faculty feedback supports overall and section-level comments; marks, rubrics and field annotations are excluded. | Provides actionable feedback with bounded Phase 1 complexity. |
| DEC-031 | Assigned faculty may reopen an approved case only with a mandatory audited reason. | Supports genuine correction without silently rewriting approved evidence. |
| DEC-032 | Faculty configures simple rotation case targets; progress is role-scoped and approved cases export as de-identified PDF. | Supports learning oversight without curriculum tables. |
| DEC-033 | No student attachments; approved educational cases are retained through course completion plus one year. | Reduces privacy/storage risk and avoids indefinite retention. |
| DEC-034 | Pilot UI is English and officially supports Android Chrome phone/tablet and desktop Chrome/Edge. | Keeps the initial Indian pilot device matrix focused and testable. |
| DEC-035 | B.Pharm practical records are delivered in a separate next gate after the Pharm.D clinical module. | Avoids coupling two different record domains in the first implementation gate. |


## 3. Provisional decisions requiring validation

| ID | Provisional decision | Validation needed |
| --- | --- | --- |
| DEC-109 | Section-level revision comparison is sufficient for Phase 1. | Test repeatable medication and investigation rows with faculty during implementation. |
| DEC-111 | B.Pharm clinical learning uses masked/theoretical cases by default. | Resolve separately in the B.Pharm gate; it does not block the Pharm.D implementation. |

## 4. Open institutional decisions

These items remain before production pilot; the 25 September product decisions resolved the former field, workflow, attachment, report, retention-duration, language and device-scope questions.

| ID | Question | Why it matters |
| --- | --- | --- |
| OPEN-003 | Has the hospital/institution approved the de-identification boundary and permitted case-date precision? | Confirms local privacy governance. |
| OPEN-009 | What happens to unfinished cases when a rotation ends? | Determines grace period, read-only state or explicit extension. |
| OPEN-013 | What backup/restore procedure and operational owner apply to retained educational records? | Required for pilot resilience and recovery. |
| OPEN-016 | What final PDF branding/layout is approved? | Determines the formal appearance of exported academic records. |

## 5. Deferred decisions

| ID      | Decision area                                   | Revisit when                                                                              |
| ------- | ----------------------------------------------- | ----------------------------------------------------------------------------------------- |
| DEF-001 | Drug-monograph API versus curated database      | Phase 1 workflow is stable and licensing budget is known.                                 |
| DEF-002 | DDI data provider and clinical governance       | Licensed data and pharmacist governance team are available.                               |
| DEF-003 | AI provider, model, retrieval and evaluation    | Sufficient governed cases exist and safety/evaluation protocol is approved.               |
| DEF-004 | Public API / native applications                | A second client or external institution has a confirmed need.                             |
| DEF-005 | Multi-tenant SaaS subscriptions and super-admin | Pilot proves institutional value and commercial model is approved.                        |
| DEF-006 | M.Pharm records                                 | A later phase explicitly approves M.Pharm scope and its record requirements.             |

## 6. Scope reconciliation records

### ADM-FOUNDATION-01 — 2026-09-21

- `PROJECT_STATE.md` narrows this gate relative to the broader implementation backlog.
- Included now: programmes, cohorts, clinical sites, departments, wards, student/faculty account creation and status administration, rotations, one student/primary-preceptor assignment, tenant authorization, and audit events.
- Deferred from the broader Epic 2 backlog: CSV roster import (`ADM-04`) and assignment notifications (`ADM-07`).
- Deferred from the schema/workflow direction: case-template and rubric references, requirements JSON, overlap scheduling rules, and activation prerequisites that depend on later institutional decisions.
- Explicitly excluded: template/rubric builders (`ADM-09`/`ADM-10`), reports, clinical schema, and case/review workflow.
- Reason: the authoritative milestone is limited to the minimum configuration an administrator needs to support the next walking-skeleton gate.

### PCI curriculum and template direction — 2026-09-22 — superseded

- The proposed curriculum/template foundation was a research direction only and was not implemented.
- Its curriculum database, automatic curriculum assignment and dynamic template-builder scope were superseded by the client decision recorded on 24 September 2026.
- Historical context remains available in Git history; it is not an active implementation instruction.

### Direct documentation Phase 1 direction — 2026-09-24

- The client requires the direct documentation and faculty-review workflow, not curriculum-based activity assignment.
- No new curriculum versions, periods, subjects, regulatory mappings, curriculum activity requirements, student-period enrolments or automatic curriculum-to-template assignments will be added.
- Existing accepted programme, cohort, site, ward and rotation tables remain untouched but are not expanded into a curriculum engine.
- Phase 1 begins with fixed faculty-approved form structures and stable form-version identification.
- The active implementation gate is `DIRECT-DOCUMENTATION-IMPL-01`.
- Detailed record: [`docs/decisions/2026-09-24_DIRECT_DOCUMENTATION_PHASE1_DIRECTION.md`](decisions/2026-09-24_DIRECT_DOCUMENTATION_PHASE1_DIRECTION.md).

## 7. Decision-change template

```text
ID: DEC-XXX
Date:
Status:
Decision:
Context:
Options considered:
Reason:
Consequences:
Supersedes:
Approved by:
```
