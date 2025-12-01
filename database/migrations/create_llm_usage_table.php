<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_usage', function (Blueprint $table) {
            $table->id();
            
            // Provider info
            $table->string('provider', 50)->index();
            $table->string('model', 100)->index();
            
            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            
            // Linkage to other entities
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->uuid('request_id')->nullable()->index();
            
            // Environment
            $table->string('environment', 20)->default('production')->index();
            
            // Token tracking
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('cached_tokens')->default(0);
            $table->integer('total_tokens')->default(0); // convenience field
            
            // Cost tracking
            $table->decimal('cost', 10, 6)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->date('date')->index(); // for fast date-based aggregation
            $table->timestamp('created_at')->useCurrent()->index();

            // Composite indexes for analytics
            $table->index(['provider', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'created_at']);
            $table->index(['organization_id', 'date']);
            $table->index(['organization_id', 'user_id', 'date']);
            $table->index(['conversation_id', 'created_at']);
            
            // Foreign keys
            $table->foreign('conversation_id')->references('id')->on('llm_conversation')->onDelete('set null');
            $table->foreign('message_id')->references('id')->on('llm_message')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage');
    }
};

