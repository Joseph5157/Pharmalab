<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->unsignedSmallInteger('duration_years');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'status']);
        });

        Schema::create('academic_cohorts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('programme_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('admission_year');
            $table->string('academic_year_label', 40);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['programme_id', 'name']);
            $table->index(['institution_id', 'status']);
        });

        Schema::create('clinical_sites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'status']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_site_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['clinical_site_id', 'name']);
            $table->index(['institution_id', 'clinical_site_id', 'status']);
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_site_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['clinical_site_id', 'code']);
            $table->index(['institution_id', 'clinical_site_id', 'status']);
            $table->index('department_id');
        });

        Schema::create('rotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('programme_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('academic_cohort_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_site_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('ward_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['institution_id', 'status', 'starts_on']);
            $table->index('programme_id');
            $table->index('academic_cohort_id');
            $table->index('clinical_site_id');
            $table->index('department_id');
            $table->index('ward_id');
        });

        Schema::create('rotation_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('rotation_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('primary_preceptor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['rotation_id', 'student_id']);
            $table->index(['institution_id', 'status']);
            $table->index('student_id');
            $table->index('primary_preceptor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_assignments');
        Schema::dropIfExists('rotations');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('clinical_sites');
        Schema::dropIfExists('academic_cohorts');
        Schema::dropIfExists('programmes');
    }
};
