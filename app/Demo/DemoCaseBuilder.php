<?php

namespace App\Demo;

use App\Actions\ApproveCase;
use App\Actions\ReopenCase;
use App\Actions\ReturnCase;
use App\Actions\SubmitCase;
use App\Enums\CaseFormVersion;
use App\Enums\CaseStatus;
use App\Enums\ClinicalActivityType;
use App\Models\CaseClinicalActivity;
use App\Models\CaseClinicalProfile;
use App\Models\CaseInvestigation;
use App\Models\CaseMedication;
use App\Models\CaseVital;
use App\Models\ClinicalCase;
use App\Models\RotationAssignment;
use App\Models\SoapNote;
use App\Models\User;

/**
 * Writes one scenario's student-authored content under the student's identity,
 * then drives every status change through the real workflow action under the
 * acting user's identity.
 *
 * @phpstan-import-type DemoScenario from DemoCaseScenarios
 * @phpstan-import-type DemoStep from DemoCaseScenarios
 */
class DemoCaseBuilder
{
    public function __construct(
        private readonly DemoActingAs $actingAs,
        private readonly SubmitCase $submit,
        private readonly ApproveCase $approve,
        private readonly ReturnCase $return,
        private readonly ReopenCase $reopen,
    ) {}

    /** @param DemoScenario $scenario */
    public function build(array $scenario, RotationAssignment $assignment, User $student, User $faculty): ClinicalCase
    {
        $case = $this->actingAs->run($student, fn (): ClinicalCase => $this->writeContent($scenario, $assignment, $student));

        foreach ($scenario['steps'] as $step) {
            $this->runStep($step, $case, $student, $faculty);
        }

        return $case;
    }

    /** @param DemoScenario $scenario */
    private function writeContent(array $scenario, RotationAssignment $assignment, User $student): ClinicalCase
    {
        $content = $scenario['content'];
        $owned = ['institution_id' => $assignment->institution_id];

        $case = ClinicalCase::query()->create([
            ...$owned,
            'student_id' => $student->id,
            'rotation_assignment_id' => $assignment->id,
            'case_number' => $scenario['case_number'],
            'status' => CaseStatus::Draft,
            'form_version' => CaseFormVersion::PharmdV1,
            'case_category' => $scenario['category'],
            ...$content['case'],
        ]);
        $forCase = [...$owned, 'clinical_case_id' => $case->id];

        CaseClinicalProfile::query()->create([...$forCase, ...$content['profile'], 'last_saved_by' => $student->id]);

        foreach ($content['vitals'] as $vital) {
            CaseVital::query()->create([...$forCase, ...$vital, 'recorded_by' => $student->id]);
        }
        foreach ($content['investigations'] as $investigation) {
            CaseInvestigation::query()->create([...$forCase, ...$investigation, 'recorded_by' => $student->id]);
        }
        foreach ($content['medications'] as $medication) {
            CaseMedication::query()->create([...$forCase, 'medication_context' => null, ...$medication, 'recorded_by' => $student->id]);
        }

        foreach ([ClinicalActivityType::Adr->value => $content['adr'], ClinicalActivityType::Counselling->value => $content['counselling']] as $type => $activity) {
            if ($activity !== null) {
                CaseClinicalActivity::query()->create([...$forCase, 'activity_type' => $type, ...$activity, 'recorded_by' => $student->id]);
            }
        }
        foreach ($content['interventions'] as $intervention) {
            CaseClinicalActivity::query()->create([...$forCase, 'activity_type' => ClinicalActivityType::Intervention->value, ...$intervention, 'recorded_by' => $student->id]);
        }

        if ($content['soap'] !== null) {
            SoapNote::query()->create([...$forCase, 'revision_number' => 1, ...$content['soap'], 'author_id' => $student->id, 'last_saved_by' => $student->id]);
        }

        return $case;
    }

    /** @param DemoStep $step */
    private function runStep(array $step, ClinicalCase $case, User $student, User $faculty): void
    {
        match ($step['action']) {
            'submit' => $this->actingAs->run($student, fn () => ($this->submit)($student, $case->fresh(), true)),
            'approve' => $this->actingAs->run($faculty, fn () => ($this->approve)($faculty, $case->fresh(), $step['summary'] ?? null, $step['comments'] ?? [])),
            'return' => $this->actingAs->run($faculty, fn () => ($this->return)($faculty, $case->fresh(), $step['reason'] ?? '', $step['comments'] ?? [])),
            'reopen' => $this->actingAs->run($faculty, fn () => ($this->reopen)($faculty, $case->fresh(), $step['reason'] ?? '')),
            'correct' => $this->actingAs->run($student, fn () => $this->correct($case, $step)),
        };
    }

    /** @param DemoStep $step */
    private function correct(ClinicalCase $case, array $step): void
    {
        foreach ($step['medications'] ?? [] as $genericName => $changes) {
            CaseMedication::query()
                ->where('clinical_case_id', $case->id)
                ->where('generic_name', $genericName)
                ->sole()
                ->update($changes);
        }

        if (isset($step['soap'])) {
            SoapNote::query()
                ->where('clinical_case_id', $case->id)
                ->orderByDesc('revision_number')
                ->firstOrFail()
                ->update($step['soap']);
        }
    }
}
