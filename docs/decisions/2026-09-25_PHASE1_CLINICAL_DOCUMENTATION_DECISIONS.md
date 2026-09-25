# Phase 1 Clinical Documentation Decisions

**Status:** Accepted product baseline  
**Date:** 25 September 2026  
**Applies to:** DIRECT-DOCUMENTATION-IMPL-01  
**Supersedes:** Unresolved defaults in the 24 September Pharm.D candidate form

## 1. Accepted decisions

| ID | Decision | Accepted outcome | Reason |
| --- | --- | --- | --- |
| P1-CD-001 | Record start | Student starts a case inside an active authorized rotation. | Supports ward-round work without requiring faculty to create every case. |
| P1-CD-002 | Reviewer | Assigned faculty/preceptor is the single Phase 1 reviewer. | Matches the existing assignment model and avoids premature multi-reviewer complexity. |
| P1-CD-003 | Mandatory documentation | De-identified context/demographics, history/diagnosis/allergy, medication chart, relevant vitals/investigations, SOAP and attestation. | Provides reviewable clinical evidence while avoiding an EMR-sized record. |
| P1-CD-004 | Clinical activities | Intervention, ADR and counselling are conditional; monitoring plan is required in SOAP or justified not applicable. | Prevents fabricated activities while preserving PCI-aligned learning. |
| P1-CD-005 | Correction scope | Returned/reopened cases allow editing of all sections; flagged sections are highlighted. | Avoids complex field locking and permits clinically connected corrections. |
| P1-CD-006 | Feedback | Overall feedback plus section-level comments; no field-level annotations. | Gives actionable correction guidance with manageable Phase 1 UX. |
| P1-CD-007 | Assessment | No marks, rubric or pass score. | Pilot the documentation/review loop before defining academic scoring. |
| P1-CD-008 | Reopening | Assigned faculty may reopen an approved case with mandatory reason and audit event. | Supports genuine correction while preserving accountability. |
| P1-CD-009 | Case targets | Faculty configures simple targets per rotation. | Enables progress without curriculum tables or automatic assignment. |
| P1-CD-010 | Attachments | No student uploads. | Reduces privacy, malware, storage and retention risk. |
| P1-CD-011 | Retention | Course completion plus one year. | Provides an academic evidence period without indefinite storage. |
| P1-CD-012 | Reports | On-screen progress plus de-identified PDF. | Covers pilot learning and record-sharing needs with bounded export scope. |
| P1-CD-013 | Report access | Student-own, assigned-faculty and institution-admin scopes. | Mirrors existing authorization and tenant boundaries. |
| P1-CD-014 | Language | English-only pilot. | Matches clinical documentation and keeps the pilot testable. |
| P1-CD-015 | Device matrix | Android Chrome phone/tablet and desktop Chrome/Edge. | Prioritizes likely Indian ward and faculty devices. |
| P1-CD-016 | B.Pharm boundary | Separate next gate after the Pharm.D module. | Prevents two different record domains from destabilizing the first clinical slice. |

## 2. Fixed privacy boundary

The standard educational form excludes patient name/initials, hospital record numbers, bed number, Aadhaar, contact information, address, full date of birth and photographs. A generated educational Case ID is used.

The record remains protected clinical learning information even when direct identifiers are omitted. Institution scoping, least-privilege access, immutable submitted versions, audit events and retention rules remain mandatory.

## 3. Accepted implementation boundary

The first form is native Laravel/Inertia/Vue and fixed/versioned. It is not implemented through Form.io, OpenMRS, a general template builder or a curriculum engine.

The accepted specification is maintained in docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md. The bounded delivery plan is maintained in docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md.

## 4. Decisions intentionally deferred

- Marks, rubrics and pass rules
- Field-level annotations
- Co-reviewers and second signatures
- Student attachments
- Naranjo/WHO-UMC automation
- Drug database, interaction engine and calculators
- AI-generated recommendations
- B.Pharm practical-record fields
- Multilingual interface
- iOS/iPadOS certification
- Public API and native applications

## 5. Items still requiring institutional/pilot validation

These do not block the first implementation slice but must be resolved before production pilot:

- hospital and institutional confirmation of the de-identification policy;
- backup/restore procedure and operational owner;
- behaviour for unfinished drafts when a rotation ends;
- usability and accessibility results on the accepted device matrix;
- approval of the final PDF layout and any institutional branding.
