<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The real frontend (MedicationChartSection.vue's emptyPayload()) sends
     * `medication_context: null` explicitly on every create, not merely
     * omitting the key — unlike this table's `status`, which defaults to a
     * real value ('active') the client also always sends. An explicit null
     * for a NOT NULL column with only a default (not ->nullable()) fails the
     * INSERT outright rather than falling back to that default, so a bare
     * "Add medicine" tap 500'd. Same rebuild-the-table approach as
     * 2026_09_29_000001/000002/000003 for the same reason (SQLite has no
     * ALTER COLUMN DROP NOT NULL; Schema::table()->change() is off-limits
     * per this plan's Global Constraints).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: true);

            return;
        }

        DB::statement('ALTER TABLE case_medications ALTER COLUMN medication_context DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: false);

            return;
        }

        DB::statement('ALTER TABLE case_medications ALTER COLUMN medication_context SET NOT NULL');
    }

    /**
     * Same SQLite index-rename gap as 2026_09_29_000001/000002/000003 — an
     * explicit, suffix-qualified index name avoids a collision on a second
     * rebuild cycle (e.g. rollback then re-migrate).
     */
    private function rebuild(bool $nullable): void
    {
        $suffix = $nullable ? 'nullable' : 'notnull';

        Schema::create('case_medications_rebuild', function (Blueprint $table) use ($nullable, $suffix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('medication_context', 20)->default('chart')->nullable($nullable);
            $table->string('generic_name', 120)->nullable();
            $table->string('brand_name', 120)->nullable();
            $table->string('indication', 255)->nullable();
            $table->boolean('indication_unclear')->default(false);
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
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id'], "case_medications_ctx_{$suffix}_institution_case_index");
        });

        DB::statement('INSERT INTO case_medications_rebuild (id, institution_id, clinical_case_id, medication_context, generic_name, brand_name, indication, indication_unclear, dose_amount, dose_unit, dosage_form, route, frequency, start_reference, stop_reference, status, prn_indication, notes, recorded_by, lock_version, created_at, updated_at) SELECT id, institution_id, clinical_case_id, medication_context, generic_name, brand_name, indication, indication_unclear, dose_amount, dose_unit, dosage_form, route, frequency, start_reference, stop_reference, status, prn_indication, notes, recorded_by, lock_version, created_at, updated_at FROM case_medications');
        Schema::drop('case_medications');
        Schema::rename('case_medications_rebuild', 'case_medications');
    }
};
