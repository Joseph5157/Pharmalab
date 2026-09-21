# ADM-FOUNDATION-01 Implementation Record

## Purpose

This milestone supplies the minimum institution-scoped academic and placement administration required by the walking skeleton. It does not define clinical documentation, review, reporting, templates, or advanced scheduling.

## Delivered model

- Programme → academic cohort
- Clinical site → department → ward
- Institution-owned student and faculty/preceptor accounts
- Rotation linked to a programme, optional cohort, clinical site, optional department, optional ward, date range, and simple lifecycle status
- One rotation assignment per student and rotation, linked to one primary preceptor
- Append-only-style audit creation for configuration, account, rotation, assignment, and status mutations

All domain records carry `institution_id`. PostgreSQL foreign-key access paths are indexed, historical parents use restrictive deletion, and externally routed domain IDs use ULIDs. Route model binding is constrained by the existing institution global scope, while policies and scoped validation provide independent server-side authorization.

## Administrator workspaces

- `/admin/academic` — programmes and cohorts/batches
- `/admin/clinical-sites` — hospitals/clinical sites, departments, and wards
- `/admin/people` — student/faculty account creation and activation status
- `/admin/rotations` — rotations, lifecycle status, and student/preceptor assignments

The screens use the existing Laravel/Inertia/Vue shell, components, typography, color tokens, mobile navigation, and responsive layout patterns.

## Significant implementation decisions

1. Referenced configuration is deactivated rather than deleted; no destructive administration route is exposed.
2. Codes are normalized to uppercase before uniqueness validation and persistence.
3. Hierarchical references are validated together: cohort/programme, department/site, and ward/site/department must agree.
4. Assignment validation requires active users with the correct student and faculty roles in the administrator's institution.
5. Saving the same student and rotation again updates the primary preceptor and reactivates the existing assignment rather than creating a duplicate.
6. Audit metadata contains identifiers, statuses, and safe codes only. Account passwords are never included.
7. Rotation overlap detection is intentionally deferred as advanced scheduling; the database only prevents duplicate students inside one rotation.

## Scope reconciliation

The broader backlog mentions CSV roster import and assignment notifications, while the schema direction anticipates case-template/rubric references and requirements configuration. `PROJECT_STATE.md` explicitly narrows this milestone, so those items remain deferred. The reconciliation is also recorded in `docs/DECISIONS.md`.

## Deferred

- CSV import and notifications
- Rotation overlap rules and advanced scheduling
- Multiple/co-preceptor assignments
- Case templates, rubrics, requirements and reports
- Clinical cases, SOAP notes and faculty review
