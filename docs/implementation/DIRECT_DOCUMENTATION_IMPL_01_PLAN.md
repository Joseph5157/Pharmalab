# DIRECT-DOCUMENTATION-IMPL-01 Implementation Plan

**Status:** Ready for implementation  
**Decision baseline:** 25 September 2026  
**Depends on:** WALKING-SKELETON-01, SYNC-SPIKE-01 and the approved Pharm.D form baseline

## 1. Goal

Extend the existing case/SOAP walking skeleton into one complete, fixed and de-identified Pharm.D clinical-documentation workflow without creating a second case engine.

Exit outcome:

> An authorized student starts a case, completes all approved sections on mobile, saves through intermittent connectivity, submits an immutable version, receives section-level faculty feedback, corrects and resubmits, obtains approval, and sees progress/PDF output under role-scoped access.

## 2. Mandatory pre-edit audit

Before migrations or application edits, the implementation agent must:

1. Read PROJECT_STATE.md completely.
2. Verify the branch and accepted main baseline.
3. Inspect existing clinical_cases, case_versions, SOAP, review, status-history, sync and audit structures.
4. Extend existing aggregates and services; do not introduce a parallel record lifecycle.
5. Record any schema conflict before changing migrations.
6. Preserve institution scoping, owner/reviewer authorization and immutable snapshot behaviour.

## 3. Delivery slices

### Slice 1 — Domain and compatibility

- Reconcile the approved fields with existing tables/models.
- Define one stable Pharm.D form version.
- Add only the minimum normalized/JSON structures needed for repeatable vitals, investigations, medicines and conditional activities.
- Preserve existing walking-skeleton cases through a documented compatibility/default path.
- Add server request validation and one authoritative completeness service.
- Add policies and tenant/assignment constraints before UI work.

Exit evidence:

- migrations run from the accepted baseline;
- existing tests remain green;
- cross-institution and cross-student negative tests pass;
- a new draft stores its fixed form version.

### Slice 2 — Mobile case editor

Implement phone-first sections:

1. Case Profile
2. History and Diagnosis
3. Vitals and Investigations
4. Medication Chart
5. SOAP
6. Conditional Clinical Activities

Requirements:

- one logical section at a time;
- visible completion/progress;
- persistent save status;
- bounded repeatable rows;
- accessible labels and errors;
- DD/MM/YYYY display and 24-hour time;
- accepted IndexedDB outbox, idempotency, retry and conflict behaviour;
- no optional child record until deliberate save.

Exit evidence:

- Android-sized browser scenarios cover create, edit, offline draft recovery, reconnect and conflict resolution;
- direct identifier fields are absent;
- screen-reader labels and keyboard access pass component checks.

### Slice 3 — Submission and completeness

- Add read-only submission review.
- Show missing-section/error summary.
- Require de-identification attestation.
- Apply conditional ADR/intervention/counselling/monitoring rules.
- Submit only while online.
- Create an immutable snapshot tied to the fixed form version.
- Reject unknown and unauthorized fields server-side.

Exit evidence:

- incomplete and privacy-invalid submissions fail;
- a complete case submits once under repeated/idempotent requests;
- submitted clinical content cannot be mutated.

### Slice 4 — Faculty review, correction and reopening

- Review queue remains assignment- and institution-scoped.
- Show the exact submitted snapshot and prior versions.
- Add overall feedback.
- Add section flags and section-level comments.
- Require an actionable section comment/reason to return.
- Unlock all student sections after return and highlight flags.
- Resubmission creates a new immutable snapshot.
- Assigned faculty may reopen an approved case with a mandatory reason and audit event.
- Exclude field comments, rubrics, marks and second review.

Exit evidence:

- return → edit → resubmit → approve passes end to end;
- reopen → correction → resubmit preserves the earlier approved version;
- unauthorized faculty/admin/student attempts are rejected.

### Slice 5 — Targets, progress, PDF and retention

- Add simple faculty-configured rotation case targets/categories without curriculum tables.
- Add student-own, assigned-faculty and institution-admin progress views.
- Export approved cases only as de-identified PDFs.
- Audit export actions.
- Record the course-completion-plus-one-year retention policy without implementing unsafe automatic deletion until an operational archival job is approved.
- Keep student attachments disabled.

Exit evidence:

- progress counts match authorized approved/submitted cases;
- PDF contains the educational Case ID and omits prohibited identifiers;
- cross-tenant report/export tests pass.

### Slice 6 — Release verification and pilot pack

Run:

- PHP application and authorization tests;
- PHPStan and Pint;
- frontend formatting/lint and TypeScript;
- production build;
- mobile browser flow at representative Android viewport;
- desktop faculty flow in Chrome/Edge;
- online save, network failure, reconnect, conflict and logout clearing;
- accessibility, security and performance checks proportionate to the changed surfaces;
- backup/restore rehearsal before pilot data.

Produce:

- implementation record with commit/test evidence;
- pilot checklist for students and faculty;
- known-limitations list;
- rollback and data-recovery notes.

## 4. Scope exclusions

Do not add:

- curriculum/subject/semester tables or automatic curriculum assignment;
- dynamic template builder;
- B.Pharm practical engine;
- attachments;
- marks/rubrics;
- multi-reviewer approval;
- drug monographs, DDI engine or clinical calculators;
- automated laboratory interpretation;
- AI analysis;
- regulatory ADR submission;
- public API or native application.

## 5. Test matrix

| Area | Minimum proof |
| --- | --- |
| Authorization | Student ownership, assigned reviewer, role boundaries and cross-institution denial |
| Form versioning | Draft and each immutable submission retain the exact form version |
| Validation | Mandatory, conditional, date/unit and de-identification checks |
| Sync | Idempotent save, retry, stale lock conflict and logout clearing |
| Lifecycle | Draft, submit, review, return, resubmit, approve and audited reopen |
| Audit | Status, comments, reopen and export events use bounded metadata |
| Reporting | Role-scoped progress and de-identified approved-case PDF |
| Mobile | All sections and error states usable on representative Android viewport |
| Desktop | Faculty review and administration usable on current Chrome/Edge |
| Compatibility | Existing walking-skeleton records remain readable |

## 6. Gate acceptance criteria

- [ ] No prohibited curriculum or template-builder model is introduced.
- [ ] One fixed Pharm.D form version implements the approved catalogue.
- [ ] Server validation is authoritative and unknown fields are rejected.
- [ ] Direct patient identifier fields are absent.
- [ ] Existing sync/conflict behaviour is reused.
- [ ] Student can complete the full mobile workflow.
- [ ] Assigned faculty can comment by section, return, approve and auditably reopen.
- [ ] Every submission/approval version is immutable.
- [ ] Simple rotation targets and role-scoped progress work.
- [ ] Approved records export as de-identified PDF.
- [ ] No student attachments, marks/rubrics or second reviewer are present.
- [ ] Automated checks and browser scenarios pass.
- [ ] PROJECT_STATE.md records the accepted implementation commit and evidence only after review/merge.

## 7. Remaining pre-pilot policy item

Automatic rotation-end handling is intentionally not implemented until faculty chooses whether unfinished drafts receive a grace period, become read-only or require an explicit extension. Existing authorization must not be silently weakened to guess this policy.
