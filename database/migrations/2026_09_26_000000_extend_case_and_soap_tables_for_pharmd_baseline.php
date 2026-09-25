<?php

use App\Enums\CaseFormVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->string('form_version', 40)->default(CaseFormVersion::PharmdV1->value)->after('case_number');
            $table->string('care_setting', 30)->nullable()->after('sex');
            $table->unsignedSmallInteger('hospital_day_at_first_review')->nullable()->after('care_setting');
            $table->string('information_source', 60)->nullable()->after('hospital_day_at_first_review');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('information_source');
            $table->decimal('height_cm', 5, 2)->nullable()->after('weight_kg');
            $table->string('pregnancy_lactation_status', 30)->nullable()->after('height_cm');
            $table->timestamp('deidentification_attested_at')->nullable()->after('pregnancy_lactation_status');
            $table->foreignId('deidentification_attested_by')->nullable()->after('deidentification_attested_at')->constrained('users')->restrictOnDelete();
        });

        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->string('drug_related_problem_status', 30)->nullable()->after('plan');
            $table->json('drug_related_problem_categories')->nullable()->after('drug_related_problem_status');
        });
    }

    public function down(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->dropColumn(['drug_related_problem_status', 'drug_related_problem_categories']);
        });

        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deidentification_attested_by');
            $table->dropColumn([
                'form_version',
                'care_setting',
                'hospital_day_at_first_review',
                'information_source',
                'weight_kg',
                'height_cm',
                'pregnancy_lactation_status',
                'deidentification_attested_at',
            ]);
        });
    }
};
