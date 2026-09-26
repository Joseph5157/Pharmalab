<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bare "Add row" tap (Slice 2B Task 2) must succeed with no clinical
     * data at all, but `observation_type` was created NOT NULL in
     * 2026_09_26_000001. `Schema::table()->change()` is off-limits per this
     * plan's Global Constraints, and SQLite has no ALTER COLUMN DROP NOT
     * NULL, so this follows the same rebuild-the-table approach already used
     * by 2026_09_28_000001_make_sync_operations_polymorphic.php.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: true);

            return;
        }

        DB::statement('ALTER TABLE case_vitals ALTER COLUMN observation_type DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: false);

            return;
        }

        DB::statement('ALTER TABLE case_vitals ALTER COLUMN observation_type SET NOT NULL');
    }

    /**
     * SQLite does not rename a table's indexes when the table itself is
     * renamed (Schema::rename() below), so an index created here under an
     * auto-generated name tied to the temporary `case_vitals_rebuild` table
     * name survives, unrenamed, on the real `case_vitals` table afterward.
     * A second rebuild cycle (e.g. rollback then re-migrate) would then
     * collide creating the same auto-generated name again. Naming the index
     * explicitly, with a suffix that differs between the up() and down()
     * shapes, avoids that collision.
     */
    private function rebuild(bool $nullable): void
    {
        $suffix = $nullable ? 'nullable' : 'notnull';

        Schema::create('case_vitals_rebuild', function (Blueprint $table) use ($nullable, $suffix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('observation_type', 40)->nullable($nullable);
            $table->decimal('value_numeric', 7, 2)->nullable();
            $table->string('value_text', 60)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->string('source', 60)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->unsignedSmallInteger('value_systolic')->nullable();
            $table->unsignedSmallInteger('value_diastolic')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id'], "case_vitals_{$suffix}_institution_case_index");
        });

        DB::statement('INSERT INTO case_vitals_rebuild (id, institution_id, clinical_case_id, observation_type, value_numeric, value_text, unit, observed_on, observed_at_time, source, note, recorded_by, lock_version, value_systolic, value_diastolic, created_at, updated_at) SELECT id, institution_id, clinical_case_id, observation_type, value_numeric, value_text, unit, observed_on, observed_at_time, source, note, recorded_by, lock_version, value_systolic, value_diastolic, created_at, updated_at FROM case_vitals');
        Schema::drop('case_vitals');
        Schema::rename('case_vitals_rebuild', 'case_vitals');
    }
};
