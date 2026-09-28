<?php

namespace Tests\Unit;

use App\Enums\CaseReviewSection;
use App\Models\CaseReviewComment;
use App\Models\CaseStatusTransition;
use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
            'id' => (string) Str::ulid(),
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'case_status_transition_id' => $transitionId,
            'section' => 'soap',
            'body' => 'First comment.',
            'is_flagged' => true,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('case_review_comments')->insert([
            'id' => (string) Str::ulid(),
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

    public function test_a_comment_is_reachable_from_its_case_version_through_the_transition(): void
    {
        [$institutionId, $caseId, $transitionId, $facultyId] = $this->seedTransition();

        $version = CaseVersion::query()->withoutGlobalScopes()->create([
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'version_number' => 1,
            'source_revision_number' => 1,
            'snapshot' => ['soap' => ['subjective' => 'test']],
            'snapshot_hash' => hash('sha256', 'test'),
            'submitted_by' => ClinicalCase::query()->withoutGlobalScopes()->find($caseId)->student_id,
            'submitted_at' => now(),
        ]);

        CaseStatusTransition::query()->withoutGlobalScopes()
            ->whereKey($transitionId)
            ->update(['case_version_id' => $version->id]);

        CaseReviewComment::query()->create([
            'institution_id' => $institutionId,
            'clinical_case_id' => $caseId,
            'case_status_transition_id' => $transitionId,
            'section' => CaseReviewSection::Soap,
            'body' => 'Please expand the plan.',
            'is_flagged' => true,
            'created_by' => $facultyId,
        ]);

        $reloaded = CaseVersion::query()->withoutGlobalScopes()
            ->with('statusTransitions.reviewComments.author')
            ->findOrFail($version->id);

        $comment = $reloaded->statusTransitions->first()->reviewComments->first();

        $this->assertNotNull($comment);
        $this->assertSame(CaseReviewSection::Soap, $comment->section);
        $this->assertTrue($comment->is_flagged);
        $this->assertSame($facultyId, $comment->author->id);
    }

    /** @return array{string, string, string, int} */
    private function seedTransition(): array
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

        $case = ClinicalCase::query()->withoutGlobalScopes()->create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => 1,
            'status' => 'submitted',
        ]);

        $transition = CaseStatusTransition::query()->withoutGlobalScopes()->create([
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
