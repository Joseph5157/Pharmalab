<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_vitals', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->unsignedSmallInteger('value_systolic')->nullable()->after('value_numeric');
            $table->unsignedSmallInteger('value_diastolic')->nullable()->after('value_systolic');
        });

        Schema::table('case_investigations', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->boolean('unit_not_stated')->default(false)->after('unit');
            $table->boolean('reference_range_not_provided')->default(false)->after('reference_range');
        });

        Schema::table('case_medications', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(0)->after('recorded_by');
            $table->boolean('indication_unclear')->default(false)->after('indication');
        });
    }

    public function down(): void
    {
        Schema::table('case_vitals', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'value_systolic', 'value_diastolic']);
        });

        Schema::table('case_investigations', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'unit_not_stated', 'reference_range_not_provided']);
        });

        Schema::table('case_medications', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'indication_unclear']);
        });
    }
};
