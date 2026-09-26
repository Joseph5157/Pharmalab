<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Student\StoreCaseClinicalActivityRequest;
use App\Http\Requests\Student\StoreCaseInvestigationRequest;
use App\Http\Requests\Student\StoreCaseMedicationRequest;
use App\Http\Requests\Student\StoreCaseVitalRequest;
use App\Http\Requests\Student\UpdateCaseClinicalProfileRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseDetailRequestRulesTest extends TestCase
{
    public function test_vital_request_requires_a_client_operation_id(): void
    {
        $rules = (new StoreCaseVitalRequest)->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
            'observation_type' => 'pulse',
            'value_numeric' => 80,
            'unit' => 'beats/min',
        ], $rules)->passes());
    }

    public function test_investigation_request_rejects_an_unknown_result_type(): void
    {
        $rules = (new StoreCaseInvestigationRequest)->rules();

        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Haemoglobin',
            'result_type' => 'not_a_real_type',
            'result_value' => '13.5',
        ], $rules)->fails());

        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
            'test_name' => 'Haemoglobin',
            'result_type' => 'numeric',
            'result_value' => '13.5',
        ], $rules)->passes());
    }

    public function test_investigation_request_requires_a_client_operation_id(): void
    {
        $rules = (new StoreCaseInvestigationRequest)->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
        ], $rules)->passes());
    }

    public function test_medication_request_rejects_an_unknown_status(): void
    {
        $rules = (new StoreCaseMedicationRequest)->rules();

        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Paracetamol',
            'status' => 'not_a_real_status',
        ], $rules)->fails());

        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
            'generic_name' => 'Paracetamol',
            'status' => 'active',
        ], $rules)->passes());
    }

    public function test_medication_request_requires_a_client_operation_id(): void
    {
        $rules = (new StoreCaseMedicationRequest)->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make([
            'client_operation_id' => (string) Str::uuid(),
        ], $rules)->passes());
    }

    public function test_clinical_activity_request_requires_a_known_activity_type(): void
    {
        $rules = (new StoreCaseClinicalActivityRequest)->rules();

        $this->assertTrue(Validator::make(['activity_type' => 'not_real'], $rules)->fails());
        $this->assertTrue(Validator::make(['activity_type' => 'adr'], $rules)->passes());
    }

    public function test_clinical_profile_update_request_rejects_an_unknown_allergy_status(): void
    {
        $rules = (new UpdateCaseClinicalProfileRequest)->rules();

        $envelope = ['client_operation_id' => (string) Str::uuid(), 'base_lock_version' => 0];

        $this->assertTrue(Validator::make([...$envelope, 'allergy_status' => 'not_real'], $rules)->fails());
        $this->assertTrue(Validator::make([...$envelope, 'allergy_status' => 'no_known_allergy'], $rules)->passes());
    }
}
