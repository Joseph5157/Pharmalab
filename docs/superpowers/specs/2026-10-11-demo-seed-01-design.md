# DEMO-SEED-01 — Protected end-to-end workflow demonstration data

**Status:** Design approved in conversation (Sections 1–3 with corrections); written spec awaiting review
**Branch:** `demo-seed-01` (from `main` at `69898f9`)
**Date:** 2026-10-11

## 1. Goal

Give the product owner a repeatable, fully fictional Pharm.D demonstration on Railway staging and local Docker: one isolated demo institution with admin, faculty and two student accounts, a rotation with category targets, and cases in every reachable workflow state — Draft, Returned, Submitted, Approved, and Returned-after-approval (reopened) — with real review comments, version history, progress figures and PDF exports.

Two Artisan commands:

- `php artisan demo:seed` — idempotently adds whatever demo data is missing.
- `php artisan demo:reset` — removes only the demo institution's data and rebuilds the full dataset, atomically.

### Non-goals

- No boot-time seeding or wiping. `docker/start.sh` is not changed; deploys never run either command.
- The legacy `SEED_DEMO` hook, `DemoUsersSeeder`, `DatabaseSeeder` and the existing "SIMS College of Pharmacy" accounts are not modified, migrated or deleted.
- No `UnderReview` data — no application code path currently sets that status.
- No UI changes.

## 2. Configuration and environment

New `config/demo.php`:

| Key | Source | Meaning |
|---|---|---|
| `demo.enabled` | `env('DEMO_MODE', false)` (boolean) | Master switch. Read through config so it survives `config:cache`. |
| `demo.password` | `env('DEMO_PASSWORD')` | Password for every demo account. Required, ≥ 12 characters. |

The institution slug/name, the email domain, and the exact user allowlist are constants in code (§4), not configuration — they define what the purge is allowed to delete and must not be changeable by an environment variable.

Required Railway staging variables (documented in `README.md`, §9):

```env
DEMO_MODE=true
SEED_DEMO=false
DEMO_PASSWORD=<12+ character password>
```

`SEED_DEMO=false` stops `start.sh` from re-creating the older SIMS demo accounts on every boot alongside the dedicated demo institution. Existing SIMS accounts are left in place; removing them is a manual decision outside this gate.

`APP_ENV` is deliberately not consulted: Railway staging runs with `APP_ENV=production`.

## 3. Architecture

Approach: two Artisan commands backed by small single-purpose classes under `app/Demo/`. Workflow transitions go through the real actions (`SubmitCase`, `ReturnCase`, `ApproveCase`, `ReopenCase`) so versions, snapshots (including `record_metadata`), transitions, review comments and audit events are produced exactly as the application produces them.

| Unit | Responsibility |
|---|---|
| `DemoGuard` | Fails closed unless `demo.enabled` is true and `demo.password` is a string of ≥ 12 characters. Throws a `DemoRefusedException` with an actionable message; never echoes the password. |
| `DemoDataset` | Constants: institution slug/name, email domain, the exact user allowlist with names/roles, academic-structure natural keys, target definitions. |
| `DemoSeeder` | Builds structure, accounts, assignments, targets, then runs each case scenario. Fails closed on conflicts (§5). |
| `DemoCaseScenarios` | The eight case scenarios (§4.4): clinical content builders plus the ordered workflow steps for each. |
| `DemoActingAs` | `run(User $user, Closure $step)` — sets the user on the auth guard, runs the step, restores the previous user in `finally`. |
| `DemoPurger` | Pre-purge safety checks (§6.1), counts for the confirmation summary, and the labelled, ordered purge (§6.2). |
| `DemoSeedCommand` / `DemoResetCommand` | Thin console wrappers: guard, confirmation, outer transaction (reset), summary output, safe error reporting (§6.3). |

### 3.1 Authentication scoping in the CLI

An Artisan command starts with no authenticated user, so institution global scopes (`BelongsToInstitution`) are inactive at first.

- **Bootstrap phase** (institution, academic structure, users, assignments, targets): every lookup uses `withoutGlobalScopes()` with exact identifiers (slug, email, codes, foreign keys). No step depends on an authenticated user.
- **Workflow phase**: before each action call — and before each student-authored content write — the relevant demo user is set via `DemoActingAs::run()`:

  ```php
  $previousUser = Auth::user();

  try {
      Auth::setUser($demoUser);
      // one workflow step
  } finally {
      $previousUser === null ? Auth::forgetUser() : Auth::setUser($previousUser);
  }
  ```

  Each step sets its own actor; no identity carries from one step or scenario to the next. After either command finishes, no user is authenticated.

## 4. Dataset

### 4.1 Academic structure

All rows belong to the demo institution and are looked up by natural key within it.

| Entity | Natural key | Values |
|---|---|---|
| Institution | slug `pharmalab-demo-college` | name "Pharmalab Demo College", status active, timezone Asia/Kolkata |
| Programme | code `PD-DEMO` | "Pharm.D", 6 years, active |
| Academic cohort | programme + name `2022 Intake` | admission year 2022, academic year label "2026–27", active |
| Clinical site | code `DGH` | "Demo General Hospital", active |
| Department | site + name `General Medicine` | active |
| Ward | code `MWA` | "Medicine Ward A", site DGH, department General Medicine, active |
| Rotation | programme + name `General Medicine Clerkship` | cohort, site, department, ward as above; **starts 2022-01-01, ends 2032-12-31**; active |

The fixed long-lived dates keep the rotation active for years and keep seed output deterministic.

### 4.2 Accounts — exact allowlist

All `@pharmalab-demo.test`, status active, email verified, password = `demo.password` (stored only through the model's `hashed` cast).

| Email | Name | Role |
|---|---|---|
| `admin@pharmalab-demo.test` | Demo Administrator | Administrator |
| `faculty@pharmalab-demo.test` | Dr. Demo Preceptor | Faculty |
| `student1@pharmalab-demo.test` | Priya Demo | Student |
| `student2@pharmalab-demo.test` | Rahul Demo | Student |

Re-running `demo:seed` re-applies the current `DEMO_PASSWORD` to these four accounts (so rotating the variable takes effect) and changes nothing else about them.

Both students have an active `RotationAssignment` to the rotation with the faculty account as primary preceptor.

### 4.3 Targets

Created by the faculty account on the rotation:

| Category | Target |
|---|---|
| (overall) | 6 |
| Cardiology | 2 |
| Respiratory | 2 |
| Infectious Disease | 2 |

### 4.4 Case scenarios

Every case is fictional and de-identified (age, sex and clinical detail only — no names, hospital numbers or dates of birth). Each scenario has its own clinically coherent content: diagnoses, vitals, investigations, medications, SOAP note and conditional activities agree with one another. The content structure follows the existing complete-case test fixture (`tests/Support/BuildsSliceFiveFixtures.php`); the clinical substance differs per case. Encounter dates are fixed dates within the rotation. Student-authored content is written directly to the models under the student's identity (as the fixtures do); every status change goes through the real action.

Clinical case site/ward overrides are left null, so record metadata exercises the rotation fallback fixed in PR #17.

| Key | Student | Category | Clinical scenario | Workflow steps | Final state |
|---|---|---|---|---|---|
| P1 | Priya | Cardiology | 64 y male, uncontrolled essential hypertension — amlodipine + telmisartan; BP readings, serum creatinine and potassium; adherence and home-BP counselling given | submit → approve with summary | **Approved** (v1) |
| P2 | Priya | Respiratory | 52 y male, acute exacerbation of COPD — nebulised salbutamol/ipratropium, oral prednisolone, amoxicillin–clavulanate; SpO₂, CBC; inhaler-technique counselling | submit (v1 omits steroid duration and monitoring plan) → return with flagged Medication Chart and SOAP comments → student corrects both → resubmit → approve | **Approved** (v1 + v2, full review history) |
| P3 | Priya | Cardiology | 70 y female, decompensated heart failure with reduced ejection fraction — furosemide, spironolactone, bisoprolol; serum potassium, creatinine, weight | submit → approve → reopen with reason (potassium of 5.6 mmol/L not addressed with continued spironolactone) | **Returned after approval** — reopen banner; approved v1 unchanged |
| P4 | Priya | Infectious Disease | 34 y female, uncomplicated lower urinary tract infection — nitrofurantoin; urinalysis and urine culture | submit → return with flagged Vitals & Investigations comment (culture sensitivity not recorded) | **Returned** |
| P5 | Priya | Respiratory | 23 y female, acute asthma — salbutamol, budesonide–formoterol; peak expiratory flow, SpO₂ | submit | **Submitted** |
| P6 | Priya | (none) | 45 y male, suspected community-acquired pneumonia — profile and history only, no SOAP | none | **Draft** (incomplete) |
| R1 | Rahul | Cardiology | 58 y male, stable angina with dyslipidaemia — aspirin, atorvastatin, isosorbide mononitrate; lipid profile, ECG noted | submit → approve | **Approved** (v1) |
| R2 | Rahul | Respiratory | 67 y male, stable COPD review — tiotropium, salbutamol as needed; spirometry noted, SpO₂; inhaler-technique and smoking-cessation counselling | submit | **Submitted** |

Case numbers are 1–6 for Priya and 1–2 for Rahul, in table order.

**Resulting demonstration state:**

- Priya's progress: overall **2/6**, Cardiology **1/2** (P3 is reopened and no longer counts), Respiratory **1/2**, Infectious Disease **0/2**.
- Rahul's progress: overall **1/6**, Cardiology **1/2**, Respiratory **0/2**, Infectious Disease **0/2**.
- Faculty review queue: **two Submitted cases across two students** (P5, R2). P3 and P4 are Returned — waiting on the student, not on faculty.
- PDF exports available: P1, P2, R1.
- Version history: P2 shows v1 and v2 with both review rounds; P3 shows the approval and the reopen.

## 5. Idempotent seeding and fail-closed conflicts

`demo:seed` creates only what is missing. For every natural key it finds an existing row by, it verifies the row matches what the dataset expects; on any mismatch it stops with an actionable error and changes nothing further (the whole `demo:seed` run is one transaction). It never reassigns an existing record.

Checks include:

- Institution with the demo slug has the expected name.
- Each allowlisted email, if present, belongs to the demo institution and has the expected role.
- Programme, cohort, site, department, ward and rotation belong to the demo institution and to their expected parents (e.g. the rotation's programme, site and ward).
- Each assignment connects the intended student, faculty and rotation.
- Each existing target has the expected category and count.
- A case found at a scenario's `(student, case_number)` belongs to the intended student and the demo rotation assignment.

Case scenarios: if a case already exists at its key, the whole scenario is skipped (not repaired). Each scenario runs in its own nested transaction, so a failure never leaves a half-built case. Demo cases changed during a live demonstration are restored with `demo:reset`, not `demo:seed`.

Error example: `Demo seed refused: user student1@pharmalab-demo.test belongs to another institution. No demo data was changed.`

## 6. Reset

`demo:reset` runs **purge → full rebuild** inside a single outer database transaction. Scenario and action transactions nest inside it as savepoints. If anything fails — a safety check, a purge stage, or the rebuild — everything rolls back and the previous demo remains usable.

### 6.1 Safety checks (before any write)

Refuse — exit non-zero, write nothing — unless all hold. `--force` bypasses only the confirmation prompt, never these checks.

1. `DemoGuard` passes.
2. If an institution with slug `pharmalab-demo-college` exists, its name is exactly "Pharmalab Demo College". (If none exists, reset simply seeds.)
3. The institution's user set **exactly** matches the four-email allowlist (§4.2) or a subset of it. Refuse when:
   - any user with a non-demo-domain email belongs to it;
   - any additional `@pharmalab-demo.test` user not on the allowlist belongs to it;
   - any allowlisted email exists but is linked to another institution.

   The refusal lists the offending emails.

Without `--force`, the command then shows the summary and asks for confirmation:

- institution name and slug;
- counts of users, cases, case versions, review comments and audit events to be removed.

Answering "no" exits successfully with no writes.

### 6.2 Purge registry and order

Purging is driven by an explicit, ordered registry. Each entry has a human label and a deletion strategy. Order (child → parent), reconciled against the current migrations:

| # | Label | Table | Strategy |
|---|---|---|---|
| 1 | audit events | `audit_events` | `institution_id` |
| 2 | sync operations | `sync_operations` | `institution_id` |
| 3 | case review comments | `case_review_comments` | `institution_id` |
| 4 | case status transitions | `case_status_transitions` | `institution_id` |
| 5 | case versions | `case_versions` | `institution_id` |
| 6 | clinical activities | `case_clinical_activities` | `institution_id` |
| 7 | medications | `case_medications` | `institution_id` |
| 8 | investigations | `case_investigations` | `institution_id` |
| 9 | vitals | `case_vitals` | `institution_id` |
| 10 | clinical profiles | `case_clinical_profiles` | `institution_id` |
| 11 | SOAP notes | `soap_notes` | `institution_id` |
| 12 | case draft notes | `case_draft_notes` | `institution_id` |
| 13 | clinical cases | `clinical_cases` | `institution_id` |
| 14 | rotation case targets | `rotation_case_targets` | `institution_id` |
| 15 | rotation assignments | `rotation_assignments` | `institution_id` |
| 16 | rotations | `rotations` | `institution_id` |
| 17 | academic cohorts | `academic_cohorts` | `institution_id` |
| 18 | wards | `wards` | `institution_id` |
| 19 | departments | `departments` | `institution_id` |
| 20 | clinical sites | `clinical_sites` | `institution_id` |
| 21 | programmes | `programmes` | `institution_id` |
| 22 | demo user sessions | `sessions` | `user_id` in demo user ids |
| 23 | demo password-reset tokens | `password_reset_tokens` | `email` in demo user emails |
| 24 | demo users | `users` | `institution_id` (passkeys removed by their `cascadeOnDelete` FK) |
| 25 | demo institution | `institutions` | `id` |

Tables with no link to demo data, exempt by name: `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Tables covered by an FK cascade, registered as such: `passkeys` (→ `users`, cascade).

Foreign keys remain restrictive. If a row outside the demo institution references a demo row, that stage's delete fails and the whole reset rolls back.

### 6.3 Failure reporting

Every purge stage is labelled. On failure the command rolls back and prints only:

`Demo reset failed while deleting <label>. No data was changed.`

The underlying exception is written to the application log via `report()`. The command output never includes SQL, database credentials, exception text or the demo password. Seeding failures inside a reset use the same rule: `Demo reset failed while rebuilding the demo dataset (<scenario or entity>). No data was changed.`

## 7. Output

Both commands print a short summary on success: counts of users, cases by status, versions and review comments in the demo institution. The password is never printed or logged, including on refusal or failure.

## 8. Testing

PHPUnit class-based feature tests on SQLite, following existing conventions (`Tests\TestCase`, `RefreshDatabase`, `test_snake_case`, `withoutGlobalScopes()` model creation; only `Institution`/`User` factories exist). Tests set `config(['demo.enabled' => true, 'demo.password' => ...])` directly.

| Area | Proves |
|---|---|
| Guard | Both commands refuse with `DEMO_MODE` off, and with the password missing or < 12 characters; zero rows written. Both run with `app.env` = production. |
| Dataset | Per-scenario outcomes: final status, version count, transitions, review comments (sections and flags). P3's approved v1 keeps `approved_by`, `approved_at` and `snapshot_hash` after reopen. Priya's progress via `RotationProgressService`: 2/6, 1/2, 1/2, 0/2. Faculty queue has exactly P5 and R2. Snapshots' `record_metadata` carries rotation site/ward names. Audit events' actors are the correct demo users. Cases have distinct diagnoses/medications. |
| Idempotency | A second `demo:seed` changes no row counts. Changing `demo.password` and re-running updates all four hashes. No user is authenticated after either command. |
| Fail-closed seeding | An allowlisted email in another institution, a demo rotation pointing at the wrong site, and an existing case at a scenario key owned by a different assignment each abort with an actionable error and leave data unchanged. |
| Reset | Rebuilds the full dataset after demo cases are modified. A second, non-demo institution with its own users and cases is untouched. Reset refuses (even with `--force`) for: a non-demo-domain user in the demo institution; an extra `@pharmalab-demo.test` user; an allowlisted email linked to another institution. Answering "no" writes nothing. A rebuild failure forced mid-reset leaves the original demo data intact and prints the safe failure message. |
| Purge coverage | Schema introspection fails the test when (a) a table with an `institution_id` column is missing from the registry, or (b) any foreign key references any table in the purge registry from a table that has no registered strategy (institution-scoped, explicit, or FK cascade) and is not in the exempt list. It also checks that registry order deletes every child before its parent. |
| Output safety | The password never appears in command output on success, refusal or failure. |
| PDF | One approved demo case (P1) renders through `CaseExportPdfService::html()` with its rotation, site, ward and diagnosis. |

Outside CI: one manual `demo:seed` → `demo:seed` → `demo:reset` run against the Docker Postgres 17 container. A post-merge Railway staging walkthrough with the three roles is at the product owner's discretion.

## 9. Documentation

A "Demo environment" section in `README.md`:

- required variables (`DEMO_MODE=true`, `SEED_DEMO=false`, `DEMO_PASSWORD` ≥ 12 chars) and why `SEED_DEMO=false` matters;
- running the commands on Railway (`railway ssh` → `php artisan demo:seed` / `php artisan demo:reset --force`);
- the demo accounts (emails only — the password is whatever `DEMO_PASSWORD` is set to);
- that deploys never seed or wipe, and the legacy SIMS accounts are left untouched.
