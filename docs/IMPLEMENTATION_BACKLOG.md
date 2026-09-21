# Pharmalab Phase 1 Implementation Backlog

## 1. Backlog rules

- Implement vertical slices, not isolated database or UI layers.
- Each story includes authorization, validation, states, tests and responsive behavior.
- P0 is required for the institutional pilot.
- P1 follows after the walking skeleton and validated clinical requirements.
- Do not begin AI or comprehensive drug-reference work in Phase 1.

## 2. Epic 0 — Institutional validation

### P0

- `VAL-01` Collect existing case-history, SOAP, intervention, ADR and logbook forms.
- `VAL-02` Confirm prohibited identifiers and institutional privacy notice.
- `VAL-03` Confirm required/optional sections by programme and rotation.
- `VAL-04` Confirm case lifecycle, review authority and correction-edit scope.
- `VAL-05` Confirm rubric, marks, pass rules and required exports.
- `VAL-06` Confirm devices, browsers and ward connectivity conditions.

**Gate:** Decisions recorded in `DECISIONS.md`; faculty owner approves clinical-field baseline.

## 3. Epic 1 — Repository and application foundation

### P0

- `FND-01` Scaffold current supported Laravel application with Inertia, Vue 3 and TypeScript.
- `FND-02` Configure PostgreSQL, Redis, environment templates and local setup instructions.
- `FND-03` Add formatting, static analysis, backend/frontend tests and CI.
- `FND-04` Establish design tokens, responsive shells and shared form primitives.
- `FND-05` Add authentication, password reset and secure session configuration.
- `FND-06` Add institution context, roles and policy test harness.
- `FND-07` Add structured logging/correlation IDs without clinical payload logging.

**Gate:** Clean install and CI pass; two-institution isolation test passes.

## 4. Epic 2 — Academic and rotation administration

### P0

- `ADM-01` Programme and cohort management.
- `ADM-02` Clinical site, department and ward management.
- `ADM-03` User create/edit/deactivate and role assignment.
- `ADM-04` CSV roster import with preview and row-level errors.
- `ADM-05` Rotation create/edit/status lifecycle.
- `ADM-06` Student/preceptor assignment with overlap validation.
- `ADM-07` Safe assignment notifications.
- `ADM-08` Audit events for all configuration changes.

### P1

- `ADM-09` Versioned case-template configuration.
- `ADM-10` Versioned rubric configuration.

**Gate:** Admin creates the complete setup required by the walking skeleton.

## 5. Epic 3 — Walking skeleton

### P0

- `WS-01` Student dashboard with active rotation.
- `WS-02` Student creates minimal de-identified case.
- `WS-03` Case overview/task-list shell.
- `WS-04` SOAP editor with server save and lock version.
- `WS-05` Review summary, completeness and attestation.
- `WS-06` Immutable submission snapshot and status transition.
- `WS-07` Faculty review queue and case reader.
- `WS-08` Minimal rubric/feedback decision.
- `WS-09` Approve case transition.
- `WS-10` Approved case appears in student portfolio.
- `WS-11` End-to-end audit timeline and notification.

**Gate:** Automated end-to-end test proves admin → student → faculty → portfolio loop.

## 6. Epic 4 — Full clinical case documentation

### P0

- `CASE-01` Case details and de-identification guidance.
- `CASE-02` Presenting complaint and history.
- `CASE-03` Allergies.
- `CASE-04` Examination and structured vitals.
- `CASE-05` Diagnoses/problems.
- `CASE-06` Investigations/lab results with units/ranges/times.
- `CASE-07` Medication chart.
- `CASE-08` Full SOAP guidance and feedback display.
- `CASE-09` Drug-related problem/intervention form.
- `CASE-10` Case task-list completeness engine.
- `CASE-11` Responsive add/edit flows for repeatable items.
- `CASE-12` Identifier-pattern warning before submission.

### P1

- `CASE-13` ADR record and configurable Naranjo questionnaire.
- `CASE-14` Counselling and monitoring plan.

**Gate:** Faculty validates field behavior using representative de-identified cases.

## 7. Epic 5 — Review, corrections and version history

### P0

- `REV-01` Section-anchored comment types.
- `REV-02` Comment resolve/reopen history.
- `REV-03` Configured rubric assessment.
- `REV-04` Return-for-correction transition and required reason.
- `REV-05` Student correction checklist and scoped editing.
- `REV-06` Resubmission creates new immutable version.
- `REV-07` Section-level revision comparison.
- `REV-08` Approval blocks unresolved required corrections.
- `REV-09` Exceptional reopen permission and reason.
- `REV-10` Review ageing and status notifications.

**Gate:** Return/resubmit/compare/approve path passes feature and end-to-end tests.

## 8. Epic 6 — Portfolio, progress and reporting

### P0

- `RPT-01` Student portfolio by rotation and status.
- `RPT-02` Faculty assigned-student progress.
- `RPT-03` Admin rotation progress and review-ageing report.

### P1

- `RPT-04` Authorized CSV export.
- `RPT-05` PDF case/portfolio export after privacy review.
- `RPT-06` Intervention category summary.

**Gate:** Exports preserve authorization and exclude prohibited identifiers.

## 9. Epic 7 — PWA and draft resilience

### P0

- `PWA-01` Installable manifest, icons and controlled application-shell caching.
- `PWA-02` IndexedDB draft/outbox schema.
- `PWA-03` Persistent autosave and sync indicator states.
- `PWA-04` Foreground reconnect and manual retry.
- `PWA-05` Idempotent sync endpoint.
- `PWA-06` Optimistic-version conflict detection.
- `PWA-07` Section-level conflict-resolution UI.
- `PWA-08` Device draft listing and secure clearing policy.
- `PWA-09` Offline fallback page.

**Gate:** Tested device scenarios show no silent data loss or overwrite. Submission remains online-only.

## 10. Epic 8 — Pilot hardening

### P0

- `QA-01` Authorization matrix and cross-institution negative tests.
- `QA-02` Workflow-transition and idempotency tests.
- `QA-03` Accessibility audit and critical-flow remediation.
- `QA-04` Android phone and tablet usability tests.
- `QA-05` Performance budgets for dashboards and case screens.
- `QA-06` Queue/retry/failure monitoring.
- `QA-07` Backup, restore and migration rehearsal.
- `QA-08` Security review, dependency scan and secret scan.
- `QA-09` Pilot support and incident process.
- `QA-10` Release checklist and rollback plan.

**Gate:** Pilot acceptance criteria in `PROJECT_PLAN.md` pass.

## 11. First development iteration

Recommended first iteration after planning approval:

1. `FND-01`–`FND-06`
2. Minimal `ADM-01`–`ADM-06`
3. `WS-01`–`WS-06`
4. `WS-07`–`WS-11`

Do not build all admin screens before proving the walking skeleton. Use only the configuration needed for one end-to-end path, then deepen each module.

## 12. Deferred backlog

- Comprehensive drug monographs and licensed content integration
- DDI decision-support engine
- Clinical calculators beyond institution-approved low-risk tools
- AI case review and presentation analysis
- Literature retrieval and citations
- Native apps and external API
- Hospital EHR/lab integration
- Multi-institution SaaS billing/licensing

Each deferred area requires separate product, licensing, safety and technical research.
