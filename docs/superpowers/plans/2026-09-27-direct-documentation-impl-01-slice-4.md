# DIRECT-DOCUMENTATION-IMPL-01 Slice 4 — Faculty Review, Correction and Reopening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let assigned faculty add section-flagged review comments and an overall reason/summary when returning or approving a case, reopen an approved case with a mandatory audited reason, and let students see exactly what to correct — while every past round of feedback stays permanently visible and nothing already accepted (comments, versions, transitions) is ever mutated or deleted.

**Architecture:** One new append-only table (`case_review_comments`) hangs off the existing `CaseStatusTransition` row for the exact Return/Approve event that produced it — not off `CaseVersion` directly — so a three-hop native Eloquent relation (`CaseVersion → statusTransitions → reviewComments → author`) is the single read path everywhere. Reopening an approved case is modeled as an ordinary `Approved → Returned` transition, distinguished only by `from_status`, so every existing Draft/Returned edit/resubmit/highlight code path already works for it with no new `CaseStatus` value.

**Tech Stack:** Laravel (PHP), Inertia.js + Vue 3 + TypeScript, PostgreSQL, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-27-direct-documentation-impl-01-slice-4-design.md` — read it alongside this plan; this plan implements it task-by-task and does not repeat its reasoning.

## Global Constraints

- No rubric model, scoring scale, marks, field-level annotations, second reviewer, or comment edit/delete/resolve endpoints — Slice 4 delivers feedback, correction, resubmission, approval and reopening only (spec §1, DEC-030).
- `case_review_comments` rows are append-only: never updated, never deleted, once written (spec §3).
- Every new/changed action (`ReturnCase`, `ApproveCase`, `ReopenCase`) locks the case row and re-checks its status *after* acquiring the lock, before writing anything (spec §5).
- The read path for comments is always the same three-hop chain: `versions.statusTransitions.reviewComments.author` — no second, differently-shaped path anywhere (spec §3, §6).
- `ApproveCase`'s existing optional/nullable `summary` behaviour is untouched — this slice only adds optional section comments alongside it (spec §5).
- All new PHP passes PHPStan (0 errors) and Pint; all new/changed Vue passes `npm run check` and `npm run types:check` (repo-wide convention, unchanged by this slice).

## Review Focus

- A case returned, then reopened, then returned again: the "current flagged sections" query must resolve to the *latest* Returned-producing transition, never an earlier round's flags bleeding through. → Task 9.
- A faculty client forging `is_flagged: true` on the Approve payload must never persist as flagged — the server forces `false` regardless of what's sent. → Task 6.
- Two review actions racing the same case (e.g. approve and return submitted moments apart from two tabs) must not both succeed — the second must 409, not silently overwrite the first. → Tasks 4, 5, 6.
- A case already `Returned` from before this migration shipped (no matching `CaseReviewComment` rows for its transition) must render the editor and history views with empty feedback, not an error. → Task 9.
- A mid-transaction failure (a raw duplicate-section insert, or any exception before the comment rows are written) must leave neither the `CaseStatusTransition` row nor any `CaseReviewComment` row behind — no partial write, for either Return or Approve. → Tasks 5, 6.

---

## Task 1: `CaseReviewSection` enum and `case_review_comments` migration

**Files:**
- Create: `app/Enums/CaseReviewSection.php`
- Create: `database/migrations/2026_09_30_000004_create_case_review_comments_table.php`
- Test: `tests/Unit/CaseReviewCommentSchemaTest.php`

**Interfaces:**
- Produces: `CaseReviewSection` enum (backed `string`, cases `CaseProfile = 'case_profile'`, `HistoryDiagnosis = 'history_diagnosis'`, `VitalsInvestigations = 'vitals_investigations'`, `MedicationChart = 'medication_chart'`, `Soap = 'soap'`, `ClinicalActivities = 'clinical_activities'`); `case_review_comments` table with columns `id, institution_id, clinical_case_id, case_status_transition_id, section, body, is_flagged, created_by, created_at` and a unique index on `(case_status_transition_id, section)`.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseReviewCommentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_review_comments_table_has_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('case_review_comments'));
        $this->assertTrue(Schema::hasColumns('case_review_comments', [
            'id', 'institution_id', 'clinical_case_id', 'case_status_transition_id',
            'section', 'body', 'is_flagged', 'created_by', 'created_at',
        ]));
    }

    public function test_only_one_comment_per_section_per_transition_is_allowed(): void
    {
        [$institutionId, $caseId, $transitionId, $userId] = $this->seedTransition();

        DB::table('case_review_comments')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'case_status_transition_id' => $transitionId,
            'section' => 'soap',
            'body' => 'First comment.',
            'is_flagged' => true,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('case_review_comments')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'case_status_transition_id' => $transitionId,
            'section' => 'soap',
            'body' => 'Duplicate section for the same transition.',
            'is_flagged' => false,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @return array{string, string, string, int} */
    private function seedTransition(): array
    {
        $institution = \App\Models\Institution::factory()->create();
        $student = \App\Models\User::factory()->student()->create(['institution_id' => $institution->id]);
        $faculty = \App\Models\User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $site = \App\Models\ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = \App\Models\Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = \App\Models\Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = \App\Models\RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => 'active',
        ]);

        $case = \App\Models\ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => 'submitted',
        ]);

        $transition = \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => 'submitted',
            'to_status' => 'returned',
            'actor_id' => $faculty->id,
            'reason' => 'Needs correction.',
        ]);

        return [$institution->id, $case->id, $transition->id, $faculty->id];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/CaseReviewCommentSchemaTest.php`
Expected: FAIL — table `case_review_comments` does not exist.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum CaseReviewSection: string
{
    case CaseProfile = 'case_profile';
    case HistoryDiagnosis = 'history_diagnosis';
    case VitalsInvestigations = 'vitals_investigations';
    case MedicationChart = 'medication_chart';
    case Soap = 'soap';
    case ClinicalActivities = 'clinical_activities';

    public function label(): string
    {
        return match ($this) {
            self::CaseProfile => 'Case Profile',
            self::HistoryDiagnosis => 'History & Diagnosis',
            self::VitalsInvestigations => 'Vitals & Investigations',
            self::MedicationChart => 'Medication Chart',
            self::Soap => 'SOAP',
            self::ClinicalActivities => 'Conditional Clinical Activities',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_review_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_status_transition_id')->constrained('case_status_transitions')->restrictOnDelete();
            $table->string('section', 40);
            $table->text('body');
            $table->boolean('is_flagged');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['case_status_transition_id', 'section']);
            $table->index(['institution_id', 'clinical_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_review_comments');
    }
};
```

- [ ] **Step 5: Run migrations and the test again**

Run: `docker compose exec app php artisan migrate` then `docker compose exec app php artisan test tests/Unit/CaseReviewCommentSchemaTest.php`
Expected: PASS — both tests green, the second confirming the DB constraint itself rejects a raw duplicate-section insert.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/CaseReviewSection.php database/migrations/2026_09_30_000004_create_case_review_comments_table.php tests/Unit/CaseReviewCommentSchemaTest.php
git commit -m "feat: add case_review_comments table and CaseReviewSection enum"
```

---

## Task 2: `CaseReviewComment` model and the three-hop read path

**Files:**
- Create: `app/Models/CaseReviewComment.php`
- Modify: `app/Models/CaseVersion.php`
- Modify: `app/Models/CaseStatusTransition.php`
- Test: `tests/Unit/CaseReviewCommentSchemaTest.php` (extend)

**Interfaces:**
- Consumes: `case_review_comments` table from Task 1.
- Produces: `CaseReviewComment` model (casts `section` to `CaseReviewSection`, `is_flagged` to `bool`); `CaseVersion::statusTransitions(): HasMany<CaseStatusTransition>`; `CaseStatusTransition::reviewComments(): HasMany<CaseReviewComment>`; `CaseReviewComment::author(): BelongsTo<User>`. Every later task's eager-loads use the dotted string `'versions.statusTransitions.reviewComments.author'`.

- [ ] **Step 1: Write the failing relation test**

Add to `tests/Unit/CaseReviewCommentSchemaTest.php`:

```php
    public function test_a_comment_is_reachable_from_its_case_version_through_the_transition(): void
    {
        [$institutionId, $caseId, $transitionId, $facultyId] = $this->seedTransition();

        $version = \App\Models\CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => \App\Models\ClinicalCase::query()->withoutGlobalScopes()->find($caseId)->student_id,
            'submitted_at' => now(),
        ]);

        \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()
            ->whereKey($transitionId)
            ->update(['case_version_id' => $version->id]);

        \App\Models\CaseReviewComment::query()->create([
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'case_status_transition_id' => $transitionId,
            'section' => \App\Enums\CaseReviewSection::Soap,
            'body' => 'Please expand the plan.',
            'is_flagged' => true,
            'created_by' => $facultyId,
        ]);

        $reloaded = \App\Models\CaseVersion::query()->withoutGlobalScopes()
            ->with('statusTransitions.reviewComments.author')
            ->findOrFail($version->id);

        $comment = $reloaded->statusTransitions->first()->reviewComments->first();

        $this->assertNotNull($comment);
        $this->assertSame(\App\Enums\CaseReviewSection::Soap, $comment->section);
        $this->assertTrue($comment->is_flagged);
        $this->assertSame($facultyId, $comment->author->id);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec app php artisan test tests/Unit/CaseReviewCommentSchemaTest.php --filter test_a_comment_is_reachable`
Expected: FAIL — `CaseReviewComment` class and `statusTransitions()`/`reviewComments()` relations don't exist yet.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\CaseReviewSection;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $clinical_case_id
 * @property string $case_status_transition_id
 * @property CaseReviewSection $section
 * @property string $body
 * @property bool $is_flagged
 * @property int $created_by
 * @property Carbon $created_at
 */
#[Fillable([
    'institution_id',
    'clinical_case_id',
    'case_status_transition_id',
    'section',
    'body',
    'is_flagged',
    'created_by',
])]
class CaseReviewComment extends Model
{
    use BelongsToInstitution, HasUlids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'section' => CaseReviewSection::class,
            'is_flagged' => 'boolean',
        ];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    /** @return BelongsTo<CaseStatusTransition, $this> */
    public function caseStatusTransition(): BelongsTo
    {
        return $this->belongsTo(CaseStatusTransition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 4: Add the relations to `CaseVersion` and `CaseStatusTransition`**

In `app/Models/CaseVersion.php`, add the import `use Illuminate\Database\Eloquent\Relations\HasMany;` and this method (anywhere among the other relation methods):

```php
    /** @return HasMany<CaseStatusTransition, $this> */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(CaseStatusTransition::class);
    }
```

In `app/Models/CaseStatusTransition.php`, add the same `HasMany` import and:

```php
    /** @return HasMany<CaseReviewComment, $this> */
    public function reviewComments(): HasMany
    {
        return $this->hasMany(CaseReviewComment::class);
    }
```

- [ ] **Step 5: Run the test again**

Run: `docker compose exec app php artisan test tests/Unit/CaseReviewCommentSchemaTest.php`
Expected: PASS — all three tests in the file green.

- [ ] **Step 6: Commit**

```bash
git add app/Models/CaseReviewComment.php app/Models/CaseVersion.php app/Models/CaseStatusTransition.php tests/Unit/CaseReviewCommentSchemaTest.php
git commit -m "feat: add CaseReviewComment model and the three-hop version-to-comment read path"
```

---

## Task 3: `ReopenCase` action and `ClinicalCasePolicy::reopen()`

**Files:**
- Create: `app/Actions/ReopenCase.php`
- Modify: `app/Policies/ClinicalCasePolicy.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `AuditTrail::record(User, Model, string, array): AuditEvent` (existing); `CaseStatusTransition`, `ClinicalCase`, `CaseStatus` (existing).
- Produces: `ReopenCase::__invoke(User $actor, ClinicalCase $case, string $reason): void`; `ClinicalCasePolicy::reopen(User $user, ClinicalCase $case): bool`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_faculty_can_reopen_an_approved_case_and_the_prior_version_is_untouched(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
            'current_revision_number' => 1,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'original']],
            'snapshot_hash' => hash('sha256', 'original'),
            'submitted_by' => $student->id,
            'submitted_at' => now()->subDay(),
            'approved_by' => $faculty->id,
            'approved_at' => now()->subHour(),
        ]);

        $this->actingAs($faculty);

        $reopenCase = app(\App\Actions\ReopenCase::class);
        $reopenCase($faculty, $case, 'Diagnosis needs revisiting after new labs.');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
        $this->assertDatabaseHas('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Approved->value,
            'to_status' => CaseStatus::Returned->value,
            'case_version_id' => $version->id,
            'reason' => 'Diagnosis needs revisiting after new labs.',
        ]);
        $this->assertDatabaseHas('case_versions', [
            'id' => $version->id,
            'approved_by' => $faculty->id,
            'snapshot_hash' => hash('sha256', 'original'),
        ]);
    }

    public function test_reopen_is_rejected_when_the_case_is_not_approved(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        $this->assertFalse((new \App\Policies\ClinicalCasePolicy)->reopen($faculty, $case));
    }
```

Also add this private helper to `ClinicalCaseWorkflowTest` — every task from here on (and both tests above) uses it instead of repeating the institution/site/programme/rotation/assignment scaffold inline. `clinical_cases.rotation_assignment_id` and `rotation_assignments.rotation_id` are both required, non-nullable foreign keys, so a real chain is needed even for tests that don't otherwise care about rotation details:

```php
    /** @return array{Institution, User, User, RotationAssignment} */
    private function setupAssignment(): array
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $faculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Hospital', 'code' => 'HSP', 'status' => 'active']);
        $programme = Programme::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'name' => 'Pharm.D', 'code' => 'PD', 'duration_years' => 6, 'status' => 'active']);
        $rotation = Rotation::query()->withoutGlobalScopes()->create(['institution_id' => $institution->id, 'programme_id' => $programme->id, 'clinical_site_id' => $site->id, 'name' => 'Rotation', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'active']);
        $assignment = RotationAssignment::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'rotation_id' => $rotation->id,
            'student_id' => $student->id,
            'primary_preceptor_id' => $faculty->id,
            'status' => 'active',
        ]);

        return [$institution, $student, $faculty, $assignment];
    }
```

This needs the additional imports `use App\Models\ClinicalSite;`, `use App\Models\Programme;`, and `use App\Models\Rotation;` at the top of `tests/Feature/ClinicalCaseWorkflowTest.php` (mirroring `tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`'s existing imports), alongside the file's current ones. The two tests above already call it, as does every later task's new test in this file.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter reopen`
Expected: FAIL — `App\Actions\ReopenCase` and `ClinicalCasePolicy::reopen` don't exist yet.

- [ ] **Step 3: Add the policy method**

In `app/Policies/ClinicalCasePolicy.php`, add after `returnCase()`:

```php
    public function reopen(User $user, ClinicalCase $case): bool
    {
        if ($case->institution_id !== $user->institution_id) {
            return false;
        }

        return $user->role === UserRole::Faculty
            && $case->rotationAssignment->primary_preceptor_id === $user->id
            && $case->status === CaseStatus::Approved;
    }
```

- [ ] **Step 4: Write the action**

```php
<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseStatusTransition;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ReopenCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    public function __invoke(User $actor, ClinicalCase $case, string $reason): void
    {
        DB::transaction(function () use ($actor, $case, $reason): void {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless($case->status === CaseStatus::Approved, 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Approved case has no version to reopen — this should be unreachable.');

            $case->update([
                'status' => CaseStatus::Returned,
            ]);

            CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => CaseStatus::Approved->value,
                'to_status' => CaseStatus::Returned->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $reason,
            ]);

            $this->audit->record($actor, $case, 'clinical_case.reopened', [
                'case_id' => $case->id,
                'version_number' => $latestVersion->version_number,
                'reason' => $reason,
            ]);
        });
    }
}
```

- [ ] **Step 5: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS — the whole file, including the two new tests.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/ReopenCase.php app/Policies/ClinicalCasePolicy.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: add ReopenCase action and reopen authorization policy"
```

---

## Task 4: `POST /faculty/reviews/{case}/reopen` route and controller, with authorization tests

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Faculty/ReviewController.php`
- Modify: `tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `ReopenCase` (Task 3), `ClinicalCasePolicy::reopen()` (Task 3).
- Produces: `ReviewController::reopen(Request, ClinicalCase, ReopenCase): RedirectResponse`; route `faculty.reviews.reopen`.

- [ ] **Step 1: Write the failing authorization tests**

Add to `tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`:

```php
    public function test_students_cannot_reopen_a_case(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($student);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Approved);

        $this->post(route('faculty.reviews.reopen', $case))->assertForbidden();
    }

    public function test_faculty_cannot_reopen_a_cross_institution_case(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherInstitution = Institution::factory()->create();
        $otherStudent = User::factory()->student()->create(['institution_id' => $otherInstitution->id]);
        $this->actingAs($faculty);

        $otherCase = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $otherInstitution->id,
            'student_id' => $otherStudent->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
        ]);

        $this->post(route('faculty.reviews.reopen', $otherCase))->assertNotFound();
    }

    public function test_faculty_cannot_reopen_a_case_that_is_not_approved(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $this->actingAs($faculty);

        foreach ([CaseStatus::Draft, CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Returned] as $index => $status) {
            $case = $this->createCase($institution, $student, $assignment, $status, $index + 1);

            $this->post(route('faculty.reviews.reopen', $case), ['reason' => 'Test'])->assertForbidden();
        }
    }

    public function test_unassigned_faculty_cannot_reopen_a_case(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupInstitution();
        $otherFaculty = User::factory()->faculty()->create(['institution_id' => $institution->id]);
        $this->actingAs($otherFaculty);

        $case = $this->createCase($institution, $student, $assignment, CaseStatus::Approved);

        $this->post(route('faculty.reviews.reopen', $case), ['reason' => 'Test'])->assertForbidden();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php --filter reopen`
Expected: FAIL — route `faculty.reviews.reopen` doesn't exist.

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the existing `faculty/reviews/{case}/return` line:

```php
        Route::post('faculty/reviews/{case}/reopen', [ReviewController::class, 'reopen'])->name('faculty.reviews.reopen');
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/Faculty/ReviewController.php`, add the import `use App\Actions\ReopenCase;` and, after `returnCase()`:

```php
    public function reopen(Request $request, ClinicalCase $case, ReopenCase $reopenCase): RedirectResponse
    {
        Gate::authorize('reopen', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reopenCase($request->user(), $case, $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case reopened.']);
    }
```

- [ ] **Step 5: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php`
Expected: PASS — full file green.

- [ ] **Step 6: Write and run an end-to-end HTTP test, then commit**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_reopen_http_endpoint_moves_an_approved_case_back_to_returned(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
            'current_revision_number' => 1,
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'original']],
            'snapshot_hash' => hash('sha256', 'original'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.reopen', $case), ['reason' => 'Please re-check the dosing.'])
            ->assertRedirect();

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
    }
```

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS.

```bash
git add routes/web.php app/Http/Controllers/Faculty/ReviewController.php tests/Feature/Authorization/ClinicalCaseAuthorizationTest.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: add faculty reopen-case route and controller endpoint"
```

---

## Task 5: Extend `ReturnCase` — row-lock, `case_version_id`, section comments, atomicity

**Files:**
- Modify: `app/Actions/ReturnCase.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `CaseReviewComment` (Task 2).
- Produces: `ReturnCase::__invoke(User $actor, ClinicalCase $case, string $reason, array $sectionComments): void`, where `$sectionComments` is `array<int, array{section: string, body: string, is_flagged: bool}>`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php` (this replaces the need to change the existing `test_faculty_can_return_case_for_correction` test's call signature — update that existing test's `$returnCase($faculty, $case, 'Please add more detail to the assessment section.');` call to pass a fourth argument: `[['section' => 'soap', 'body' => 'Please add more detail.', 'is_flagged' => true]]`, and add):

```php
    public function test_return_creates_section_comments_tied_to_the_return_transition(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $returnCase = app(\App\Actions\ReturnCase::class);
        $returnCase($faculty, $case, 'See flagged sections.', [
            ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ['section' => 'medication_chart', 'body' => 'Looks good.', 'is_flagged' => false],
        ]);

        $transition = \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()
            ->where('clinical_case_id', $case->id)
            ->where('to_status', CaseStatus::Returned->value)
            ->sole();

        $this->assertSame($version->id, $transition->case_version_id);
        $this->assertDatabaseHas('case_review_comments', [
            'case_status_transition_id' => $transition->id,
            'section' => 'soap',
            'is_flagged' => true,
        ]);
        $this->assertDatabaseHas('case_review_comments', [
            'case_status_transition_id' => $transition->id,
            'section' => 'medication_chart',
            'is_flagged' => false,
        ]);
    }

    public function test_return_is_rejected_once_the_case_has_already_moved_past_review(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
        ]);

        $this->actingAs($faculty);

        $returnCase = app(\App\Actions\ReturnCase::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $returnCase($faculty, $case, 'Too late.', [
            ['section' => 'soap', 'body' => 'x', 'is_flagged' => true],
        ]);
    }

    public function test_a_failed_return_leaves_no_transition_or_comment_behind(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $returnCase = app(\App\Actions\ReturnCase::class);

        // Two entries for the same section violate the DB unique constraint
        // on (case_status_transition_id, section) — this is what a bug in
        // the controller's own duplicate-section check (Task 7) would let
        // through, and the action must still roll back cleanly if it does.
        try {
            $returnCase($faculty, $case, 'Duplicate section.', [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => true],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ]);
            $this->fail('Expected a database exception for the duplicate section.');
        } catch (\Illuminate\Database\QueryException) {
            // expected
        }

        $this->assertDatabaseMissing('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'to_status' => CaseStatus::Returned->value,
        ]);
        $this->assertDatabaseMissing('case_review_comments', [
            'clinical_case_id' => $case->id,
        ]);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter return`
Expected: FAIL — `ReturnCase::__invoke` doesn't accept a fourth argument yet, and the existing return test's call is now a type error.

- [ ] **Step 3: Rewrite `ReturnCase`**

```php
<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ReturnCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    /**
     * @param array<int, array{section: string, body: string, is_flagged: bool}> $sectionComments
     */
    public function __invoke(User $actor, ClinicalCase $case, string $reason, array $sectionComments): void
    {
        DB::transaction(function () use ($actor, $case, $reason, $sectionComments): void {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless(in_array($case->status, [CaseStatus::Submitted, CaseStatus::UnderReview], true), 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Case has no submitted version to return — this should be unreachable.');

            $fromStatus = $case->status;

            $case->update([
                'status' => CaseStatus::Returned,
            ]);

            $transition = CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Returned->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $reason,
            ]);

            foreach ($sectionComments as $comment) {
                CaseReviewComment::query()->create([
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'case_status_transition_id' => $transition->id,
                    'section' => $comment['section'],
                    'body' => $comment['body'],
                    'is_flagged' => $comment['is_flagged'],
                    'created_by' => $actor->id,
                ]);
            }

            $this->audit->record($actor, $case, 'clinical_case.returned', [
                'case_id' => $case->id,
                'reason' => $reason,
            ]);
        });
    }
}
```

- [ ] **Step 4: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS — all tests in the file, including the updated existing return test.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/ReturnCase.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: extend ReturnCase with row-lock, case_version_id, and section comments"
```

---

## Task 6: Extend `ApproveCase` — row-lock, optional section comments forced unflagged

**Files:**
- Modify: `app/Actions/ApproveCase.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `CaseReviewComment` (Task 2).
- Produces: `ApproveCase::__invoke(User $actor, ClinicalCase $case, ?string $summary = null, array $sectionComments = []): CaseVersion`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_approve_forces_is_flagged_false_even_if_the_caller_sends_true(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $approveCase = app(ApproveCase::class);
        $approveCase($faculty, $case, 'Great work.', [
            // A malicious or buggy client sends is_flagged: true on approve.
            ['section' => 'soap', 'body' => 'Nicely reasoned assessment.', 'is_flagged' => true],
        ]);

        $this->assertDatabaseHas('case_review_comments', [
            'clinical_case_id' => $case->id,
            'section' => 'soap',
            'is_flagged' => false,
        ]);
    }

    public function test_a_failed_approve_leaves_no_transition_or_comment_behind(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $approveCase = app(ApproveCase::class);

        try {
            $approveCase($faculty, $case, 'Duplicate section.', [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => false],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ]);
            $this->fail('Expected a database exception for the duplicate section.');
        } catch (\Illuminate\Database\QueryException) {
            // expected
        }

        $this->assertDatabaseMissing('case_status_transitions', [
            'clinical_case_id' => $case->id,
            'to_status' => CaseStatus::Approved->value,
        ]);
        $this->assertDatabaseMissing('case_review_comments', ['clinical_case_id' => $case->id]);
        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
    }

    public function test_approve_is_rejected_once_the_case_has_already_moved_past_review(): void
    {
        // The pre-Slice-4 ApproveCase had no status guard at all. This proves
        // the new row-lock-and-recheck guard actually rejects an approve
        // attempt once the case is no longer Submitted/UnderReview — the
        // same simulated-race shape as ReturnCase's equivalent test.
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Draft,
        ]);

        $this->actingAs($faculty);

        $approveCase = app(ApproveCase::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $approveCase($faculty, $case, 'Too early.', []);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter approve`
Expected: FAIL — `ApproveCase::__invoke` doesn't accept a fourth argument yet.

- [ ] **Step 3: Rewrite `ApproveCase`**

```php
<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

class ApproveCase
{
    public function __construct(
        private AuditTrail $audit,
    ) {}

    /**
     * @param array<int, array{section: string, body: string, is_flagged?: bool}> $sectionComments
     */
    public function __invoke(User $actor, ClinicalCase $case, ?string $summary = null, array $sectionComments = []): CaseVersion
    {
        return DB::transaction(function () use ($actor, $case, $summary, $sectionComments): CaseVersion {
            ClinicalCase::query()->whereKey($case->id)->lockForUpdate()->first();
            $case->refresh();

            abort_unless(in_array($case->status, [CaseStatus::Submitted, CaseStatus::UnderReview], true), 409);

            $latestVersion = $case->versions()->latest('version_number')->first();
            abort_unless($latestVersion !== null, 500, 'Case has no submitted versions.');

            $fromStatus = $case->status;

            $latestVersion->update([
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $case->update([
                'status' => CaseStatus::Approved,
                'approved_at' => now(),
            ]);

            $transition = CaseStatusTransition::query()->create([
                'institution_id' => $case->institution_id,
                'clinical_case_id' => $case->id,
                'from_status' => $fromStatus->value,
                'to_status' => CaseStatus::Approved->value,
                'actor_id' => $actor->id,
                'case_version_id' => $latestVersion->id,
                'reason' => $summary,
            ]);

            foreach ($sectionComments as $comment) {
                CaseReviewComment::query()->create([
                    'institution_id' => $case->institution_id,
                    'clinical_case_id' => $case->id,
                    'case_status_transition_id' => $transition->id,
                    'section' => $comment['section'],
                    'body' => $comment['body'],
                    'is_flagged' => false,
                    'created_by' => $actor->id,
                ]);
            }

            $this->audit->record($actor, $case, 'clinical_case.approved', [
                'case_id' => $case->id,
                'version_number' => $latestVersion->version_number,
            ]);

            return $latestVersion;
        });
    }
}
```

- [ ] **Step 4: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS — all tests in the file, including the pre-existing `test_faculty_can_approve_submitted_case`.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/ApproveCase.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: extend ApproveCase with row-lock and forced-unflagged section comments"
```

---

## Task 7: Controller validation, eager-loads, and HTTP-level validation tests

**Files:**
- Modify: `app/Http/Controllers/Faculty/ReviewController.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `CaseReviewSection` (Task 1), extended `ReturnCase`/`ApproveCase` (Tasks 5, 6).
- Produces: `approve()`/`returnCase()` accept and validate a `section_comments` array; `show()`/`index()` eager-load the full comment history via `'versions.statusTransitions.reviewComments.author'`.

- [ ] **Step 1: Write the failing validation tests**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_return_http_endpoint_requires_at_least_one_flagged_section_comment(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Fine as-is.', 'is_flagged' => false],
            ],
        ])->assertSessionHasErrors('section_comments');

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Submitted->value]);
    }

    public function test_return_http_endpoint_rejects_a_duplicate_section_in_the_request(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'First.', 'is_flagged' => true],
                ['section' => 'soap', 'body' => 'Second.', 'is_flagged' => false],
            ],
        ])->assertSessionHasErrors('section_comments');
    }

    public function test_return_http_endpoint_succeeds_with_a_valid_flagged_section_comment(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('clinical_cases', ['id' => $case->id, 'status' => CaseStatus::Returned->value]);
    }

    public function test_review_show_page_exposes_the_full_comment_history(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Submitted,
            'submitted_at' => now(),
        ]);

        CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty);

        $this->post(route('faculty.reviews.return', $case), [
            'reason' => 'Needs work.',
            'section_comments' => [
                ['section' => 'soap', 'body' => 'Expand the assessment.', 'is_flagged' => true],
            ],
        ]);

        $response = $this->get(route('faculty.reviews.show', $case));
        $response->assertInertia(fn ($page) => $page
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.body', 'Expand the assessment.')
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.author.id', $faculty->id));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter "return_http|review_show"`
Expected: FAIL — the controller doesn't accept/validate `section_comments` yet, and `show()` doesn't eager-load the comment chain.

- [ ] **Step 3: Update the controller**

Replace the full contents of `app/Http/Controllers/Faculty/ReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Faculty;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\ReturnCase;
use App\Enums\CaseReviewSection;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ClinicalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $cases = ClinicalCase::query()
            ->whereHas('rotationAssignment', fn ($q) => $q->where('primary_preceptor_id', $user->id))
            ->whereIn('status', [CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Returned, CaseStatus::Approved])
            ->with(['student', 'clinicalSite'])
            ->orderByDesc('submitted_at')
            ->get();

        return Inertia::render('faculty/Reviews', [
            'cases' => $cases,
        ]);
    }

    public function show(Request $request, ClinicalCase $case): Response
    {
        Gate::authorize('view', $case);

        $case->load([
            'student',
            'clinicalSite',
            'department',
            'ward',
            'currentSoap',
            'versions.submittedBy',
            'versions.approvedBy',
            'versions.statusTransitions.reviewComments.author',
            'statusTransitions.actor',
        ]);

        return Inertia::render('faculty/CaseReview', [
            'clinicalCase' => $case,
        ]);
    }

    public function approve(Request $request, ClinicalCase $case, ApproveCase $approveCase): RedirectResponse
    {
        Gate::authorize('approve', $case);

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'section_comments' => ['sometimes', 'array'],
            'section_comments.*.section' => ['required', Rule::enum(CaseReviewSection::class)],
            'section_comments.*.body' => ['required', 'string', 'max:2000'],
        ]);

        $sectionComments = $data['section_comments'] ?? [];
        $this->assertDistinctSections($sectionComments);

        $approveCase($request->user(), $case, $data['summary'] ?? null, $sectionComments);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case approved.']);
    }

    public function returnCase(Request $request, ClinicalCase $case, ReturnCase $returnCase): RedirectResponse
    {
        Gate::authorize('returnCase', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'section_comments' => ['required', 'array', 'min:1'],
            'section_comments.*.section' => ['required', Rule::enum(CaseReviewSection::class)],
            'section_comments.*.body' => ['required', 'string', 'max:2000'],
            'section_comments.*.is_flagged' => ['required', 'boolean'],
        ]);

        $this->assertDistinctSections($data['section_comments']);

        if (! collect($data['section_comments'])->contains('is_flagged', true)) {
            throw ValidationException::withMessages([
                'section_comments' => 'At least one section must be flagged to return a case.',
            ]);
        }

        $returnCase($request->user(), $case, $data['reason'], $data['section_comments']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case returned for correction.']);
    }

    public function reopen(Request $request, ClinicalCase $case, ReopenCase $reopenCase): RedirectResponse
    {
        Gate::authorize('reopen', $case);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reopenCase($request->user(), $case, $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Case reopened.']);
    }

    /** @param array<int, array{section: string}> $sectionComments */
    private function assertDistinctSections(array $sectionComments): void
    {
        $sections = array_column($sectionComments, 'section');

        if (count($sections) !== count(array_unique($sections))) {
            throw ValidationException::withMessages([
                'section_comments' => 'Each section can only receive one comment per review.',
            ]);
        }
    }
}
```

- [ ] **Step 4: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS — all tests in the file.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Faculty/ReviewController.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: validate section comments on return/approve and eager-load the full review history"
```

---

## Task 8: Student `CaseShow` — permanent feedback history at every status

**Files:**
- Modify: `app/Http/Controllers/Student/CaseController.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: eager-load path from Task 2/7.
- Produces: `CaseController::show()` exposes `versions.statusTransitions.reviewComments.author` to `student/CaseShow.vue`, reachable at every case status (spec §6).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_student_case_show_exposes_feedback_history_even_after_approval(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Approved,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        $transition = \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Approved->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'Well documented.',
        ]);

        \App\Models\CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $transition->id,
            'section' => \App\Enums\CaseReviewSection::Soap,
            'body' => 'Nicely reasoned.',
            'is_flagged' => false,
            'created_by' => $faculty->id,
        ]);

        $this->actingAs($student);

        $response = $this->get(route('student.cases.show', $case));
        $response->assertInertia(fn ($page) => $page
            ->where('clinicalCase.versions.0.status_transitions.0.review_comments.0.body', 'Nicely reasoned.'));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter student_case_show`
Expected: FAIL — `CaseController::show()` doesn't eager-load the comment chain yet.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Student/CaseController.php`, change:

```php
        $case->load(['clinicalSite', 'department', 'ward', 'currentSoap', 'versions']);
```

to:

```php
        $case->load([
            'clinicalSite',
            'department',
            'ward',
            'currentSoap',
            'versions.submittedBy',
            'versions.approvedBy',
            'versions.statusTransitions.reviewComments.author',
        ]);
```

- [ ] **Step 4: Run the test again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Student/CaseController.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: expose the full review-comment history on the student case-detail page"
```

---

## Task 9: `ClinicalCasePresenter::reviewFeedback()` and `CaseEditorController` props

**Files:**
- Modify: `app/Services/ClinicalCasePresenter.php`
- Modify: `app/Http/Controllers/Student/CaseEditorController.php`
- Test: `tests/Feature/ClinicalCaseWorkflowTest.php` (extend)

**Interfaces:**
- Consumes: `CaseStatus`, `CaseReviewSection`, relations from Tasks 1–2.
- Produces: `ClinicalCasePresenter::reviewFeedback(ClinicalCase $case): array{flaggedSections: list<string>, reopenedReason: string|null}`; `CaseEditorController::show()` passes it as the `reviewFeedback` Inertia prop.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ClinicalCaseWorkflowTest.php`:

```php
    public function test_editor_shows_flagged_sections_from_the_latest_return_round_only(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now()->subDays(2),
        ]);

        // Round 1 (older): flags case_profile — must NOT appear as current.
        $oldTransition = \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'First round.',
            'created_at' => now()->subDay(),
        ]);
        \App\Models\CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $oldTransition->id,
            'section' => \App\Enums\CaseReviewSection::CaseProfile,
            'body' => 'Old round.',
            'is_flagged' => true,
            'created_by' => $faculty->id,
        ]);

        // Round 2 (latest): flags soap — this IS the current round.
        $newTransition = \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Submitted->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'Second round.',
            'created_at' => now(),
        ]);
        \App\Models\CaseReviewComment::query()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'case_status_transition_id' => $newTransition->id,
            'section' => \App\Enums\CaseReviewSection::Soap,
            'body' => 'Latest round.',
            'is_flagged' => true,
            'created_by' => $faculty->id,
        ]);

        $presenter = app(\App\Services\ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame(['soap'], $feedback['flaggedSections']);
        $this->assertNull($feedback['reopenedReason']);
    }

    public function test_editor_shows_the_reopen_reason_and_no_flags_after_a_reopen(): void
    {
        [$institution, $student, $faculty, $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
        ]);

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => $student->id,
            'submitted_at' => now(),
            'approved_by' => $faculty->id,
            'approved_at' => now(),
        ]);

        \App\Models\CaseStatusTransition::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'clinical_case_id' => $case->id,
            'from_status' => CaseStatus::Approved->value,
            'to_status' => CaseStatus::Returned->value,
            'actor_id' => $faculty->id,
            'case_version_id' => $version->id,
            'reason' => 'New labs changed the diagnosis.',
        ]);

        $presenter = app(\App\Services\ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame([], $feedback['flaggedSections']);
        $this->assertSame('New labs changed the diagnosis.', $feedback['reopenedReason']);
    }

    public function test_editor_review_feedback_is_empty_for_a_returned_case_with_no_transition_row(): void
    {
        // Backward compatibility: a case already Returned before this table
        // existed has no matching CaseStatusTransition/CaseReviewComment rows.
        [$institution, $student, , $assignment] = $this->setupAssignment();

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => CaseStatus::Returned,
        ]);

        $presenter = app(\App\Services\ClinicalCasePresenter::class);
        $feedback = $presenter->reviewFeedback($case);

        $this->assertSame(['flaggedSections' => [], 'reopenedReason' => null], $feedback);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php --filter "editor_shows|review_feedback_is_empty"`
Expected: FAIL — `ClinicalCasePresenter::reviewFeedback()` doesn't exist yet.

- [ ] **Step 3: Add the presenter method**

In `app/Services/ClinicalCasePresenter.php`, add the imports `use App\Enums\CaseReviewSection;`, `use App\Enums\CaseStatus;` (not yet imported in this file — the method body below needs `CaseStatus::Returned`/`CaseStatus::Approved`), and `use App\Models\CaseReviewComment;`, then add this method:

```php
    /** @return array{flaggedSections: list<string>, reopenedReason: string|null} */
    public function reviewFeedback(ClinicalCase $case): array
    {
        if ($case->status !== CaseStatus::Returned) {
            return ['flaggedSections' => [], 'reopenedReason' => null];
        }

        $transition = $case->statusTransitions()
            ->where('to_status', CaseStatus::Returned->value)
            ->latest('created_at')
            ->with('reviewComments')
            ->first();

        if ($transition === null) {
            return ['flaggedSections' => [], 'reopenedReason' => null];
        }

        if ($transition->from_status === CaseStatus::Approved->value) {
            return ['flaggedSections' => [], 'reopenedReason' => $transition->reason];
        }

        $flaggedSections = $transition->reviewComments
            ->where('is_flagged', true)
            ->map(fn (CaseReviewComment $comment): string => $comment->section->value)
            ->values()
            ->all();

        return ['flaggedSections' => $flaggedSections, 'reopenedReason' => null];
    }
```

- [ ] **Step 4: Wire the prop into `CaseEditorController`**

In `app/Http/Controllers/Student/CaseEditorController.php`, add this line to the `Inertia::render` array, alongside `'initialSection'`:

```php
            'reviewFeedback' => $presenter->reviewFeedback($case),
```

- [ ] **Step 5: Run the tests again**

Run: `docker compose exec app php artisan test tests/Feature/ClinicalCaseWorkflowTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/ClinicalCasePresenter.php app/Http/Controllers/Student/CaseEditorController.php tests/Feature/ClinicalCaseWorkflowTest.php
git commit -m "feat: compute current flagged sections and reopen reason for the case editor"
```

---

## Task 10: Faculty `CaseReview.vue` — section-comment composer and reopen action

**Files:**
- Modify: `resources/js/pages/faculty/CaseReview.vue`

**Interfaces:**
- Consumes: `section_comments` request field (Task 7); `reopen` route (Task 4); existing `Case`/`CaseVersion`/`StatusTransition` types already in this file.

- [ ] **Step 1: Add the review-comments type and composer state**

In `resources/js/pages/faculty/CaseReview.vue`, extend the existing types and form state (after the `StatusTransition` type, before `Case`):

```ts
type ReviewComment = {
    id: string;
    section: string;
    body: string;
    is_flagged: boolean;
    author: { name: string };
};

const REVIEW_SECTIONS: { key: string; label: string }[] = [
    { key: 'case_profile', label: 'Case Profile' },
    { key: 'history_diagnosis', label: 'History & Diagnosis' },
    { key: 'vitals_investigations', label: 'Vitals & Investigations' },
    { key: 'medication_chart', label: 'Medication Chart' },
    { key: 'soap', label: 'SOAP' },
    { key: 'clinical_activities', label: 'Conditional Clinical Activities' },
];
```

Extend `StatusTransition` to carry `review_comments: ReviewComment[]` and add `case_version_id: string | null`, and extend `CaseVersion` to carry `status_transitions: StatusTransition[]`:

```ts
type StatusTransition = {
    id: string;
    from_status: string;
    to_status: string;
    reason: string | null;
    created_at: string;
    actor: { name: string };
    case_version_id: string | null;
    review_comments: ReviewComment[];
};

type CaseVersion = {
    id: string;
    version_number: number;
    submitted_at: string;
    approved_at: string | null;
    snapshot: Record<string, unknown>;
    submitted_by: { name: string };
    approved_by: { name: string } | null;
    status_transitions: StatusTransition[];
};
```

Replace the two `useForm` calls and add a reopen form and per-section composer state:

```ts
type SectionCommentDraft = { body: string; is_flagged: boolean };

const sectionDrafts = ref<Record<string, SectionCommentDraft>>(
    Object.fromEntries(
        REVIEW_SECTIONS.map((s) => [s.key, { body: '', is_flagged: false }]),
    ),
);

const buildSectionComments = () =>
    REVIEW_SECTIONS.filter((s) => sectionDrafts.value[s.key].body.trim() !== '').map(
        (s) => ({
            section: s.key,
            body: sectionDrafts.value[s.key].body,
            is_flagged: sectionDrafts.value[s.key].is_flagged,
        }),
    );

const approveForm = useForm({
    summary: '',
    section_comments: [] as { section: string; body: string }[],
});
const returnForm = useForm({
    reason: '',
    section_comments: [] as { section: string; body: string; is_flagged: boolean }[],
});
const reopenForm = useForm({
    reason: '',
});

const approve = () => {
    approveForm.section_comments = buildSectionComments();
    approveForm.post(`/faculty/reviews/${props.clinicalCase.id}/approve`, {
        preserveScroll: true,
    });
};

const returnCase = () => {
    returnForm.section_comments = buildSectionComments();
    returnForm.post(`/faculty/reviews/${props.clinicalCase.id}/return`, {
        preserveScroll: true,
    });
};

const reopen = () =>
    reopenForm.post(`/faculty/reviews/${props.clinicalCase.id}/reopen`, {
        preserveScroll: true,
    });

const canReopen = (): boolean => props.clinicalCase.status === 'approved';
```

Don't forget the `import { ref } from 'vue';` alongside the existing `Head, router, useForm` import.

- [ ] **Step 2: Add the composer and reopen UI to the template**

Insert this section immediately before the existing `<section v-if="canReview()" ...>` block:

```html
        <section
            v-if="canReview()"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 class="font-display text-lg font-semibold text-[#0b2942] dark:text-white">
                Section comments
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Flag a section to require correction when returning this case. Comments are optional when approving.
            </p>
            <div class="mt-4 space-y-4">
                <div
                    v-for="section in REVIEW_SECTIONS"
                    :key="section.key"
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold">{{ section.label }}</span>
                        <label class="flex items-center gap-1.5 text-xs text-orange-700">
                            <input
                                type="checkbox"
                                v-model="sectionDrafts[section.key].is_flagged"
                            />
                            Flag for correction
                        </label>
                    </div>
                    <textarea
                        v-model="sectionDrafts[section.key].body"
                        class="border-input bg-background mt-2 min-h-[60px] w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Comment on this section..."
                    />
                </div>
            </div>
        </section>

        <section
            v-if="canReopen()"
            class="rounded-3xl border border-purple-200 bg-purple-50/50 p-5 sm:p-7 dark:border-purple-800 dark:bg-purple-950/20"
        >
            <h2 class="font-display text-lg font-semibold text-[#0b2942] dark:text-white">
                Reopen case
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Reopening returns this approved case for correction. This is exceptional and requires a reason.
            </p>
            <form class="mt-4" @submit.prevent="reopen">
                <textarea
                    v-model="reopenForm.reason"
                    class="border-input bg-background min-h-[80px] w-full rounded-md border px-3 py-2 text-sm"
                    placeholder="Why is this case being reopened?"
                    required
                />
                <InputError :message="reopenForm.errors.reason" />
                <Button
                    type="submit"
                    class="mt-3 bg-purple-700 text-white"
                    :disabled="reopenForm.processing || !reopenForm.reason"
                >
                    <RotateCcw class="mr-1 size-4" /> Reopen
                </Button>
            </form>
        </section>
```

- [ ] **Step 3: Verify the frontend build**

Run: `docker compose exec app npm run types:check`
Expected: PASS — no TypeScript errors.

Run: `docker compose exec app npm run check`
Expected: PASS — no lint/format errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/faculty/CaseReview.vue
git commit -m "feat: add section-comment composer and reopen action to the faculty review page"
```

---

## Task 11: Student `CaseShow.vue` — permanent feedback-history panel

**Files:**
- Modify: `resources/js/pages/student/CaseShow.vue`

**Interfaces:**
- Consumes: `versions.statusTransitions.reviewComments.author` prop from Task 8.

- [ ] **Step 1: Extend the types**

In `resources/js/pages/student/CaseShow.vue`, extend the `CaseVersion` type and add the two new types (mirroring Task 10's shapes exactly so both pages read the same wire format):

```ts
type ReviewComment = {
    id: string;
    section: string;
    body: string;
    is_flagged: boolean;
    author: { name: string };
};

type StatusTransition = {
    id: string;
    from_status: string;
    to_status: string;
    reason: string | null;
    created_at: string;
    actor: { name: string };
    review_comments: ReviewComment[];
};

type CaseVersion = {
    id: string;
    version_number: number;
    submitted_at: string;
    approved_at: string | null;
    submitted_by: { name: string };
    approved_by: { name: string } | null;
    status_transitions: StatusTransition[];
};
```

- [ ] **Step 2: Add the history panel to the template**

Insert immediately after the existing "Submission history" `<section>` block:

```html
        <section
            v-if="clinicalCase.versions.some((v) => v.status_transitions.length)"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                Faculty feedback
            </h2>
            <div class="mt-4 space-y-4">
                <template
                    v-for="version in clinicalCase.versions"
                    :key="`feedback-${version.id}`"
                >
                    <div
                        v-for="transition in version.status_transitions"
                        :key="transition.id"
                        class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <p class="text-xs text-slate-500">
                            Version {{ version.version_number }} ·
                            {{ transition.actor.name }} ·
                            {{ formatDate(transition.created_at) }}
                        </p>
                        <p v-if="transition.reason" class="mt-1 text-sm">
                            {{ transition.reason }}
                        </p>
                        <ul
                            v-if="transition.review_comments.length"
                            class="mt-2 space-y-1"
                        >
                            <li
                                v-for="comment in transition.review_comments"
                                :key="comment.id"
                                class="text-sm"
                            >
                                <span
                                    v-if="comment.is_flagged"
                                    class="mr-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700"
                                >
                                    Flagged
                                </span>
                                {{ comment.body }}
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
        </section>
```

- [ ] **Step 3: Verify the frontend build**

Run: `docker compose exec app npm run types:check`
Expected: PASS.

Run: `docker compose exec app npm run check`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/student/CaseShow.vue
git commit -m "feat: show permanent faculty-feedback history on the student case-detail page"
```

---

## Task 12: Student `CaseEditor.vue` — flagged-section badges and reopened banner

**Files:**
- Modify: `resources/js/pages/student/CaseEditor.vue`

**Interfaces:**
- Consumes: `reviewFeedback: { flaggedSections: string[]; reopenedReason: string | null }` prop from Task 9.

- [ ] **Step 1: Locate the section-nav list and props**

Run: `docker compose exec app grep -n "defineProps\|initialSection\|sections" resources/js/pages/student/CaseEditor.vue`

Read the surrounding ~30 lines around the `defineProps` call and the section-nav render loop before editing, since this file's exact prop list and nav markup were not reproduced here — this step exists so the implementer edits the real current file rather than guessing its shape.

- [ ] **Step 2: Add the prop and banner**

Add `reviewFeedback: { flaggedSections: string[]; reopenedReason: string | null }` to the existing `defineProps<{...}>()` type. Add near the top of the template, before the section-nav element:

```html
        <div
            v-if="reviewFeedback.reopenedReason"
            class="rounded-2xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-900 dark:border-purple-800 dark:bg-purple-950/30 dark:text-purple-100"
        >
            <strong>Case reopened:</strong> {{ reviewFeedback.reopenedReason }}
        </div>
```

In the section-nav render loop, add a badge when the iterated section's key is in `reviewFeedback.flaggedSections` — e.g., if the loop item exposes a `key` matching the six `CaseReviewSection` values (`case_profile`, `history_diagnosis`, `vitals_investigations`, `medication_chart`, `soap`, `clinical_activities`):

```html
            <span
                v-if="reviewFeedback.flaggedSections.includes(section.key)"
                class="ml-1 rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-700"
            >
                Flagged
            </span>
```

- [ ] **Step 3: Verify the frontend build**

Run: `docker compose exec app npm run types:check`
Expected: PASS.

Run: `docker compose exec app npm run check`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/student/CaseEditor.vue
git commit -m "feat: highlight flagged sections and show the reopened-case banner in the case editor"
```

---

## Task 13: Full-suite verification and PROJECT_STATE.md update

**Files:**
- Modify: `PROJECT_STATE.md`

**Interfaces:**
- Consumes: nothing new — this task only runs and records verification, per this repo's existing acceptance convention (see Slice 3's entry in `PROJECT_STATE.md` §3).

- [ ] **Step 1: Run the full backend suite**

Run: `docker compose exec app php artisan test`
Expected: PASS — every test green, including all tests added in Tasks 1–9.

- [ ] **Step 2: Run static analysis and formatting**

Run: `docker compose exec app vendor/bin/phpstan analyse`
Expected: 0 errors.

Run: `docker compose exec app vendor/bin/pint --test`
Expected: no files need formatting.

- [ ] **Step 3: Run frontend checks and the production build**

Run: `docker compose exec app npm run check`
Run: `docker compose exec app npm run types:check`
Run: `docker compose exec app npm run build`
Expected: all three PASS.

- [ ] **Step 4: Manual verification of the master plan's exit evidence**

Using the running Docker dev environment (per `reference_repo_environment` — do not use the Railway staging project for this pass unless the human partner asks for it; that lane is for UI/mobile smoke tests after Docker/CI is green), walk through, as a real browser session:

1. Submit a case as a student, then as the assigned faculty member return it with one flagged section comment and an overall reason. Confirm the case shows `Returned`, the flagged section is highlighted in the student's editor, and the reason is visible.
2. As the student, correct the flagged section and resubmit. Confirm a new `CaseVersion` row exists and the flag no longer highlights.
3. As faculty, approve the resubmitted case. Confirm `Approved`.
4. As faculty, reopen the approved case with a reason. Confirm the case is `Returned`, the student's editor shows the "Case reopened: …" banner (not a flagged section), and the original approved `CaseVersion` row is unchanged in the database.
5. Correct and resubmit again, then approve again. Confirm the full feedback history (both rounds) is visible on the student's case-detail page and on the faculty review page.
6. Attempt each of: a student reopening a case, an unassigned faculty member reviewing/reopening a case, and a faculty member from another institution opening the case URL directly — confirm each is rejected.

- [ ] **Step 5: Update `PROJECT_STATE.md`**

Following the existing pattern for Slices 1–3 in `PROJECT_STATE.md` §3, add a "Slice 4 — Faculty review, correction and reopening (accepted and merged)" entry once this branch is reviewed and merged, recording: the pull request and merge commit, the plan and spec file paths, what was delivered (summarized from this plan's tasks), the verification evidence from Steps 1–4 above, and any deferred follow-ups discovered during implementation. Update §1's "Milestone status" and "Exact next action" rows to point at Slice 5 (Targets, progress, PDF and retention), per the master plan's slice order.

- [ ] **Step 6: Commit**

```bash
git add PROJECT_STATE.md
git commit -m "docs: accept Slice 4 (faculty review, correction and reopening)"
```

## Self-Review

**Spec coverage:** §1 goal/exclusions → Global Constraints + every task's scope. §3 data model/enum/read-path → Tasks 1–2. §4 status machine/reopen → Task 3. §5 actions/authorization/row-lock/overall-feedback-contract → Tasks 3, 5, 6, 7. §6 read/visibility split (editor vs. CaseShow) → Tasks 8, 9, 11, 12. §7 endpoints → Tasks 4, 7. §8 frontend → Tasks 10, 11, 12. §9 testing plan (authorization, concurrency, validation, atomicity, immutability, round trips, history correctness, visibility) → present in Tasks 3–9's own test steps, one-to-one. §10 files touched → matches every task's Files block.

**Placeholder scan:** no TBD/"add appropriate"/"similar to Task N" markers; Task 12 names a genuine unknown (the editor's exact current section-nav markup) but gives an explicit `grep` command and instructs reading the real file before editing, rather than guessing or hand-waving the diff — the only task where the plan can't show the exact surrounding code because it wasn't read during planning.

**Type consistency:** `$sectionComments` is `array<int, array{section: string, body: string, is_flagged: bool}>` in `ReturnCase` and `array<int, array{section: string, body: string, is_flagged?: bool}>` (optional, ignored) in `ApproveCase` consistently across Tasks 5–7; `CaseReviewComment::section` is cast to `CaseReviewSection` everywhere it's read (Tasks 2, 9); the `reviewFeedback` shape (`{flaggedSections: list<string>, reopenedReason: string|null}`) matches between the PHP presenter (Task 9) and the two Vue consumers (Tasks 11, 12 use the same field names verbatim); `ReviewComment`/`StatusTransition`/`CaseVersion` TypeScript types are defined identically in both `CaseReview.vue` (Task 10) and `CaseShow.vue` (Task 11).

**Review Focus:** all five items list a task above; none were skipped.
