<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_agent_execution', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            // Environment
            $table->string('environment', 20)->default('production')->index();

            // Agent info
            $table->string('agent_type', 50)->default('agent'); // agent, workflow
            $table->string('model')->index();
            $table->string('provider', 50)->index();
            $table->text('system_prompt')->nullable();
            $table->text('input')->nullable();

            // Execution state
            $table->string('status', 20)->default('running')->index(); // running, paused, completed, failed, cancelled
            $table->integer('current_iteration')->default(0);
            $table->integer('max_iterations')->default(10);

            // Results
            $table->text('final_content')->nullable();
            $table->json('messages')->nullable(); // Full message history
            $table->json('tool_executions')->nullable(); // Tool execution history
            $table->json('context')->nullable(); // Additional context/metadata

            // Usage tracking
            $table->integer('total_tokens')->default(0);
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();

            // Pending approvals
            $table->boolean('has_pending_approval')->default(false)->index();
            $table->string('pending_approval_type')->nullable(); // tool_execution, final_response
            $table->json('pending_approval_data')->nullable();

            // Linkage
            $table->string('conversation_id')->nullable()->index();
            $table->string('parent_execution_id')->nullable()->index(); // For nested executions

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
            $table->index(['agent_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_agent_execution');
    }
};
