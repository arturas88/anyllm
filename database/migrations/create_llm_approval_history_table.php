<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_approval_history', function (Blueprint $table) {
            $table->id();

            // Linkage
            $table->string('approval_request_id')->nullable()->index(); // UUID of approval request
            $table->string('execution_type', 20)->index(); // agent, workflow
            $table->string('execution_id')->index(); // UUID of execution

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            // Approval details
            $table->string('approval_type', 50)->index();
            $table->string('approval_key')->nullable()->index();
            $table->string('action', 20)->index(); // approved, rejected, modified, auto_approved, auto_rejected

            // Decision data
            $table->text('original_data')->nullable(); // Original request data
            $table->text('modified_data')->nullable(); // Modified data (if action = modified)
            $table->text('decision_reason')->nullable();
            $table->json('metadata')->nullable();

            // Actor info
            $table->string('acted_by')->nullable()->index(); // User who made the decision
            $table->string('acted_by_type')->nullable(); // user, system, auto

            // Timestamps
            $table->timestamp('created_at')->nullable()->index();

            // Indexes
            $table->index(['execution_type', 'execution_id', 'created_at']);
            $table->index(['approval_type', 'action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_approval_history');
    }
};

