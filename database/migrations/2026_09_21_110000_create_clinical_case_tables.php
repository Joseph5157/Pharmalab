<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('rotation_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('case_number');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('current_revision_number')->default(0);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->date('encounter_date')->nullable();
            $table->string('case_category', 80)->nullable();
            $table->unsignedSmallInteger('age_value')->nullable();
            $table->string('age_unit', 20)->nullable();
            $table->string('sex', 20)->nullable();
            $table->foreignUlid('clinical_site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'student_id', 'case_number']);
            $table->index(['institution_id', 'student_id', 'status']);
            $table->index(['institution_id', 'status']);
            $table->index('rotation_assignment_id');
        });

        Schema::create('soap_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_saved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['clinical_case_id', 'revision_number']);
            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->unsignedSmallInteger('source_revision_number');
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['clinical_case_id', 'version_number']);
            $table->index(['institution_id', 'clinical_case_id']);
        });

        Schema::create('case_status_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('case_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['institution_id', 'clinical_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_status_transitions');
        Schema::dropIfExists('case_versions');
        Schema::dropIfExists('soap_notes');
        Schema::dropIfExists('clinical_cases');
    }
};
