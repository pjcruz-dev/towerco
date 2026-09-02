<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_scheduled_tasks')) {
            return;
        }

        Schema::create('dyn_scheduled_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('number')->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('command_key', 120);
            $table->string('schedule', 64);
            $table->string('cron_expression', 64);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index(['command_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_scheduled_tasks');
    }
};
