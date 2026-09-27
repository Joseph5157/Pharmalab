<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bare "Add row" tap (Slice 2B Task 3) must succeed with no clinical
     * data at all, but `test_name`, `result_type`, and `result_value` were
     * created NOT NULL in 2026_09_26_000001. `Schema::table()->change()` is
     * off-limits per this plan's Global Constraints, and SQLite has no
     * ALTER COLUMN DROP NOT NULL, so this follows the same rebuild-the-table
     * approach already used by 2026_09_29_000001_make_case_vital_observation_type_nullable.php.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: true);

            return;
        }

        DB::statement('ALTER TABLE case_investigations ALTER COLUMN test_name DROP NOT NULL');
        DB::statement('ALTER TABLE case_investigations ALTER COLUMN result_type DROP NOT NULL');
        DB::statement('ALTER TABLE case_investigations ALTER COLUMN result_value DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(nullable: false);

            return;
        }

        DB::statement('ALTER TABLE case_investigations ALTER COLUMN test_name SET NOT NULL');
        DB::statement('ALTER TABLE case_investigations ALTER COLUMN result_type SET NOT NULL');
        DB::statement('ALTER TABLE case_investigations ALTER COLUMN result_value SET NOT NULL');
    }

    /**
     * SQLite does not rename a table's indexes when the table itself is
     * renamed (Schema::rename() below), so an index created here under an
     * auto-generated name tied to the temporary `case_investigations_rebuild`
     * table name survives, unrenamed, on the real `case_investigations` table
     * afterward. A second rebuild cycle (e.g. rollback then re-migrate) would
     * then collide creating the same auto-generated name again. Naming the
     * index explicitly, with a suffix that differs between the up() and
     * down() shapes, avoids that collision (see
     * 2026_09_29_000001_make_case_vital_observation_type_nullable.php for the
     * same fix on case_vitals).
     */
    private function rebuild(bool $nullable): void
    {
        $suffix = $nullable ? 'nullable' : 'notnull';

        Schema::create('case_investigations_rebuild', function (Blueprint $table) use ($nullable, $suffix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->string('test_name', 120)->nullable($nullable);
            $table->string('result_type', 20)->nullable($nullable);
            $table->string('result_value', 255)->nullable($nullable);
            $table->string('unit', 20)->nullable();
            $table->boolean('unit_not_stated')->default(false);
            $table->string('reference_range', 120)->nullable();
            $table->boolean('reference_range_not_provided')->default(false);
            $table->string('reported_flag', 20)->nullable();
            $table->date('observed_on')->nullable();
            $table->time('observed_at_time')->nullable();
            $table->text('interpretation')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['institution_id', 'clinical_case_id'], "case_investigations_{$suffix}_institution_case_index");
        });

        DB::statement('INSERT INTO case_investigations_rebuild (id, institution_id, clinical_case_id, test_name, result_type, result_value, unit, unit_not_stated, reference_range, reference_range_not_provided, reported_flag, observed_on, observed_at_time, interpretation, recorded_by, lock_version, created_at, updated_at) SELECT id, institution_id, clinical_case_id, test_name, result_type, result_value, unit, unit_not_stated, reference_range, reference_range_not_provided, reported_flag, observed_on, observed_at_time, interpretation, recorded_by, lock_version, created_at, updated_at FROM case_investigations');
        Schema::drop('case_investigations');
        Schema::rename('case_investigations_rebuild', 'case_investigations');
    }
};
