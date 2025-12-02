<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_log', function (Blueprint $table) {
            $table->id();

            // Request tracing (for distributed systems)
            $table->uuid('request_id')->index();
            $table->uuid('trace_id')->nullable()->index();
            $table->uuid('parent_request_id')->nullable()->index();

            // Basic info
            $table->string('event_type', 20)->index(); // request, response, error, stream_chunk
            $table->string('provider', 50)->index();
            $table->string('method', 100)->index();
            $table->string('model')->nullable()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();

            // Environment tracking
            $table->string('environment', 20)->default('production')->index(); // production, staging, development, testing

            // Request/Response data
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->json('context')->nullable();

            // Performance metrics
            $table->float('duration')->nullable(); // milliseconds
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();

            // Audit fields
            $table->string('ip_address', 45)->nullable(); // supports IPv6
            $table->text('user_agent')->nullable();
            $table->string('api_key_id')->nullable()->index(); // reference to llm_api_key

            $table->timestamp('created_at', 6)->useCurrent()->index();

            // Composite indexes for common queries
            $table->index(['provider', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['environment', 'created_at']);
            $table->index(['trace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_log');
    }
};
