<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_clinical_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->json('chief_complaints')->nullable();
            $table->text('history_present_illness')->nullable();
            $table->json('diagnoses')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->boolean('past_medical_history_none')->default(false);
            $table->text('past_surgical_history')->nullable();
            $table->string('adherence_status', 30)->nullable();
            $table->text('family_history')->nullable();
            $table->text('substance_history')->nullable();
            $table->text('examination_findings')->nullable();
            $table->string('allergy_status', 20)->nullable();
            $table->text('allergy_substance')->nullable();
            $table->text('allergy_reaction')->nullable();
            $table->foreignId('last_saved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique('clinical_case_id');
            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_vitals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('observation_type', 40);
            $table->decimal('value_numeric', 7, 2)->nullable();
            $table->string('value_text', 60)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->string('source', 60)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_investigations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('test_name', 120);
            $table->string('result_type', 20);
            $table->string('result_value', 255);
            $table->string('unit', 20)->nullable();
            $table->string('reference_range', 120)->nullable();
            $table->string('reported_flag', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->text('interpretation')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_medications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('medication_context', 20)->default('chart');
            $table->string('generic_name', 120);
            $table->string('brand_name', 120)->nullable();
            $table->string('indication', 255)->nullable();
            $table->string('dose_amount', 30)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('dosage_form', 30)->nullable();
            $table->string('route', 30)->nullable();
            $table->string('frequency', 60)->nullable();
            $table->string('start_reference', 30)->nullable();
            $table->string('stop_reference', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('prn_indication', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_clinical_activities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('activity_type', 20);
            $table->string('status', 30)->nullable();
            $table->json('details')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_clinical_activities');
        Schema::dropIfExists('case_medications');
        Schema::dropIfExists('case_investigations');
        Schema::dropIfExists('case_vitals');
        Schema::dropIfExists('case_clinical_profiles');
    }
};
