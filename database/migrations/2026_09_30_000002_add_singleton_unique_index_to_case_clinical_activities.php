<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX case_clinical_activities_singleton_unique ON case_clinical_activities (clinical_case_id, activity_type) WHERE activity_type IN ('adr', 'counselling')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS case_clinical_activities_singleton_unique');
    }
};
