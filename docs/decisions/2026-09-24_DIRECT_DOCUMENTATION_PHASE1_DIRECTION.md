# Direct Documentation Phase 1 Direction

**Date:** 24 September 2026  
**Status:** Accepted product direction  
**Supersedes:** `2026-09-22_PCI_CURRICULUM_AND_PHASE1_DIRECTION.md`

## Decision

The client does not require curriculum-driven activity assignment or curriculum data in the Pharmalab Phase 1 database.

Phase 1 will use the direct learning loop:

> Direct student or rotation assignment -> documentation -> submission -> faculty review -> correction/resubmission -> approval -> record history.

## Database boundary

Do not add curriculum versions, regulatory-source mappings, curriculum periods, subjects, curriculum activity requirements, student period enrolments or automatic curriculum-to-template assignments.

The programme, cohort, clinical-site, ward, rotation and rotation-assignment tables already accepted in `ADM-FOUNDATION-01` are not removed by this decision. They remain supporting administration records, but Phase 1 documentation must not depend on a new curriculum engine.

## Form boundary

Begin with fixed, faculty-approved form structures and retain a stable form-version identifier on records where necessary for historical interpretation. A dynamic template builder, publishing lifecycle, curriculum resolution and cross-institution template copying are deferred.

## Implementation direction

The next implementation gate is `DIRECT-DOCUMENTATION-IMPL-01`. It extends the accepted walking skeleton with the approved fixed clinical/practical fields and the complete return/resubmission/approval experience.

The detailed specification is [`docs/research/DIRECT_DOCUMENTATION_WORKFLOW_SPEC.md`](../research/DIRECT_DOCUMENTATION_WORKFLOW_SPEC.md).

## Reason

This keeps the product aligned with the client's requested workflow, avoids unrequested curriculum complexity and preserves a clean future extension point without storing speculative curriculum data now.
