<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_case_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('rotation_id')->constrained()->restrictOnDelete();
            // null = the rotation's overall case-count target; a value = a
            // per-category target. Display casing is preserved as entered.
            $table->string('category', 80)->nullable();
            // Database-computed normalization key. It is the only thing ever
            // compared when matching categories, so "Cardiology" and
            // " cardiology " are the same category at the database layer.
            $table->string('category_key', 80)->nullable()->storedAs('lower(trim(category))');
            $table->unsignedInteger('target_count');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'rotation_id']);
        });

        // A plain unique(['rotation_id', 'category']) would allow any number of
        // NULL-category (overall) rows, because NULLs never collide in a
        // unique index. Two partial indexes give exactly one overall target
        // and one row per normalized category, per rotation.
        DB::statement('CREATE UNIQUE INDEX rotation_case_targets_overall_unique ON rotation_case_targets (rotation_id) WHERE category IS NULL');
        DB::statement('CREATE UNIQUE INDEX rotation_case_targets_category_unique ON rotation_case_targets (rotation_id, category_key) WHERE category IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rotation_case_targets_category_unique');
        DB::statement('DROP INDEX IF EXISTS rotation_case_targets_overall_unique');
        Schema::dropIfExists('rotation_case_targets');
    }
};
