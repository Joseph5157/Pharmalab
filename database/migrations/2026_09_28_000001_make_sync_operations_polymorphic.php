<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(true);
            return;
        }
        DB::statement('ALTER TABLE sync_operations ALTER COLUMN case_draft_note_id DROP NOT NULL');
        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->string('syncable_type', 150)->nullable();
            $table->ulid('syncable_id')->nullable();
            $table->index(['syncable_type', 'syncable_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('sync_operations')->whereNotNull('syncable_type')->exists()) {
            throw new RuntimeException('Cannot roll back: generalized sync_operations rows exist');
        }
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuild(false);
            return;
        }
        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropIndex(['syncable_type', 'syncable_id']);
            $table->dropColumn(['syncable_type', 'syncable_id']);
        });
        DB::statement('ALTER TABLE sync_operations ALTER COLUMN case_draft_note_id SET NOT NULL');
    }

    private function rebuild(bool $polymorphic): void
    {
        Schema::create('sync_operations_rebuild', function (Blueprint $table) use ($polymorphic): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_draft_note_id')->nullable($polymorphic)->constrained('case_draft_notes')->restrictOnDelete();
            if ($polymorphic) { $table->string('syncable_type', 150)->nullable(); $table->ulid('syncable_id')->nullable(); }
            $table->uuid('client_operation_id');
            $table->string('section_key', 80)->default('case_draft_note');
            $table->unsignedBigInteger('base_lock_version');
            $table->string('result_status', 40);
            $table->unsignedBigInteger('server_version');
            $table->timestamps();
            $suffix = $polymorphic ? 'poly' : 'legacy';
            $table->unique(['user_id', 'client_operation_id'], "sync_operations_{$suffix}_user_operation_unique");
            $table->index(['institution_id', 'case_draft_note_id'], "sync_operations_{$suffix}_institution_draft_index");
            if ($polymorphic) $table->index(['syncable_type', 'syncable_id'], 'sync_operations_poly_syncable_index');
        });
        DB::statement('INSERT INTO sync_operations_rebuild (id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at) SELECT id, institution_id, user_id, case_draft_note_id, client_operation_id, section_key, base_lock_version, result_status, server_version, created_at, updated_at FROM sync_operations');
        Schema::drop('sync_operations');
        Schema::rename('sync_operations_rebuild', 'sync_operations');
    }
};
