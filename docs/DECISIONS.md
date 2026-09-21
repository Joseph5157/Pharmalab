# Pharmalab Decision Log

## 1. How to use this file

Record durable product and engineering decisions here. Do not silently change an accepted decision in code. Add a new decision that supersedes it, including the reason and impact.

Statuses:

- **Accepted** — implementation may rely on it.
- **Provisional** — current direction; institutional validation still required.
- **Open** — blocks or may materially change implementation.
- **Deferred** — intentionally outside Phase 1.

## 2. Accepted decisions

| ID | Decision | Reason / consequence |
|---|---|---|
| DEC-001 | Use Laravel + Inertia.js + Vue 3 + TypeScript + PostgreSQL. | Delivers an app-like interface while retaining a cohesive server-side authorization and relational domain model. |
| DEC-002 | Build a modular monolith for Phase 1. | Avoids premature API/microservice overhead; domain services can be exposed later. |
| DEC-003 | Phase 1 centers on clinical case documentation and faculty review. | Provides a valuable complete learning loop before knowledge-engine or AI features. |
| DEC-004 | The record is a student educational case, not a live patient EHR. | UI, permissions and language must not imply clinical ordering or official medical-record status. |
| DEC-005 | Use de-identification by design. | Standard forms will not collect patient name, full DOB, phone, address, government ID or hospital MRN. |
| DEC-006 | Use a task-list case workspace with focused section screens. | Students can document in the order clinical information becomes available and resume easily. |
| DEC-007 | Submitted and approved versions are immutable. | Faculty decisions must remain tied to exactly what was reviewed. |
| DEC-008 | Authorization is enforced with Laravel policies and scoped queries. | Vue capability flags are presentation helpers only. |
| DEC-009 | Offline support is limited to de-identified drafts. | Server remains source of truth; submission, return and approval require online/current state. |
| DEC-010 | Do not rely solely on browser Background Sync. | Browser support is incomplete; foreground/manual retry is required. |
| DEC-011 | Use structured records for labs, vitals, medicines, diagnoses and interventions. | Supports comparison, validation, reporting and future standards mapping. |
| DEC-012 | Be FHIR-aligned where useful, but do not claim Phase 1 FHIR conformance. | Full conformance requires profiling, terminology and interoperability testing. |
| DEC-013 | Use a faculty-approved local intervention taxonomy initially. | Full PCNE classification reuse requires licensing/permission review for commercial use. |
| DEC-014 | AI-generated clinical recommendations are deferred. | Normal Phase 1 workflow and governed data must be established first. |
| DEC-015 | Comprehensive drug monographs and DDI checking are deferred. | Content sourcing, licensing, clinical governance and update processes need separate work. |

## 3. Provisional decisions requiring validation

| ID | Provisional decision | Validation needed |
|---|---|---|
| DEC-101 | One primary preceptor reviews each case in Phase 1. | Confirm whether co-review or second approval is required. |
| DEC-102 | Returned cases unlock only sections marked for correction, with faculty option to unlock all. | Confirm preferred academic practice and usability. |
| DEC-103 | Case demographics use age/age unit or age band rather than DOB. | Confirm curriculum and hospital policy. |
| DEC-104 | Student can enter free-text medicine/test names with controlled suggestions. | Confirm required terminology and local data availability. |
| DEC-105 | ADR/Naranjo is P1 rather than walking-skeleton scope. | Confirm whether every submitted case requires it. |
| DEC-106 | Counselling and monitoring is a separate optional section. | Confirm whether it belongs inside SOAP Plan or is independently graded. |
| DEC-107 | Admin may see case content only through an explicit academic permission. | Confirm institutional administrative oversight policy. |
| DEC-108 | Rotations retain the template/rubric versions selected at activation. | Confirm how mid-rotation curriculum changes should apply. |
| DEC-109 | Section-level revision comparison is sufficient for Phase 1. | Test with faculty on medication/lab repeatable records. |

## 4. Open institutional decisions

These should be answered during the Phase 0 workshop.

| ID | Question | Why it matters |
|---|---|---|
| OPEN-001 | Which exact fields are required for Pharm.D, M.Pharm and B.Pharm cases? | Determines migrations, templates and completeness rules. |
| OPEN-002 | Which case sections are optional or not applicable by rotation? | Determines task-list and submission behavior. |
| OPEN-003 | What identifiers/demographics may be recorded under institutional and hospital policy? | Determines privacy boundaries and field design. |
| OPEN-004 | Who may review, return, approve and reopen cases? | Determines role/permission matrix. |
| OPEN-005 | Is a second reviewer or signature required? | May change the lifecycle and schema materially. |
| OPEN-006 | What is the rubric, scoring scale and pass rule? | Determines rubric versioning and approval preconditions. |
| OPEN-007 | Can a student edit all sections after return or only flagged sections? | Determines correction authorization. |
| OPEN-008 | What case counts and categories are required per rotation? | Determines progress calculations. |
| OPEN-009 | What happens to unfinished cases when a rotation ends? | Determines grace period and locking. |
| OPEN-010 | Which reports/exports are mandatory and who may access them? | Determines reporting scope and privacy review. |
| OPEN-011 | Is ADR/Naranjo mandatory, optional or only for suspected ADR cases? | Determines Phase 1 priority and completeness rules. |
| OPEN-012 | Which low-risk calculators or approved references are required? | Determines reference-module scope. |
| OPEN-013 | What are retention, backup and device-draft clearing periods? | Determines infrastructure and offline policy. |
| OPEN-014 | Which browsers/devices and connectivity conditions are typical in wards? | Determines PWA test matrix and fallbacks. |
| OPEN-015 | Is multilingual UI/content required for the pilot? | Affects design system, content and database fields. |

## 5. Deferred decisions

| ID | Decision area | Revisit when |
|---|---|---|
| DEF-001 | Drug-monograph API versus curated database | Phase 1 workflow is stable and licensing budget is known. |
| DEF-002 | DDI data provider and clinical governance | Licensed data and pharmacist governance team are available. |
| DEF-003 | AI provider, model, retrieval and evaluation | Sufficient governed cases exist and safety/evaluation protocol is approved. |
| DEF-004 | Public API / native applications | A second client or external institution has a confirmed need. |
| DEF-005 | Multi-tenant SaaS subscriptions and super-admin | Pilot proves institutional value and commercial model is approved. |

## 6. Decision-change template

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
