<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_conversation', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();

            // Environment
            $table->string('environment', 20)->default('production')->index();

            // Basic info
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();

            // Summary management
            $table->text('summary')->nullable();
            $table->integer('summary_token_count')->default(0);
            $table->timestamp('summarized_at')->nullable();
            $table->integer('messages_summarized')->default(0);

            // Token tracking
            $table->integer('total_messages')->default(0);
            $table->integer('total_tokens_used')->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);

            // Configuration
            $table->boolean('auto_summarize')->default(true);
            $table->integer('summarize_after_messages')->default(20);
            $table->integer('keep_recent_messages')->default(5);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('llm_message', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('llm_conversation')->onDelete('cascade');

            // Multi-tenancy (denormalized for faster queries)
            $table->string('organization_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            // Message info
            $table->string('role', 20)->index(); // system, user, assistant, tool
            $table->longText('content');
            $table->json('metadata')->nullable();

            // Token tracking per message
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();

            // Model info
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->string('finish_reason')->nullable();

            // Tool calls
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();

            // Summary tracking
            $table->boolean('included_in_summary')->default(false);
            $table->timestamp('summarized_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_message');
        Schema::dropIfExists('llm_conversation');
    }
};
