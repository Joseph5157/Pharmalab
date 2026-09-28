# DIRECT-DOCUMENTATION-IMPL-01 — Slice 5 design: targets, progress, PDF and retention

**Status:** Design approved in chat; awaiting written-spec review before `writing-plans`.
**Decision baseline:** `docs/DECISIONS.md` DEC-032, DEC-033; `docs/implementation/DIRECT_DOCUMENTATION_IMPL_01_PLAN.md` §Slice 5; `docs/research/PHARMD_CASE_FORM_CANDIDATE_01.md` §7.
**Depends on:** Slice 4 (accepted, PR #15) — immutable `CaseVersion` snapshots, `ClinicalCasePresenter`, the audit-event conventions in `SubmitCase`/`ApproveCase`/`ReturnCase`/`ReopenCase`.
**Extends (does not replace):** `ClinicalCase`, `CaseVersion`, `RotationAssignment`, `Rotation`, `ClinicalCasePolicy`, `AuditTrail`, `Cases.vue`, `CaseProfileSection.vue`, `UpdateClinicalCaseContextRequest`.

## 1. Goal

Deliver the master plan's Slice 5 scope exactly as written:

> Add simple faculty-configured rotation case targets/categories without curriculum tables. Add student-own, assigned-faculty and institution-admin progress views. Export approved cases only as de-identified PDFs. Audit export actions. Record the course-completion-plus-one-year retention policy without implementing unsafe automatic deletion until an operational archival job is approved. Keep student attachments disabled.

Exit evidence (unchanged from the master plan): progress counts match authorized approved/submitted cases; PDF contains the educational Case ID and omits prohibited identifiers; cross-tenant report/export tests pass.

**Out of scope for this slice** (restating the master plan's exclusions explicitly): no curriculum/subject/semester tables, no automatic curriculum assignment, no dynamic template builder, no student attachments, no automatic/unsafe retention deletion, no cross-rotation aggregate targets, no backfilling historical `case_category` values to match new targets, no PDF branding/layout finalization (OPEN-016 remains open — this slice ships a plain, legible layout, not a final branded template).

## 2. Decisions settled this session

| # | Decision | Chosen |
| - | -------- | ------ |
| 1 | Category vocabulary | Faculty define the category list per rotation (via the new target rows themselves); the student's category field becomes a dropdown of that rotation's configured categories once any exist, falling back to free text otherwise. |
| 2 | What counts toward a target | Approved-only. Matches the PDF export scope and means a target is satisfied only by faculty-signed-off work. |
| 3 | Retention basis | Record the policy in `docs/DECISIONS.md` and this spec only. No per-case computed date, no application constant, no `PROJECT_STATE.md` change until Slice 5 is merged. |
| 4 | PDF library | `barryvdh/laravel-dompdf`. |
| 5 | `Cases.vue` "New case" bug | Fixed as part of this slice — zero/one/many active-assignment handling, backend validation unchanged (already correct). |
| 6 (added on review) | Nullable uniqueness | Two partial unique indexes, not one plain unique index — Postgres allows multiple `NULL`s under a normal unique constraint. |
| 7 (added on review) | Category normalization | A stored generated `category_key` column (`lower(trim(category))`) backs the partial unique index and the request-side match; display casing is preserved separately. |
| 8 (added on review) | Target scope | Per student, per rotation assignment — "Cardiology: 3" means each assigned student individually completes three approved Cardiology cases, counted via `clinical_cases.rotation_assignment_id`, not a rotation-wide pool. |
| 9 (added on review) | PDF source of truth | Clinical content only from `CaseVersion.snapshot`. Immutable header fields already on `CaseVersion`/`ClinicalCase` (`case_number`, `version_number`, `form_version`, `approved_by`, `approved_at`) are read live. Display labels that *can* drift (rotation/site/ward names) are captured into a new `record_metadata` block inside the snapshot at submission time; legacy versions predating this change fall back to current relation names, documented as a known limitation. |
| 10 (added on review) | Export audit fields | `case_id` + `version_number` (existing convention, kept for query consistency) **and** `case_version_id` (the exact immutable row, unambiguous). |

## 3. Data model

One new table, **`rotation_case_targets`**:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | ulid PK | |
| `institution_id` | FK, restrict-on-delete | tenancy column, matches every other table in this domain |
| `rotation_id` | FK, restrict-on-delete | |
| `category` | string, nullable, max 80 | `null` = the rotation's overall case-count target; a non-null value = a per-category target. Display casing is preserved as entered. |
| `category_key` | **generated column**, `lower(trim(category))`, stored | Postgres `GENERATED ALWAYS AS (...) STORED`. Backs the partial unique index and is the only thing ever compared for matching — never `category` directly. |
| `target_count` | unsigned int | |
| `created_by` | FK to `users`, restrict-on-delete | attribution only, not an ownership gate — any currently-assigned faculty on the rotation may edit/delete any target row on it (§5) |
| `created_at`/`updated_at` | timestamps | |

**Constraints**, following this codebase's established pattern for partial indexes (`2026_09_30_000002_add_singleton_unique_index_to_case_clinical_activities.php` used raw `DB::statement` for the same reason — Postgres allows multiple `NULL`s under an ordinary unique index, so a plain `unique(['rotation_id', 'category'])` would silently allow duplicate overall targets):

```sql
CREATE UNIQUE INDEX rotation_case_targets_overall_unique
    ON rotation_case_targets (rotation_id) WHERE category IS NULL;
CREATE UNIQUE INDEX rotation_case_targets_category_unique
    ON rotation_case_targets (rotation_id, category_key) WHERE category IS NOT NULL;
```

One overall target and any number of distinctly-named category targets per rotation; `Cardiology` and ` cardiology ` collide at the DB layer, not just in application code.

**Model:** `RotationCaseTarget` — `BelongsToInstitution`, `HasUlids`, `belongsTo(Rotation::class)`, `belongsTo(User::class, 'created_by')`. `category_key` is not fillable or cast — it is a DB-computed column the model never writes.

**No change to `clinical_cases.case_category`.** It remains the free-text column it already is. No foreign key is added from it to `rotation_case_targets` — matching stays by normalized string comparison at request-validation and progress-computation time (§4, §6), not a DB relationship. This keeps the design additive: every existing case and rotation with no configured targets behaves exactly as today.

## 4. Case creation and category selection

**Fixing `Cases.vue`'s "New case" button** (currently posts `{}`, which 422s against `StoreClinicalCaseRequest`'s required `rotation_assignment_id` — an unowned bug carried since Slice 2B):

- `CaseController::index()` gains a new prop: the student's active `RotationAssignment`s (id + rotation name + site/ward for display).
- `Cases.vue`: zero active assignments → the "New case" button is disabled with explanatory text ("You have no active rotation assignment. Contact your administrator."). Exactly one → submits with that assignment's id directly (today's accidental behavior, now explicit and correct). More than one → a lightweight picker (rotation name/site/ward per option) must be resolved before submitting.
- **No backend change needed here.** `StoreClinicalCaseRequest`'s existing `rotation_assignment_id` rule already scopes to `institution_id` + `student_id` + `status = active` — it already rejects a spoofed or inactive assignment id regardless of what the fixed frontend sends. The frontend was simply never sending a real id.

**Adding the missing `case_category` input.** It exists in `CaseProfileSection.vue`'s `Payload` type and in every read/display surface, but no version of the app has ever rendered an input for it. This slice adds one:

- `CaseProfileSection.vue` gains a `case_category` control. If the case's rotation (via `case_display`, extended with `rotation_id` and the rotation's configured category list) has any `category IS NOT NULL` targets, render a `<select>` of those category strings (verbatim, exact casing as configured) plus a "New case" `New case` categories are exact matches by construction. If the rotation has none configured, render today's-never-existed plain text input (`max:80`), unconstrained — a rotation that hasn't opted into targets is unaffected.
- **Server-side enforcement, not just the dropdown.** `UpdateClinicalCaseContextRequest::withValidator()` gains an `after()` check: if `RotationCaseTarget::where('rotation_id', $case->rotationAssignment->rotation_id)->whereNotNull('category')->exists()`, the submitted `case_category` (trimmed/lower-cased) must equal one of those rows' `category_key`; otherwise it fails with 422 naming the allowed values. This closes the gap a raw API call around the dropdown would otherwise leave open. When it matches, the *canonical* stored value (the target row's own `category` casing) is what gets saved to `clinical_cases.case_category` — not whatever casing the client sent — so every case sharing a category is byte-identical in that column and progress counting (§6) never needs to normalize at query time.

## 5. Faculty target configuration

New Faculty-facing surface — `Rotation` itself stays admin-owned (`RotationPolicy` is admin-only today), but DEC-032 explicitly assigns target configuration to faculty:

- `GET /faculty/rotations` — lists rotations where the acting faculty member is `primary_preceptor` on at least one active `RotationAssignment`, each with its current targets (overall + per-category).
- `POST /faculty/rotations/{rotation}/targets`, `PATCH .../targets/{target}`, `DELETE .../targets/{target}`.

**New `RotationCaseTargetPolicy`:**
- `manage(User $user, Rotation $rotation): bool` — true only if `$rotation->institution_id === $user->institution_id`, `$user->role === Faculty`, and the user has an active `RotationAssignment` on that rotation (as `primary_preceptor_id`). Any currently-assigned faculty on the rotation may create/update/delete any target row on it — targets belong to the rotation, not to whichever faculty member happened to create them.
- `viewAny`/`view` — the managing faculty (as above) or a same-institution Administrator, read-only. This is what backs the read side of §6's admin/faculty progress views if they need the target definitions themselves (the progress numbers are aggregate queries, not per-row policy checks — see §6).

Validation for create/update: `category` nullable string max 80 (trimmed before the uniqueness check runs, so trailing whitespace can't slip past `category_key`'s normalization at the DB layer only to look duplicated to a human later), `target_count` required unsigned int ≥ 1. Duplicate category (case/whitespace-insensitive) or a second overall target on the same rotation surfaces as a clean 422 from a pre-check, not a raw DB constraint violation reaching the client.

## 6. Progress model and computation

**Scope: per student, per rotation assignment.** A target defined once on a rotation (§3) is the goal every student assigned to that rotation is individually measured against — not a shared pool. Counting always filters by `clinical_cases.rotation_assignment_id = <specific assignment>`, never by `rotation_id` alone across students.

One shared `RotationProgressService`, so the counting logic isn't duplicated three times:

```php
class RotationProgressService
{
    /** @return Collection<int, array> one entry per active-or-not assignment, each with overall + per-category approved-count/target */
    public function forStudent(User $student): Collection;

    /** @return Collection<int, array> grouped by (student, rotation) for every assignment where this faculty is primary_preceptor */
    public function forFaculty(User $faculty): Collection;

    /** @return Collection<int, array> institution-wide, optionally filtered to one rotation */
    public function forInstitution(string $institutionId, ?string $rotationId = null): Collection;
}
```

For a given assignment: `ClinicalCase::where('rotation_assignment_id', $id)->where('status', CaseStatus::Approved)->get(['case_category'])`, grouped by the trimmed/lower-cased `case_category` in PHP (not a DB expression — `clinical_cases.case_category` has no generated column, unlike the targets table) and matched against that rotation's `rotation_case_targets` rows by `category_key`. The overall count is simply the total approved-case count for the assignment, matched against the `category IS NULL` row if one exists. A category with approved cases but no matching target (e.g. free-text entered before any target existed) is shown as "no target set", never silently dropped.

**Three read-only pages**, parallel to `Portfolio.vue`, not folded into the existing dashboards:
- `GET /student/progress` — the student's own assignment(s).
- `GET /faculty/progress` — every student the faculty is assigned to, grouped by rotation.
- `GET /admin/progress` — institution-wide, filterable by rotation; drilling into a rotation also lists its individual approved cases (case number, student, category) as the discovery path to §7's admin export, since admins have no other case-browsing surface today.

The existing `DashboardController::student()`/`faculty()`/`administrator()` stub metrics (e.g. the hardcoded "Cases this rotation: 0/6") are wired to real numbers from the same service, replacing the hardcoded strings — not restructured otherwise.

**No new `ClinicalCasePolicy` check is needed for progress.** Every query above is institution/assignment/student-scoped directly (matching how `Admin\RotationController::index()` already queries `Rotation` without a per-row policy check), and returns aggregate counts, never individual clinical narrative content. The existing `role:student`/`role:faculty`/`role:administrator` route middleware is the only gate.

## 7. PDF export

**Extending `SubmitCase::buildSnapshot()`** with a new `record_metadata` block, captured once at submission time (frozen thereafter, like every other snapshot field):

```php
'record_metadata' => [
    'case_number' => $case->case_number,
    'rotation_name' => $case->rotationAssignment?->rotation?->name,
    'clinical_site_name' => $case->clinicalSite?->name,
    'ward_name' => $case->ward?->name,
],
```

No migration needed — `snapshot` is already a JSON column; this only adds a key inside it.

**`ClinicalCasePolicy::exportPdf(User $user, ClinicalCase $case): bool`** — a new, narrowly-scoped ability (not a widening of `view()`, which correctly still denies administrators broad case access):

```php
if ($case->status !== CaseStatus::Approved || $case->institution_id !== $user->institution_id) {
    return false;
}
return match ($user->role) {
    UserRole::Student => $case->student_id === $user->id,
    UserRole::Faculty => $case->rotationAssignment->primary_preceptor_id === $user->id,
    UserRole::Administrator => true, // same-institution, approved-only — the narrowest safe admin case access this slice introduces
};
```

**`CaseExportPdfService::build(ClinicalCase $case): \Barryvdh\DomPDF\PDF`:**
- Resolves the approved `CaseVersion` (`$case->versions()->whereNotNull('approved_at')->latest('version_number')->first()`).
- Clinical content (case context, history, vitals, investigations, medications, SOAP, clinical activities) comes **only** from `$version->snapshot` — never a live query against `ClinicalCase`/`CaseVital`/etc.
- Header metadata: `case_number`, `version_number`, `form_version`, `approved_by`/`approved_at` from `CaseVersion`/`ClinicalCase` columns (immutable, never drift); rotation/site/ward display names from `$version->snapshot['record_metadata']` when present, falling back to the case's current live relations when absent (versions submitted before this change) — a documented, one-time legacy gap, not a live bug going forward.
- Renders a plain Blade view (`resources/views/exports/clinical-case.blade.php`) via `barryvdh/laravel-dompdf`; no branding/layout finalization in this slice (OPEN-016 stays open).

**Routes**, one thin action per role reusing `exportPdf`:
```
GET /student/cases/{case}/export        → CaseController::export
GET /faculty/reviews/{case}/export      → ReviewController::export
GET /admin/cases/{case}/export          → new Admin\CaseExportController::export
```

Every successful export is audited via the existing `AuditTrail` service, subject = the `ClinicalCase` (matching every other case-lifecycle event's subject choice):

```php
$this->audit->record($actor, $case, 'clinical_case.exported', [
    'case_id' => $case->id,
    'version_number' => $version->version_number,
    'case_version_id' => $version->id,
]);
```

## 8. Retention

**Documentation only, per this session's decision — no schema, no code, no `PROJECT_STATE.md` change until Slice 5 merges.**

`docs/DECISIONS.md` gains one new open item (§4, next available id):

> `OPEN-017` | What data source determines "course completion" for computing each case's retention-until date? | DEC-033 accepts the course-completion-plus-one-year duration, but no completion-date field exists anywhere in the schema (`AcademicCohort` has only `admission_year`/`academic_year_label`). Slice 5 records the accepted policy and this open gap; it does not compute or store a per-case retention date, and does not implement any deletion job, until this is resolved — matching the master plan's caution against unsafe automatic deletion.

This mirrors how `OPEN-009` (rotation-end draft policy) is already recorded as open rather than silently defaulted. No `RETENTION_YEARS` constant or similar is added — an unused constant with no caller is dead code, not documentation.

## 9. Endpoints

```
GET    /faculty/rotations
POST   /faculty/rotations/{rotation}/targets
PATCH  /faculty/rotations/{rotation}/targets/{target}
DELETE /faculty/rotations/{rotation}/targets/{target}

GET    /student/progress
GET    /faculty/progress
GET    /admin/progress

GET    /student/cases/{case}/export
GET    /faculty/reviews/{case}/export
GET    /admin/cases/{case}/export
```

`CaseController::index()` gains the active-assignments prop (§4). `UpdateClinicalCaseContextRequest` gains the category-match check (§4). No existing route signature changes.

## 10. Frontend surfaces

- **`Cases.vue`**: zero/one/many active-assignment handling for "New case" (§4).
- **`CaseProfileSection.vue`**: real `case_category` control — dropdown when the rotation has configured categories, free text otherwise (§4).
- **New `faculty/Rotations.vue`**: per-rotation target list (overall + categories) with add/edit/delete, scoped to the faculty's own assigned rotations.
- **New `student/Progress.vue`, `faculty/Progress.vue`, `admin/Progress.vue`**: role-scoped progress tables (§6); the admin page also lists a selected rotation's approved cases with export links.
- **Export buttons**: added to `student/CaseShow.vue`, `faculty/CaseReview.vue` (both already render only when `status === 'approved'` is true for at least the approved-version metadata, so gating the button on that is a template-only change), and the new admin progress drill-down.
- Existing `student/Dashboard.vue`, `faculty/Dashboard.vue`, `admin/Dashboard.vue` stub metrics wired to real numbers (§6), no layout change.

## 11. Testing plan

- **Targets:** cross-institution faculty denied; unassigned faculty (assigned to a *different* rotation) denied; assigned faculty can create/update/delete; duplicate category (including whitespace/case variants) rejected with a clean 422, not a DB error; a second overall target on the same rotation rejected the same way; same-institution admin can view, not manage.
- **Category enforcement:** `UpdateClinicalCaseContextRequest` accepts an exact-match category when targets exist; accepts any free text when the rotation has none configured; rejects a category not in that rotation's configured list (raw request, bypassing the dropdown) with 422; stored value is the canonical casing from the target row, not the client's casing.
- **Progress correctness:** approved-only counting (submitted/under-review/returned cases never counted); per-student isolation — two students on the same rotation with the same category target get independent counts, not a shared pool; a category with cases but no matching target shown as unset rather than dropped; institution/faculty/student scoping matches the authorization tests already established for every other role-scoped view in this codebase.
- **PDF de-identification/content:** exported content matches `version.snapshot` byte-for-byte for every clinical field, even after simulating a live-data change post-approval (e.g. mutate a `CaseVital` row directly in the test, confirm the PDF is unaffected); header shows `case_number`/`version_number`/`approved_by`/`approved_at` correctly; rotation/site/ward names come from `record_metadata` when present and from live relations when absent (test both paths explicitly); no ULID or other technical identifier appears in the rendered output.
- **Export authorization and audit:** only the case owner (student), the assigned faculty, or a same-institution admin can export; a non-`Approved` case is denied for every role; a successful export creates exactly one `AuditEvent` with `case_id`, `version_number`, and `case_version_id` all correct.
- **New Case flow:** zero active assignments disables creation with the explanatory message; exactly one active assignment creates without a picker; more than one requires a selection; a raw POST with a spoofed/inactive/cross-institution assignment id is still rejected by the unchanged `StoreClinicalCaseRequest` rule (regression-proving §4's claim that no backend change was needed).

## 12. Files touched (for the implementation plan to size, not exhaustive)

New: migration for `rotation_case_targets` (with generated column + two raw partial unique indexes); `RotationCaseTarget` model; `RotationCaseTargetPolicy`; `Faculty\RotationTargetController`; `RotationProgressService`; `Student\ProgressController`, `Faculty\ProgressController`, `Admin\ProgressController`; `CaseExportPdfService`; `Admin\CaseExportController`; `resources/views/exports/clinical-case.blade.php`; `faculty/Rotations.vue`, `student/Progress.vue`, `faculty/Progress.vue`, `admin/Progress.vue`.
Extended: `SubmitCase::buildSnapshot()` (`record_metadata` block); `ClinicalCasePolicy` (`exportPdf`); `CaseController` (active-assignments prop, `export` action); `ReviewController` (`export` action); `UpdateClinicalCaseContextRequest` (category-match `after()` check); `Cases.vue`, `CaseProfileSection.vue`; the three role dashboards' stub metrics; `docs/DECISIONS.md` (`OPEN-017`); `composer.json` (`barryvdh/laravel-dompdf`).

## Self-review

- **Placeholders:** none — every section states a concrete mechanism, not a TBD; the two genuinely open items (PDF branding/layout, completion-date source) are explicitly named as still-open, not silently assumed.
- **Internal consistency:** §4's "no backend change needed" claim is checked against `StoreClinicalCaseRequest`'s actual existing rule, not assumed, and §11 adds a regression test proving it; §6's per-assignment (not per-rotation) counting is applied identically in all three `RotationProgressService` methods; §7's policy is deliberately a new narrow ability rather than a change to `view()`, keeping every other slice's authorization behavior untouched; §3's generated `category_key` is the single normalization mechanism referenced consistently by §4 (request validation), §5 (target uniqueness), and §6 (progress matching) — never redefined per call site.
- **Scope:** matches the master plan's Slice 5 bullets and exit evidence exactly; §1 restates the exclusion list; the `Cases.vue` bug fix is called out explicitly as an intentional, scoped addition (per this session's decision), not silent creep.
- **Ambiguity:** "faculty configures" is concrete (any currently-assigned faculty on that specific rotation, not a global faculty-wide ability); "de-identified PDF" is concrete (clinical content is byte-sourced from the snapshot, tested against live-data drift); "approved-only" progress is concrete and tested against the other four statuses explicitly.
