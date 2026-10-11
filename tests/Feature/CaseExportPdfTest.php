<?php

namespace Tests\Feature;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\SubmitCase;
use App\Enums\CaseStatus;
use App\Models\AuditEvent;
use App\Models\CaseVersion;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\ClinicalSite;
use App\Models\Rotation;
use App\Models\RotationAssignment;
use App\Models\User;
use App\Models\Ward;
use App\Services\CaseExportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class CaseExportPdfTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    private const ULID = '/\b[0-9A-HJKMNP-TV-Z]{26}\b/';

    public function test_the_owner_assigned_faculty_and_a_same_institution_admin_can_export_an_approved_case(): void
    {
        [$case, $student, $faculty, $rotation] = $this->approvedCase();
        $admin = $this->makeAdministrator($rotation);

        foreach ([[$student, "/student/cases/{$case->id}/export"], [$faculty, "/faculty/reviews/{$case->id}/export"], [$admin, "/admin/cases/{$case->id}/export"]] as [$user, $url]) {
            $response = $this->actingAs($user)->get($url);

            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertSame('attachment; filename="pharmalab-case-'.$case->case_number.'-v1.pdf"', $response->headers->get('Content-Disposition'));
            $this->assertDoesNotMatchRegularExpression(self::ULID, (string) $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
            $this->assertStringContainsString('/Type /Page', (string) $response->getContent());
        }
    }

    public function test_each_export_records_exactly_one_audit_event_with_all_three_identifiers(): void
    {
        [$case, $student] = $this->approvedCase();
        $version = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole();

        $this->actingAs($student)->get("/student/cases/{$case->id}/export")->assertOk();

        $event = AuditEvent::query()->withoutGlobalScopes()->where('event_type', 'clinical_case.exported')->sole();
        $this->assertSame($student->id, $event->actor_id);
        $this->assertSame(ClinicalCase::class, $event->auditable_type);
        $this->assertSame($case->id, $event->auditable_id);
        $this->assertSame(['case_id' => $case->id, 'version_number' => 1, 'case_version_id' => $version->id], $event->metadata);
    }

    public function test_everyone_else_is_denied(): void
    {
        [$case, , , $rotation] = $this->approvedCase();
        $institution = $this->institutionOf($rotation);
        $otherStudent = $this->makeStudent($rotation, 'Classmate');
        $unassignedFaculty = $this->makeFaculty($rotation, 'Dr Unassigned');
        $foreign = $this->makeRotation(null, 'Foreign');

        $this->actingAs($otherStudent)->get("/student/cases/{$case->id}/export")->assertForbidden();
        $this->actingAs($unassignedFaculty)->get("/faculty/reviews/{$case->id}/export")->assertForbidden();
        // Other institutions cannot even resolve the case (tenant global scope).
        $this->actingAs($this->makeFaculty($foreign))->get("/faculty/reviews/{$case->id}/export")->assertNotFound();
        $this->actingAs($this->makeAdministrator($foreign))->get("/admin/cases/{$case->id}/export")->assertNotFound();
        // Role routes stay role-only.
        $this->actingAs($this->makeAdministrator($institution))->get("/student/cases/{$case->id}/export")->assertForbidden();

        $this->assertSame(0, AuditEvent::query()->withoutGlobalScopes()->where('event_type', 'clinical_case.exported')->count());
    }

    public function test_a_case_that_is_not_approved_cannot_be_exported_by_anyone(): void
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $admin = $this->makeAdministrator($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);

        foreach ([CaseStatus::Draft, CaseStatus::Submitted, CaseStatus::UnderReview, CaseStatus::Returned] as $status) {
            $case = $this->makeCase($assignment, $status);
            $this->actingAs($student)->get("/student/cases/{$case->id}/export")->assertForbidden();
            $this->actingAs($faculty)->get("/faculty/reviews/{$case->id}/export")->assertForbidden();
            $this->actingAs($admin)->get("/admin/cases/{$case->id}/export")->assertForbidden();
        }
    }

    public function test_a_reopened_case_cannot_be_exported_until_it_is_approved_again(): void
    {
        [$case, $student, $faculty] = $this->approvedCase();
        $this->actingAs($faculty);
        app(ReopenCase::class)($faculty, $case, 'Dose needs correcting.');

        $this->actingAs($student)->get("/student/cases/{$case->id}/export")->assertForbidden();
    }

    public function test_the_pdf_html_carries_the_header_and_the_snapshot_clinical_content(): void
    {
        [$case, $student, $faculty] = $this->approvedCase();
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        foreach ([
            'Educational Case #'.$case->case_number,
            'Version 1',
            'pharmd-case-v1',
            'Asha Student',
            'Approved by</td><td>Dr Ravi Preceptor',
            'General Medicine',
            'City Hospital',
            'Ward 3',
            'Congestive heart failure',
            'Progressive exertional dyspnoea with ankle swelling.',
            'Serum creatinine',
            'Furosemide',
            'Fluid overload',
            'Decompensated heart failure.',
            'Monitor potassium and creatinine every 48 hours.',
            'Cardiology',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html, "missing: {$expected}");
        }
        $this->assertStringNotContainsString('submitted before rotation, site and ward names were recorded', $html);
    }

    public function test_the_pdf_never_contains_ulids_or_other_technical_identifiers(): void
    {
        [$case, $student] = $this->approvedCase();
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertDoesNotMatchRegularExpression(self::ULID, $html);
        $this->assertStringNotContainsString((string) $case->rotation_assignment_id, $html);
        $this->assertStringNotContainsString('institution_id', $html);
    }

    public function test_a_change_to_live_case_data_after_approval_never_reaches_the_pdf(): void
    {
        [$case, $student] = $this->approvedCase();
        CaseVital::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->update(['value_numeric' => 188]);
        $case->forceFill(['case_category' => 'Changed after approval'])->save();
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertStringContainsString('96', $html);
        $this->assertStringNotContainsString('188', $html);
        $this->assertStringNotContainsString('Changed after approval', $html);
    }

    public function test_renamed_rotation_site_and_ward_still_show_their_names_at_submission(): void
    {
        [$case, $student, , $rotation] = $this->approvedCase();
        $rotation->forceFill(['name' => 'Renamed Rotation'])->save();
        ClinicalSite::query()->withoutGlobalScopes()->whereKey($rotation->clinical_site_id)->update(['name' => 'Renamed Hospital']);
        Ward::query()->withoutGlobalScopes()->whereKey($rotation->ward_id)->update(['name' => 'Renamed Ward']);
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertStringContainsString('General Medicine', $html);
        $this->assertStringContainsString('City Hospital', $html);
        $this->assertStringNotContainsString('Renamed', $html);
    }

    public function test_a_legacy_version_without_record_metadata_falls_back_to_current_names_and_says_so(): void
    {
        [$case, $student] = $this->approvedCase();
        $version = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole();
        $snapshot = $version->snapshot;
        unset($snapshot['record_metadata']);
        $version->forceFill(['snapshot' => $snapshot])->save();
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertStringContainsString('General Medicine', $html);
        $this->assertStringContainsString('submitted before rotation, site and ward names were recorded', $html);
    }

    public function test_the_approver_name_is_looked_up_live_from_the_immutable_approver_id(): void
    {
        [$case, $student, $faculty] = $this->approvedCase();
        $faculty->forceFill(['name' => 'Dr Ravi Renamed'])->save();
        $this->actingAs($student);

        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertStringContainsString('Approved by</td><td>Dr Ravi Renamed', $html);
        $this->assertSame($faculty->id, CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->value('approved_by'));
    }

    public function test_submission_freezes_record_metadata_in_the_snapshot(): void
    {
        [$case] = $this->approvedCase();

        $snapshot = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole()->snapshot;

        $this->assertSame([
            'case_number' => $case->case_number,
            'rotation_name' => 'General Medicine',
            'clinical_site_name' => 'City Hospital',
            'ward_name' => 'Ward 3',
        ], $snapshot['record_metadata']);
    }

    public function test_a_case_without_its_own_site_or_ward_freezes_the_rotation_defaults(): void
    {
        [$case] = $this->approvedCaseWith(['clinical_site_id' => null, 'ward_id' => null]);

        $snapshot = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole()->snapshot;

        $this->assertSame('City Hospital', $snapshot['record_metadata']['clinical_site_name']);
        $this->assertSame('Ward 3', $snapshot['record_metadata']['ward_name']);
    }

    public function test_a_case_level_site_and_ward_override_the_rotation_defaults(): void
    {
        $rotation = $this->makeRotation();
        $site = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $rotation->institution_id, 'name' => 'Override Hospital', 'code' => 'OVR', 'status' => 'active']);
        $ward = Ward::query()->withoutGlobalScopes()->create(['institution_id' => $rotation->institution_id, 'clinical_site_id' => $site->id, 'name' => 'Override Ward', 'code' => 'OVW', 'status' => 'active']);

        [$case] = $this->approvedCaseWith(['clinical_site_id' => $site->id, 'ward_id' => $ward->id], $rotation);

        $snapshot = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole()->snapshot;

        $this->assertSame('Override Hospital', $snapshot['record_metadata']['clinical_site_name']);
        $this->assertSame('Override Ward', $snapshot['record_metadata']['ward_name']);
    }

    public function test_rotation_defaults_frozen_at_submission_survive_later_rotation_changes_in_the_snapshot_and_pdf(): void
    {
        [$case, $student, , $rotation] = $this->approvedCaseWith(['clinical_site_id' => null, 'ward_id' => null]);
        $newSite = ClinicalSite::query()->withoutGlobalScopes()->create(['institution_id' => $rotation->institution_id, 'name' => 'Moved Hospital', 'code' => 'MOV', 'status' => 'active']);
        $newWard = Ward::query()->withoutGlobalScopes()->create(['institution_id' => $rotation->institution_id, 'clinical_site_id' => $newSite->id, 'name' => 'Moved Ward', 'code' => 'MVW', 'status' => 'active']);
        $rotation->forceFill(['clinical_site_id' => $newSite->id, 'ward_id' => $newWard->id])->save();
        $this->actingAs($student);

        $snapshot = CaseVersion::query()->withoutGlobalScopes()->where('clinical_case_id', $case->id)->sole()->snapshot;
        $html = app(CaseExportPdfService::class)->html($case->fresh());

        $this->assertSame('City Hospital', $snapshot['record_metadata']['clinical_site_name']);
        $this->assertSame('Ward 3', $snapshot['record_metadata']['ward_name']);
        $this->assertStringContainsString('City Hospital', $html);
        $this->assertStringContainsString('Ward 3', $html);
        $this->assertStringNotContainsString('Moved', $html);
    }

    /**
     * @param  array<string, mixed>  $caseAttributes
     * @return array{0: ClinicalCase, 1: User, 2: User, 3: Rotation}
     */
    private function approvedCaseWith(array $caseAttributes, ?Rotation $rotation = null): array
    {
        $rotation ??= $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        $case = $this->makeCompleteDraftCase($assignment);
        $case->forceFill($caseAttributes)->save();

        $this->actingAs($student);
        app(SubmitCase::class)($student, $case->fresh(), true);
        $this->actingAs($faculty);
        app(ApproveCase::class)($faculty, $case->fresh());

        return [$case->fresh(), $student, $faculty, $rotation];
    }

    /** @return array{0: ClinicalCase, 1: User, 2: User, 3: Rotation, 4: RotationAssignment} */
    private function approvedCase(): array
    {
        $rotation = $this->makeRotation();
        $student = $this->makeStudent($rotation);
        $faculty = $this->makeFaculty($rotation);
        $assignment = $this->assign($rotation, $student, $faculty);
        $case = $this->makeApprovedCase($assignment);
        $this->assertSame(CaseStatus::Approved, $case->status);

        return [$case, $student, $faculty, $rotation, $assignment];
    }
}
