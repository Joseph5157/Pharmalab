# Pharmalab Phase 1 Project Plan

## 1. Purpose

Pharmalab Phase 1 is a mobile-first clinical learning and documentation platform for pharmacy students during hospital rotations. It supports one complete academic workflow:

> Rotation assignment → de-identified case documentation → student review → faculty review → correction/resubmission → approval → portfolio

The platform is an educational system. It is not an electronic health record, prescribing system or replacement for clinician judgement.

## 2. Fixed technical direction

- Laravel monolith
- Inertia.js
- Vue 3 with TypeScript
- PostgreSQL
- Tailwind CSS
- Redis for queues, cache and rate-limiting support
- PWA shell with controlled offline drafts using IndexedDB

## 3. Phase 1 users

| Role | Primary responsibility |
|---|---|
| Student | Document cases, submit work, respond to feedback and maintain a portfolio. |
| Faculty / preceptor | Review assigned cases, comment, grade, return or approve. |
| Institution administrator | Maintain academic structure, users, sites, rotations, assignments, templates and reports. |

## 4. Phase 1 scope

### Required for pilot

- Authentication and password recovery
- Institution-scoped role and record authorization
- Programmes, academic years, batches, hospitals, departments and wards
- Rotation creation and student/preceptor assignments
- De-identified case creation
- Case completion task list
- Clinical history, examination/vitals, diagnoses, labs and medication chart
- SOAP notes
- Drug-related problem and intervention documentation
- Completeness review and student attestation
- Submission snapshots
- Faculty queue, section comments and rubric
- Correction, resubmission, comparison and approval
- Notifications, audit history and basic portfolio
- Responsive student/faculty/admin interfaces
- Autosave, local draft resilience and explicit synchronization states

### Conditional Phase 1 items

- ADR documentation and configurable Naranjo questionnaire
- Counselling and monitoring-plan section
- Institution-approved calculators and reference links
- Operational report exports

These become required only after faculty validation.

### Deferred

- Full drug-monograph database
- Drug–drug interaction engine
- AI-generated clinical assessment or recommendations
- Live hospital EHR integration
- E-prescribing or medication ordering
- Native Android/iOS applications
- External public API
- Multi-stage or external clinician approvals
- Full FHIR implementation

## 5. Delivery principles

1. De-identification is built into fields, content guidance and submission checks.
2. Server-side authorization is the source of truth.
3. Submitted and approved versions are immutable.
4. Clinical information is structured where comparison matters.
5. No automated warning should pretend to be a diagnosis.
6. Mobile usability is a release requirement, not a later enhancement.
7. Every vertical slice includes authorization, validation, accessibility and tests.

## 6. Delivery phases

| Phase | Deliverable | Exit gate |
|---:|---|---|
| 0 | Institutional validation | Faculty signs off fields, forms, rubric, case counts, privacy rules and approval authority. |
| 1 | Engineering foundation | Application boots locally; CI passes; authentication, institution scoping and role policies work. |
| 2 | Academic administration | Admin can configure sites, programmes, users, rotations and assignments with audit history. |
| 3 | Walking skeleton | One assigned student creates a minimal case, submits it and one assigned faculty member approves it. |
| 4 | Clinical documentation | Required case sections support structured entry, validation and autosave. |
| 5 | Review workflow | Faculty comments, returns, compares revisions, applies rubric and approves. |
| 6 | Portfolio and reports | Students see approved work; faculty/admin see operational progress reports. |
| 7 | PWA resilience | Draft recovery, foreground synchronization and safe conflict resolution pass device tests. |
| 8 | Pilot hardening | Accessibility, security, performance and usability gates pass. |

## 7. Walking-skeleton definition

The first end-to-end implementation contains only enough functionality to prove architecture and workflow:

1. Admin creates one programme, hospital, ward and rotation.
2. Admin assigns one student and one faculty reviewer.
3. Student signs in and sees the active rotation.
4. Student creates a de-identified case with basic details and a SOAP note.
5. Student reviews and submits the case.
6. System creates an immutable submission version.
7. Faculty sees the case in the review queue.
8. Faculty records a simple review and approves it.
9. Student sees the approved case in the portfolio.
10. All important actions appear in the audit timeline.

The walking skeleton must not use temporary authorization shortcuts or disposable status logic.

## 8. Required validation workshop

Before finalizing clinical migrations and forms, collect and review:

- Current bedside case-history form
- SOAP format
- Intervention/DRP form and terminology
- ADR/Naranjo requirement
- Rotation logbook and required case counts
- Review rubric and marking rules
- Required reports and exports
- Hospital/institution privacy guidance
- Common ward devices and connectivity limitations

Required decisions are tracked in [DECISIONS.md](DECISIONS.md).

## 9. Pilot acceptance criteria

### Workflow

- A case can complete the full draft-to-approval lifecycle.
- Invalid status transitions are rejected server-side.
- Earlier submissions remain readable after corrections.

### Privacy and security

- Standard forms contain no fields for patient name, phone, address, government ID, full date of birth or hospital MRN.
- Tenant and record-level authorization tests cover negative cases.
- Exports, logs and notifications do not expose case content unnecessarily.

### Reliability

- Network loss does not silently discard saved work.
- A stale or offline edit never silently overwrites newer server content.
- Submission, return and approval operations are transactional and idempotent.

### Usability and accessibility

- Core student tasks work on representative Android phones.
- Faculty review works on tablet and desktop.
- Keyboard, focus, error summary, responsive reflow and touch targets meet the selected WCAG 2.2 AA baseline.

### Quality

- Automated tests pass in CI.
- No known critical authorization, data-loss or cross-institution defects remain.
- Faculty and student pilot users complete the agreed usability scenarios.

## 10. Definition of done

A backlog item is done only when:

- Acceptance criteria are implemented.
- Authorization and validation are server-side.
- Loading, empty, error and permission states are covered.
- Relevant automated tests pass.
- Mobile and desktop behavior is checked where applicable.
- Accessibility behavior is tested.
- Documentation and decision records are updated.
- No unrelated user data or credentials are committed.

## 11. Immediate next action

Run Phase 0 validation while engineering builds Phase 1 foundation and the minimal walking skeleton. Do not freeze detailed clinical-field migrations until the institution's forms and rubric are reviewed.
