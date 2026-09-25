# Pharm.D Clinical Case Form — Phase 1 Implementation Baseline

**Status:** Product-owner/faculty decisions accepted for implementation planning  
**Institutional boundary:** Privacy, hospital-policy and pilot sign-off remain required before production use  
**Gate:** DIRECT-DOCUMENTATION-IMPL-01  
**Decision date:** 25 September 2026

## 1. Purpose and boundary

This document defines the first fixed native Pharm.D clinical-case form for Pharmalab. It extends the accepted walking skeleton and sync protocol without adding a curriculum engine, dynamic form builder or hospital EMR.

The workflow is:

> Student starts a de-identified case inside an assigned rotation → documents the case → submits online → assigned faculty reviews → returns or approves → student corrects and resubmits when returned → approved record appears in progress and portfolio views.

The form is educational. It is not the hospital's legal medical record and must not be used to identify a patient.

## 2. Accepted product decisions

| Area | Accepted Phase 1 decision |
| --- | --- |
| Record start | Student starts a case only inside an active authorized rotation. |
| Reviewer | The assigned faculty/preceptor reviews, returns and approves the case. No second reviewer is required in Phase 1. |
| Mandatory documentation | Case context, de-identified demographics, history and diagnosis, allergy status, medication chart, relevant vitals/investigations, SOAP and submission attestation. |
| Clinical activities | Intervention, ADR and counselling details are conditional; a monitoring plan or justified not-applicable reason is required in SOAP Plan. |
| Corrections | A returned case unlocks all student sections; faculty-flagged sections are highlighted. |
| Feedback | Overall feedback plus section-level comments. Field-level annotations are deferred. |
| Assessment | No marks, grading rubric or pass score in Phase 1. |
| Reopening | Assigned faculty may reopen an approved case only with a mandatory reason and audit event. |
| Case targets | Faculty configures simple case-count/category targets per rotation; no curriculum engine is introduced. |
| Attachments | No student PDF, image or other file uploads in Phase 1. |
| Retention | Approved educational cases are retained through course completion plus one year. |
| Reporting | On-screen progress plus de-identified PDF export. |
| Report access | Students access their own records; assigned faculty access their students; administrators access records within their institution. |
| Pilot language | English. |
| Pilot devices | Current Android Chrome on phones/tablets and current Chrome/Edge on desktop. |
| B.Pharm | B.Pharm practical records are a separate next gate after the Pharm.D clinical module. |

## 3. Mobile student journey

1. My Cases
2. Start New Case
3. Case Profile
4. History and Diagnosis
5. Vitals and Investigations
6. Medication Chart
7. SOAP Note
8. Conditional Clinical Activities
9. Submission Review and De-identification Attestation
10. Submit Online
11. Feedback and Correction
12. Approved Portfolio Record

The editor shows one logical section at a time, visible progress, persistent save state and explicit offline, retry and conflict states. Opening an optional activity does not create a saved record.

## 4. Field catalogue

Legend:

- **M:** mandatory before submission
- **C:** conditionally mandatory
- **O:** optional

### 4.1 Case context and privacy

| Field | Level | Control and rule |
| --- | --- | --- |
| Educational Case ID | M | Generated and read-only; never a hospital identifier. |
| Rotation, site and ward/unit | M | Derived from the authorized rotation assignment. |
| Care setting | M | Inpatient, Outpatient, Emergency or Other. |
| Case documentation date | M | Cannot be a future date; display DD/MM/YYYY and store ISO date. |
| Hospital day at first review | O | Positive whole number; preferred over storing an admission identifier. |
| Information source | M | Case sheet, patient interview, caregiver interview, ward-round discussion or combination. |
| Age | M | Positive value with days, months or years; no full date of birth. |
| Sex as recorded | M | Male, Female, Intersex or Unknown. |
| Weight | C | Required when relevant to dosing, paediatrics, renal assessment or the student's plan. |
| Height | O | Centimetres. |
| Pregnancy/lactation status | C | Shown only when clinically relevant. |
| De-identification attestation | M | Confirmed only on the online submission review screen. |

Standard forms must not collect patient name/initials, UHID/MRN/IP/OP number, bed number, Aadhaar, phone, address, email, full date of birth or patient photograph.

### 4.2 History, diagnosis and allergies

| Field | Level | Rule |
| --- | --- | --- |
| Chief complaints | M | Repeatable complaint plus duration. |
| History of present illness | M | Structured long text with de-identification guidance. |
| Diagnosis/active problem | M | At least one; free text in Phase 1 with provisional, confirmed or comorbidity type. |
| Past medical history | M | Enter relevant history or explicitly select none known/not available. |
| Past surgical history | O | Enter when relevant. |
| Medication history | M | Repeatable medicines or explicit no previous/current medicines documented. |
| Adherence status | C | Adherent, partially adherent, non-adherent or unable to assess when medication history exists. |
| Family history | O | Relevant information only. |
| Tobacco/alcohol/substance history | O | Relevant information only. |
| Relevant examination findings | O | Record findings from the case sheet or ward discussion and identify the source. |
| Allergy status | M | No known allergy, Known allergy or Unknown. |
| Allergy substance and reaction | C | Required when Known allergy is selected; severity/source optional when unknown. |

A blank allergy value must never mean no known allergy.

### 4.3 Vitals

The section must contain at least one relevant observation or a reason that vitals were unavailable/not clinically relevant.

| Observation | Default unit |
| --- | --- |
| Blood pressure | mmHg |
| Pulse/heart rate | beats/min |
| Respiratory rate | breaths/min |
| Temperature | °C |
| Oxygen saturation | % |
| Weight | kg |
| Height | cm |
| Blood glucose | mg/dL, with another reported unit allowed |

Each entry stores the observation type, value, unit, case date/day, optional time, source and note.

### 4.4 Investigations

The section must contain at least one relevant result or a reason that investigations were unavailable/not clinically relevant.

Each repeatable investigation contains:

- test/investigation name;
- result type: numeric, qualitative or narrative/report;
- result value;
- unit, or Unit not stated;
- hospital-provided reference range, or Reference range not provided;
- hospital-reported flag: Low, Normal, High, Critical or Not stated;
- case date/day and optional time;
- student clinical interpretation, optional.

Provide quick selections for common haematology, renal, hepatic, electrolyte, glucose, coagulation, thyroid, lipid, microbiology, cardiac, imaging and pulmonary-function investigations, plus Custom.

Do not apply a single universal reference range and do not generate automatic clinical advice from a laboratory value.

### 4.5 Medication chart

Each deliberately added medicine row contains:

| Field | Level | Rule |
| --- | --- | --- |
| Generic medicine name | M | Free text with future suggestion support. |
| Brand name | O | Useful for Indian prescriptions but not a substitute for generic name. |
| Indication | M | Free text or Indication unclear. |
| Dose amount and unit | M | Positive numeric amount where applicable plus controlled/common unit or Other. |
| Dosage form | O | Tablet, capsule, injection, solution or Other. |
| Route | M | Controlled value plus Other. |
| Frequency | M | Display full meaning, for example Once daily (OD), while storing the canonical meaning. |
| Start case-day/date | O | Must not be after stop date. |
| Stop case-day/date | C | Required when status is Stopped or Completed. |
| Status | M | Active, Stopped, On hold, Completed or PRN. |
| PRN indication | C | Required for PRN medicines. |
| Administration instructions/notes | O | De-identified text. |

At least one medicine row is required unless No current medicines documented is explicitly selected.

There is no interaction checker, dose-adjustment recommendation or drug-monograph advice in this gate.

### 4.6 SOAP note

All four SOAP areas are visible and mandatory.

#### Subjective

- presenting symptoms and concerns;
- relevant patient/caregiver-reported history;
- medication use and adherence information.

#### Objective

- relevant examination findings;
- references to structured vitals and investigations;
- relevant medication-chart facts.

Do not require the student to duplicate every structured value in prose.

#### Assessment

- at least one clinical/pharmaceutical problem;
- evaluation of current therapy;
- drug-related-problem status: none identified, identified or unable to assess;
- clinical reasoning.

When a drug-related problem is identified, select one or more bounded categories: untreated indication, medicine without indication, ineffective medicine, dose too low, dose too high, ADR, interaction, non-adherence, duplication, administration problem, monitoring required or Other.

These are student assessments for faculty review, not automated alerts.

#### Plan

- therapy recommendation or Continue current therapy with reasoning;
- monitoring parameter and interval, or justified not applicable;
- follow-up plan;
- counselling consideration;
- communication with the healthcare team when performed.

### 4.7 Conditional clinical activities

#### Pharmacist intervention

Required when a drug-related problem or therapy-change recommendation is documented.

Fields: problem, recommendation, recipient, communication method, case date/day, outcome and follow-up. Outcomes are Accepted, Partially accepted, Not accepted, Pending or Not communicated.

#### Suspected ADR

The student first answers Suspected ADR: Yes, No or Unable to assess. ADR details are required only for Yes.

Fields: event/reaction, onset/stop case date/day, suspected medicine, dose/route/frequency, concomitant medicines, relevant tests, action taken, seriousness, outcome and dechallenge/rechallenge if known.

No regulatory submission is made from Pharmalab in Phase 1. Naranjo and WHO-UMC automation are deferred.

#### Patient counselling

Every case records a status: Performed, Planned, Not indicated or Unable to perform.

When Performed or Planned is selected, capture counselling topics, medicine purpose, administration, adherence, precautions, important adverse effects, storage, lifestyle/follow-up and whether understanding was checked.

#### Monitoring follow-up

A monitoring plan is mandatory in SOAP Plan. A separate follow-up result is created only when the student actually follows the case.

## 5. Validation policy

### Draft saves

- Incomplete drafts may be saved.
- Client validation provides immediate guidance.
- The server remains authoritative.
- Autosave uses the accepted idempotency, optimistic-locking and conflict protocol.
- Optional child records are created only after a deliberate student save.

### Submission checks

Submission is blocked when:

- a mandatory section is incomplete;
- no diagnosis/active problem is entered;
- allergy status is unanswered;
- the medication chart is empty without an explicit no-medicines answer;
- vitals or investigations contain neither an entry nor an allowed reason;
- any SOAP area is empty;
- an identified drug-related problem lacks assessment details;
- an intervention-triggering recommendation lacks intervention status/details;
- Suspected ADR is Yes without the ADR section;
- counselling status is unanswered;
- SOAP Plan lacks a monitoring plan or justified not-applicable reason;
- the de-identification attestation is not confirmed.

### Technical checks

- Future clinical dates are rejected.
- Stop dates cannot precede start dates.
- Numeric results must parse as numbers.
- Numeric investigations require a unit or Unit not stated.
- SpO₂ accepts 0–100 only.
- Systolic and diastolic pressure are entered together.
- Unusual but possible physiological values trigger confirmation rather than an automatic clinical conclusion.
- Hospital-specific laboratory reference ranges are not overridden.
- Direct-identifier patterns such as Aadhaar, phone, email and long hospital-record numbers are blocked or flagged before submission.
- Unknown request fields are rejected by the server.
- User-facing dates use DD/MM/YYYY; stored dates use ISO format; time uses 24-hour display.

## 6. Faculty review and correction

The assigned faculty workspace shows the exact immutable submitted version, section completion and version history.

Faculty may:

- add optional overall feedback;
- flag one or more sections and add section-level comments;
- return a case, requiring at least one actionable section comment/reason;
- approve the exact submitted version;
- reopen an approved case with a mandatory reason.

After return or reopen, every student section is editable and the flagged sections are highlighted. Resubmission creates a new immutable snapshot and preserves the prior approved/submitted evidence.

No field-level annotations, marks, rubrics, second reviewer or multi-signature workflow are included.

## 7. Progress, targets, export and retention

- Faculty may configure simple case-count and category targets for a rotation without curriculum tables or automatic curriculum assignment.
- Students see their own target progress.
- Assigned faculty see progress for their students.
- Institution administrators see institution-scoped progress.
- Approved cases may be exported as a de-identified PDF.
- Export access follows the same role and institution scope as the record.
- Approved educational cases are retained through course completion plus one year.
- Student device drafts clear on logout under the accepted sync baseline.
- No student attachments are accepted.

## 8. India-first usability

- English-only pilot.
- Android phone/tablet layouts are the primary student target.
- Current Chrome/Edge desktop layouts support faculty and administration.
- Touch targets, section navigation and visible save state support ward-round use.
- Generic name is required and brand name is optional.
- Familiar OD/BD/TDS/QID labels are expanded to full English wording.
- Common Indian laboratory units are available, but the unit and reference range reported by the hospital remain authoritative.
- No cold-offline launch promise; drafts recover after reconnect under the accepted sync boundary.

## 9. Versioning, authorization and audit

- The form version is fixed when a case starts.
- Later form changes do not rewrite existing drafts or snapshots.
- Submitted and approved snapshots are immutable.
- Students access only their records within an authorized rotation.
- Faculty access only records covered by their assignment relationship.
- Administrators remain institution-scoped.
- Start, save, submit, review, comment, return, resubmit, approve, reopen and export actions are auditable.
- Audit payloads contain identifiers and bounded metadata, not full clinical narratives.

## 10. Explicit exclusions

- Curriculum versions, subjects, semesters and automatic curriculum assignment
- Dynamic form builder
- B.Pharm practical form implementation
- Patient/hospital identifiers
- Student file uploads
- Drug monographs and interaction engine
- Clinical calculators
- Automated laboratory interpretation
- AI recommendations
- Regulatory ADR submission
- Marks, rubrics, second reviewer and multi-signature approval
- Public API and native applications

## 11. Implementation readiness

The product decisions needed to begin DIRECT-DOCUMENTATION-IMPL-01 are accepted.

Before production pilot, the institution must still validate the de-identification policy, hospital permission, backup/restore procedure and supported-device test results. The handling of unfinished drafts when a rotation ends remains an explicit pilot-policy decision and must not be silently hard-coded.

## 12. Authoritative research references

- [PCI Pharm.D Regulations 2008](https://pci.gov.in/documents/1265/PharmD_regu_2008_ktXB7Zo_m43ifcd.pdf)
- [PCI PDR-2008 compliance guidance](https://www.pci.gov.in/media/documents/PDR-2008.pdf)
- [Digital Personal Data Protection Act, 2023](https://www.meity.gov.in/static/uploads/2024/06/2bf1f0e9f04e6fb4f8fef35e82c42aa5.pdf)
- [ABDM Health Data Management Policy](https://abdm.gov.in/static/media/health_management_policy_bac9429a79.80f74bc3e039c00acd4f.pdf)
- [ABDM FHIR vital-sign profile](https://nrces.in/ndhm/fhir/r4/StructureDefinition-ObservationVitalSigns.html)
- [PvPI suspected ADR reporting form](https://ipc.gov.in/images/ADR-Reporting-Form1.3.pdf)
