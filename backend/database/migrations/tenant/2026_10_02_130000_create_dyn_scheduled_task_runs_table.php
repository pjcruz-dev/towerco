<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_scheduled_task_runs')) {
            return;
        }

        Schema::create('dyn_scheduled_task_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dyn_scheduled_task_id')
                ->constrained('dyn_scheduled_tasks')
                ->cascadeOnDelete();
            $table->timestamp('ran_at');
            $table->string('status', 32);
            $table->text('error')->nullable();

            $table->index(['dyn_scheduled_task_id', 'ran_at'], 'dyn_task_runs_task_ran_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_scheduled_task_runs');
    }
};
