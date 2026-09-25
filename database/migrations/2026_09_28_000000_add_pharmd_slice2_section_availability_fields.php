<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->string('vitals_status', 20)->nullable();
            $table->text('vitals_unavailable_reason')->nullable();
            $table->string('investigations_status', 20)->nullable();
            $table->text('investigations_unavailable_reason')->nullable();
            $table->string('medication_chart_status', 20)->nullable();
            $table->text('medication_chart_none_reason')->nullable();
            $table->unsignedBigInteger('vitals_availability_lock_version')->default(0);
            $table->unsignedBigInteger('investigations_availability_lock_version')->default(0);
            $table->unsignedBigInteger('medication_chart_availability_lock_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('clinical_cases', function (Blueprint $table): void {
            $table->dropColumn(['vitals_status', 'vitals_unavailable_reason', 'investigations_status', 'investigations_unavailable_reason', 'medication_chart_status', 'medication_chart_none_reason', 'vitals_availability_lock_version', 'investigations_availability_lock_version', 'medication_chart_availability_lock_version']);
        });
    }
};
