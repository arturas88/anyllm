<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_task', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            
            // Multi-tenancy
            $table->string('organization_id')->nullable()->index();
            $table->string('team_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            
            // Environment
            $table->string('environment', 20)->default('production')->index();
            
            // Queue management
            $table->string('queue_name', 100)->default('default')->index();
            $table->string('task_type', 50)->index();
            $table->string('status', 20)->default('pending')->index();
            
            // Task data
            $table->json('parameters');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            
            // Priority & retry
            $table->integer('priority')->default(50)->index();
            $table->integer('attempts')->default(0);
            $table->integer('max_retries')->default(3);
            $table->integer('timeout')->default(300); // seconds
            
            // Worker tracking
            $table->string('worker_id')->nullable();
            $table->string('worker_host')->nullable();
            
            // Timestamps
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Composite indexes for queue processing
            $table->index(['status', 'available_at']);
            $table->index(['status', 'priority']);
            $table->index(['queue_name', 'status', 'available_at']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_task');
    }
};

