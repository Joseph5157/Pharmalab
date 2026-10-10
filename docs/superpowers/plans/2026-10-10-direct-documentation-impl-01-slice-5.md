# DIRECT-DOCUMENTATION-IMPL-01 — Slice 5 implementation plan: targets, progress, PDF and retention

**Spec:** [`docs/superpowers/specs/2026-09-28-direct-documentation-impl-01-slice-5-design.md`](../specs/2026-09-28-direct-documentation-impl-01-slice-5-design.md) (with its §13 corrections).
**Branch:** `direct-documentation-impl-01-slice-5`, cut from `main` after this plan is committed.
**Baseline:** `main` at the Slice 5 design-spec commit (`eb13f51`) plus this plan.
**Exit evidence (master plan, unchanged):** progress counts match authorized approved cases; the PDF contains the educational Case ID and omits prohibited identifiers; cross-tenant report/export tests pass.

## 0. Plan-review corrections (all seven applied below)

| # | Correction | Where it lands |
| - | ---------- | -------------- |
| C1 | **Target-authorization wiring.** A `manage(User, Rotation)` ability on `RotationCaseTargetPolicy` is never reached by `Gate::authorize('manage', $rotation)`: Laravel resolves the policy from the *argument's* class, which is `Rotation` → `RotationPolicy` (admin-only). Every call site passes the class explicitly: `Gate::authorize('manage', [RotationCaseTarget::class, $rotation])`. `{target}` routes use `scopeBindings()` so a target id from another rotation 404s instead of being edited through the wrong rotation's authorization. | Task 1 |
| C2 | **Concurrent duplicate targets.** The pre-check returns a clean 422 for the ordinary case, but two simultaneous requests can both pass it. The insert/update is wrapped so `Illuminate\Database\UniqueConstraintViolationException` from either partial unique index is converted into the same 422 message — never a 500. A test forces the race by bypassing the pre-check. | Task 1 |
| C3 | **Exact-path migration test.** The new migration is exercised by `--path` with its exact filename (the convention in `CaseMedicationContextNullableMigrationTest`), proving `down()` drops both raw partial indexes and `up()` re-creates them, and that both indexes reject duplicates at the database layer, independent of application code. | Task 1 |
| C4 | **Admin filter data.** `GET /admin/progress` must send the institution's rotation list (`id`, `name`) for the filter control, plus the selected rotation id; the `rotation` query parameter is validated against the institution's rotations, so an unknown or cross-tenant id is a validation error, never a silent empty page. | Task 4 |
| C5 | **No broken export links before routes exist.** Export buttons (student case page, faculty review page, admin drill-down) are added in the *same task* that registers the export routes and regenerates Wayfinder bindings — never earlier. The admin drill-down in Task 4 lists approved cases without a link; Task 5 adds the link. | Tasks 4–5 |
| C6 | **Concrete PDF-content testing.** dompdf compresses text streams, so assertions against raw PDF bytes are meaningless. `CaseExportPdfService` exposes `html()` (the exact Blade output fed to dompdf) and tests assert concrete strings in it: case number, version, form version, snapshot clinical values, `record_metadata` names, approver name; plus the absence of ULIDs and of a post-approval live-data change. The HTTP test asserts `application/pdf`, a ULID-free `Content-Disposition` filename and a body starting with `%PDF-`. | Task 5 |
| C7 | **Accurate "approved by" wording.** `clinical_cases` has `approved_at` but **no** `approved_by`; the approver is `case_versions.approved_by` (an immutable user id). The *display name* printed for it is looked up live from `users`, so it can change if the user is renamed. The spec and PDF say exactly that; the PDF prints approval time from `case_versions.approved_at`. | Task 5, spec §13 |

## Task 1 — Faculty rotation targets and category enforcement

1. Migration `2026_10_10_000000_create_rotation_case_targets_table.php`: ULID `id`; `institution_id`, `rotation_id` (restrict on delete); `category` nullable string(80); `category_key` `storedAs('lower(trim(category))')` (works on PostgreSQL and SQLite at create time); `target_count` unsigned int; `created_by` → `users` (restrict); timestamps. Raw `CREATE UNIQUE INDEX … WHERE category IS NULL` on `(rotation_id)` and `… WHERE category IS NOT NULL` on `(rotation_id, category_key)`; `down()` drops both indexes then the table.
2. `RotationCaseTarget` model (`BelongsToInstitution`, `HasUlids`; `category_key` never fillable); `Rotation::targets()` has-many (also the scoped-binding relation).
3. `RotationCaseTargetPolicy`: `manage(User, Rotation)` = same institution + Faculty + an **active** `RotationAssignment` on that rotation with `primary_preceptor_id = user`. `view(User, Rotation)` = `manage` or same-institution Administrator. Wired per C1.
4. `RotationCategoryMatcher` service (one normalization rule, used by both requests and the progress service): `options(rotationId)` returns configured category strings; `canonical(rotationId, input)` returns the target row's own casing or `null` when no match.
5. `Faculty\RotationTargetController`: `index` (rotations where the faculty member has an active assignment, with targets), `store`, `update`, `destroy`. Validation: `category` nullable string max 80 (trimmed by middleware), `target_count` required integer 1–999. Pre-check duplicate → 422; C2 catch → same 422. Audit `rotation_case_target.created|updated|deleted` with rotation id, category, target count.
6. Category enforcement: `UpdateClinicalCaseContextRequest` and `StoreClinicalCaseRequest` reject a non-null `case_category` not in the rotation's configured list (422 naming the allowed values) once any category target exists; the canonical casing is what gets stored. Free text stays allowed when the rotation has none. `null` (clearing) is always allowed.
7. `ClinicalCasePresenter::caseDisplay()` (shared by the editor and `CaseContextController`, replacing the duplicated array) adds `category_options`. `CaseProfileSection.vue` renders a `<select>` of those options, or a free-text input when there are none.
8. `faculty/Rotations.vue`: per-rotation overall target and category targets, add/edit/delete. Sidebar/bottom-nav link for faculty.
9. Tests: policy wiring (assigned faculty can manage; unassigned faculty, inactive assignment, cross-institution faculty, student and admin cannot; admin can view); scoped binding (target from another rotation 404s); duplicate and whitespace/case variants → 422; second overall target → 422; forced race → 422 (C2); exact-path migration round trip + DB-level rejection (C3); category enforcement on both requests, canonical casing stored, free text when unconfigured, `null` allowed.

## Task 2 — New Case assignment selection

1. `CaseController::index` sends `activeAssignments` (`id`, rotation, site, ward names) for the student's own active assignments only.
2. `Cases.vue`: zero → button disabled with "You have no active rotation assignment. Contact your administrator."; one → posts that id; many → a picker that must be resolved before posting. The draft/returned row action opens `/student/cases/{id}/edit` (the old `/soap` GET was retired in Slice 2C and now 405s).
3. Tests: prop contents (own, active only); `StoreClinicalCaseRequest` still rejects spoofed, inactive and cross-institution assignment ids (regression for the "no backend change" claim).

## Task 3 — Progress views

1. `RotationProgressService::forStudent`, `forFaculty`, `forInstitution(institutionId, ?rotationId)` — one shared counting routine per assignment: approved cases only; overall count vs the `category IS NULL` target; per-category counts matched by normalized key; a category with approved cases but no target appears as "no target set"; uncategorised approved cases count toward overall only.
2. `Student\ProgressController`, `Faculty\ProgressController`, `Admin\ProgressController` + `student/Progress.vue`, `faculty/Progress.vue`, `admin/Progress.vue`. Admin: rotation filter data and validated `rotation` parameter (C4); with a rotation selected, its approved cases (case number, student, category, approved date) — **no export link yet** (C5).
3. Dashboards: student "Active rotation", "Cases this rotation" (approved / overall target) and "Awaiting feedback" from real data; faculty "Waiting for review" and "Assigned students" from real data ("Median turnaround" stays a labelled placeholder — not in the master plan's scope). Navigation links wired for the new pages and the existing Cases/Portfolio/Review queue placeholders.
4. Tests: approved-only counting against all four other statuses; two students on one rotation counted independently; unset-target category kept; student/faculty/admin scoping, including cross-institution; admin filter validation (C4).

## Task 4 — Approved-case PDF export

1. `composer require barryvdh/laravel-dompdf`.
2. `SubmitCase::buildSnapshot()` adds `record_metadata` (`case_number`, `rotation_name`, `clinical_site_name`, `ward_name`), frozen at submission.
3. `ClinicalCasePolicy::exportPdf` — Approved + same institution; student owner, assigned faculty, or same-institution admin.
4. `CaseExportPdfService`: approved version = latest version with `approved_at`; clinical content only from `snapshot`; header from `case_number`, `version_number`, snapshot `form_version`, `case_versions.approved_at`, approver name looked up live by `case_versions.approved_by` (C7); `record_metadata` names, falling back to live relation names for legacy versions (labelled in the PDF). `html()` and `download()` (C6). Plain Blade view; no branding (OPEN-016).
5. Routes `GET student/cases/{case}/export`, `faculty/reviews/{case}/export`, `admin/cases/{case}/export`; audit `clinical_case.exported` with `case_id`, `version_number`, `case_version_id`. Export buttons on `CaseShow.vue`, `CaseReview.vue` and the admin drill-down, in this task only (C5). Regenerate Wayfinder.
6. Tests: authorization matrix (owner, other student, assigned/unassigned/cross-tenant faculty, same/cross-tenant admin; every non-Approved status denied); exactly one audit event with all three ids; concrete HTML content and drift immunity (mutate a live `CaseVital` after approval); `record_metadata` path and legacy fallback path; no ULIDs in HTML or filename; PDF response headers and `%PDF-` signature.

## Task 5 — Retention (documentation only)

DEC-033 and OPEN-017 are already recorded in `docs/DECISIONS.md`. No schema, constant or deletion job. Verify the records are intact; the acceptance record in `PROJECT_STATE.md` restates that retention is policy-only.

## Task 6 — Final gate and merge

1. `composer ci:check` (frontend check + types, Pint, PHPStan, full test suite) and production build.
2. Production-like check: migrations and the full suite against local **PostgreSQL 16** (CI runs SQLite only), then a headless-Chromium walkthrough at 390 × 844 (targets → categorised case → submit → approve → progress → PDF export). Railway staging is not reachable from the implementing environment; this replaces it and is recorded as such.
3. PR → CI green → lean review (one fresh-eyes pass over the whole diff) → fix findings → merge → `PROJECT_STATE.md` acceptance record.

## Execution ledger

Deviations from this plan, recorded as rulings during execution.

1. **Task order.** Executed as planned: targets (Task 1), New Case (Task 2), progress (Task 3), PDF (Task 4), retention (Task 5, no change needed — DEC-033 and OPEN-017 were already recorded on `main`), final gate (Task 6).
2. **C2 test shape.** The forced race inserts the competing row on the same connection inside the request's transaction, so it rolls back with the 422; the test asserts the request's own row and audit event were never kept, and a mutation (catching the wrong exception type) makes it fail with the raw constraint violation.
3. **Admin filter (C4).** An invalid `rotation` redirects to the unfiltered `/admin/progress` with the error (not `back()`), so a bad bookmarked URL cannot redirect to itself.
4. **Dependency install.** Packagist metadata is unreachable from the implementing sandbox, so `barryvdh/laravel-dompdf` v3.1.2 and its dependencies (dompdf v3.1.6, php-font-lib 1.0.2, php-svg-lib 1.0.2, masterminds/html5 2.11.0, sabberworm/php-css-parser v9.5.0) were added to `composer.lock` from each package's own `composer.json` at the exact tagged commit, with a recomputed content hash; `composer validate` passes and `composer install` from the lock succeeds. CI's `composer install` is the independent check.
5. **PDF value formatting.** Stored option codes (`no_known_allergy`) print as words (`No known allergy`); headings never break from their content across pages.
6. **Pre-existing bug fixed (found by the walkthrough).** Since Slice 2B the editor passed the whole case context — including the availability fields owned by other sections — into the Case Profile section, which sent them back; the context endpoint rejects unknown fields, so the first Case Profile edit after opening the editor returned 422. Fixed by excluding those fields from the Case Profile sync body. Frontend-only, so PHPUnit cannot cover it; the walkthrough asserts the 200.
7. **Pre-existing bug fixed (planned).** The case list's draft/returned action opened the `/soap` page retired in Slice 2C (405); it now opens the editor.
8. **Production-like check.** Railway staging is not reachable from the implementing environment. Instead: the full suite against local PostgreSQL 16 — 429 of 432 passing, 2 skipped, the only error being a pre-existing JSON-equality assertion in `AcademicAdministrationTest` (from `ADM-FOUNDATION-01`, untouched here; Postgres has no `json = text` operator) — and a 14-check headless-Chromium walkthrough at 390 × 844 on Postgres: faculty targets and duplicate rejection → New Case with one assignment → category dropdown and autosave → submit → approve → student/faculty/admin progress and dashboard → student and admin PDF download → invalid admin filter.

**Final gate (SQLite, as CI):** `npm run check`, `npm run types:check`, Pint, PHPStan (0 errors), 432 tests — 430 passed, 2 skipped, 1,602 assertions; production build clean.
