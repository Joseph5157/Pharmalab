<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bare "Add row" tap (Slice 2B Task 4) must succeed with no clinical
     * data at all, but `generic_name` was created NOT NULL in
     * 2026_09_26_000001. `Schema::table()->change()` is off-limits per this
     * plan's Global Constraints, and SQLite has no ALTER COLUMN DROP NOT
     * NULL, so this follows the same rebuild-the-table approach already
     * used by 2026_09_29_000001 (case_vitals) and 2026_09_29_000002
     * (case_investigations).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: true);

            return;
        }

        DB::statement('ALTER TABLE case_medications ALTER COLUMN generic_name DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: false);

            return;
        }

        DB::statement('ALTER TABLE case_medications ALTER COLUMN generic_name SET NOT NULL');
    }

    /**
     * SQLite does not rename a table's indexes when the table itself is
     * renamed (Schema::rename() below), so an index created here under an
     * auto-generated name tied to the temporary `case_medications_rebuild`
     * table name survives, unrenamed, on the real `case_medications` table
     * afterward. A second rebuild cycle (e.g. rollback then re-migrate)
     * would then collide creating the same auto-generated name again.
     * Naming the index explicitly, with a suffix that differs between the
     * up() and down() shapes, avoids that collision (see
     * 2026_09_29_000002_make_case_investigation_creation_fields_nullable.php
     * for the same fix on case_investigations).
     */
    private function rebuild(bool $nullable): void
    {
        $suffix = $nullable ? 'nullable' : 'notnull';

        Schema::create('case_medications_rebuild', function (Blueprint $table) use ($nullable, $suffix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('medication_context', 20)->default('chart');
            $table->string('generic_name', 120)->nullable($nullable);
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

            $table->index(['institution_id', 'clinical_case_id'], "case_medications_{$suffix}_institution_case_index");
        });

        DB::statement('INSERT INTO case_medications_rebuild (id, institution_id, clinical_case_id, medication_context, generic_name, brand_name, indication, indication_unclear, dose_amount, dose_unit, dosage_form, route, frequency, start_reference, stop_reference, status, prn_indication, notes, recorded_by, lock_version, created_at, updated_at) SELECT id, institution_id, clinical_case_id, medication_context, generic_name, brand_name, indication, indication_unclear, dose_amount, dose_unit, dosage_form, route, frequency, start_reference, stop_reference, status, prn_indication, notes, recorded_by, lock_version, created_at, updated_at FROM case_medications');
        Schema::drop('case_medications');
        Schema::rename('case_medications_rebuild', 'case_medications');
    }
};
