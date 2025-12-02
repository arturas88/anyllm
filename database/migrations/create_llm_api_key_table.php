<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_api_key', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            // Provider association
            $table->string('provider', 50)->index();

            // Key storage (encrypted)
            $table->text('encrypted_key'); // The actual API key, encrypted
            $table->string('key_hash', 64)->unique()->index(); // Hash for quick lookup
            $table->string('key_prefix', 10)->nullable(); // First few chars for display (e.g., "sk-abc...")

            // Key metadata
            $table->string('name')->nullable(); // User-friendly name
            $table->text('description')->nullable();
            $table->json('scopes')->nullable(); // Permissions/scopes

            // Rate limiting (per key)
            $table->integer('rate_limit_per_minute')->nullable();
            $table->integer('rate_limit_per_hour')->nullable();
            $table->integer('rate_limit_per_day')->nullable();
            $table->integer('rate_limit_per_month')->nullable();

            // Cost controls
            $table->decimal('cost_limit_daily', 10, 4)->nullable();
            $table->decimal('cost_limit_monthly', 10, 4)->nullable();
            $table->decimal('current_daily_cost', 10, 4)->default(0);
            $table->decimal('current_monthly_cost', 10, 4)->default(0);

            // Token limits
            $table->integer('token_limit_daily')->nullable();
            $table->integer('token_limit_monthly')->nullable();
            $table->integer('current_daily_tokens')->default(0);
            $table->integer('current_monthly_tokens')->default(0);

            // Status & lifecycle
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_test_key')->default(false)->index();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('rotated_at')->nullable();

            // Audit fields
            $table->string('created_by')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->integer('total_requests')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);

            // Security
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->text('lock_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['organization_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index(['provider', 'is_active']);
            $table->index(['is_active', 'last_used_at']);
            $table->index(['expires_at', 'is_active']);
        });

        // Key rotation history
        Schema::create('llm_api_key_rotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('llm_api_key')->onDelete('cascade');
            $table->string('old_key_hash', 64)->index();
            $table->string('new_key_hash', 64)->index();
            $table->string('rotated_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('rotated_at')->useCurrent();

            $table->index(['api_key_id', 'rotated_at']);
        });

        // Key usage logs (summary per day for analytics)
        Schema::create('llm_api_key_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('llm_api_key')->onDelete('cascade');
            $table->date('date')->index();
            $table->integer('total_requests')->default(0);
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['api_key_id', 'date']);
            $table->index(['date', 'total_requests']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_api_key_usage');
        Schema::dropIfExists('llm_api_key_rotation');
        Schema::dropIfExists('llm_api_key');
    }
};
