<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_draft_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('case_id')->unique();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->text('content')->default('');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['institution_id', 'student_id']);
        });

        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_draft_note_id')->constrained()->restrictOnDelete();
            $table->uuid('client_operation_id');
            $table->string('section_key', 80)->default('case_draft_note');
            $table->unsignedBigInteger('base_lock_version');
            $table->string('result_status', 40);
            $table->unsignedBigInteger('server_version');
            $table->timestamps();

            $table->unique(['user_id', 'client_operation_id']);
            $table->index(['institution_id', 'case_draft_note_id']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 40);
            $table->string('event_type', 80);
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['institution_id', 'auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('case_draft_notes');
    }
};
