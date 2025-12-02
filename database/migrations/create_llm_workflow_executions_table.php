<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_workflow_execution', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            // Environment
            $table->string('environment', 20)->default('production')->index();

            // Workflow info
            $table->string('workflow_name')->nullable();
            $table->string('default_model')->index();
            $table->string('provider', 50)->index();
            $table->json('steps_config')->nullable(); // Workflow step definitions
            $table->json('input_variables')->nullable();

            // Execution state
            $table->string('status', 20)->default('running')->index(); // running, paused, completed, failed, cancelled
            $table->string('current_step')->nullable()->index();
            $table->integer('completed_steps')->default(0);
            $table->integer('total_steps')->default(0);

            // Results
            $table->json('step_results')->nullable(); // Results from each step
            $table->json('context_variables')->nullable(); // Workflow context variables
            $table->text('final_output')->nullable();

            // Usage tracking
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();

            // Pending approvals
            $table->boolean('has_pending_approval')->default(false)->index();
            $table->string('pending_step_name')->nullable();
            $table->json('pending_approval_data')->nullable();

            // Linkage
            $table->string('conversation_id')->nullable()->index();
            $table->string('parent_execution_id')->nullable()->index();

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'has_pending_approval']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['current_step', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_workflow_execution');
    }
};
