<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_review_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('clinical_case_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('case_status_transition_id')->constrained('case_status_transitions')->restrictOnDelete();
            $table->string('section', 40);
            $table->text('body');
            $table->boolean('is_flagged');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['case_status_transition_id', 'section']);
            $table->index(['institution_id', 'clinical_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_review_comments');
    }
};
