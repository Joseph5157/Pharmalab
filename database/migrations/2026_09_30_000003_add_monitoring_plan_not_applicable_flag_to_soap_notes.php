<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->boolean('monitoring_plan_not_applicable')->default(false)->after('monitoring_plan');
        });
    }

    public function down(): void
    {
        Schema::table('soap_notes', function (Blueprint $table): void {
            $table->dropColumn('monitoring_plan_not_applicable');
        });
    }
};
