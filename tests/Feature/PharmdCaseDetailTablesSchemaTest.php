<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmdCaseDetailTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_detail_tables_exist_with_their_key_columns(): void
    {
        $this->assertTrue(Schema::hasTable('case_clinical_profiles'));
        $this->assertTrue(Schema::hasColumns('case_clinical_profiles', [
            'id', 'institution_id', 'clinical_case_id', 'chief_complaints', 'history_present_illness',
            'diagnoses', 'past_medical_history', 'past_medical_history_none', 'allergy_status',
            'allergy_substance', 'allergy_reaction', 'last_saved_by', 'lock_version',
        ]));

        $this->assertTrue(Schema::hasTable('case_vitals'));
        $this->assertTrue(Schema::hasColumns('case_vitals', [
            'id', 'institution_id', 'clinical_case_id', 'observation_type', 'value_numeric', 'value_text', 'unit', 'observed_on', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_investigations'));
        $this->assertTrue(Schema::hasColumns('case_investigations', [
            'id', 'institution_id', 'clinical_case_id', 'test_name', 'result_type', 'result_value', 'unit', 'reference_range', 'reported_flag', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_medications'));
        $this->assertTrue(Schema::hasColumns('case_medications', [
            'id', 'institution_id', 'clinical_case_id', 'medication_context', 'generic_name', 'status', 'recorded_by',
        ]));

        $this->assertTrue(Schema::hasTable('case_clinical_activities'));
        $this->assertTrue(Schema::hasColumns('case_clinical_activities', [
            'id', 'institution_id', 'clinical_case_id', 'activity_type', 'status', 'details', 'recorded_by',
        ]));
    }
}
