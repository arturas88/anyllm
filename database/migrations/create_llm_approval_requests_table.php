<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_approval_request', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('requested_by')->nullable()->index(); // Who initiated the execution

            // Environment
            $table->string('environment', 20)->default('production')->index();

            // Execution linkage
            $table->string('execution_type', 20)->index(); // agent, workflow
            $table->string('execution_id')->index(); // UUID of agent/workflow execution
            $table->string('execution_uuid')->nullable()->index(); // For easier joins

            // Approval details
            $table->string('approval_type', 50)->index(); // tool_execution, step_execution, final_response, step_result
            $table->string('approval_key')->nullable()->index(); // tool_name, step_name, etc.
            $table->text('description')->nullable(); // Human-readable description
            $table->json('request_data')->nullable(); // Data requiring approval (tool args, step prompt, etc.)
            $table->json('context')->nullable(); // Additional context for decision making

            // Approval state
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, expired, cancelled
            $table->text('decision_reason')->nullable(); // Why approved/rejected
            $table->json('decision_metadata')->nullable(); // Additional decision data

            // Approver info
            $table->string('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_by')->nullable()->index();
            $table->timestamp('rejected_at')->nullable();

            // Timeouts
            $table->integer('timeout_minutes')->nullable(); // Auto-reject after timeout
            $table->timestamp('expires_at')->nullable()->index();

            // Priority
            $table->integer('priority')->default(50)->index(); // Higher = more urgent

            // Notification tracking
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->json('notification_channels')->nullable(); // email, slack, webhook, etc.

            // Timestamps
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            // Indexes
            $table->index(['execution_type', 'execution_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['approval_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_approval_request');
    }
};

