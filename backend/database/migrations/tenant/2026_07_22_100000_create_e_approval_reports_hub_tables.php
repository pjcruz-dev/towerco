<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('e_approval_report_definitions')) {
            Schema::create('e_approval_report_definitions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->json('filters_json')->nullable();
                $table->json('columns_json')->nullable();
                $table->string('layout', 32)->default('submissions');
                $table->string('format', 16)->default('csv');
                $table->string('grid_field_id', 36)->nullable();
                $table->json('schedule_json')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'name'], 'eapr_report_user_name_idx');
            });
        }

        if (! Schema::hasTable('e_approval_export_histories')) {
            Schema::create('e_approval_export_histories', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('report_definition_id')
                    ->nullable()
                    ->constrained('e_approval_report_definitions')
                    ->nullOnDelete();
                $table->string('name', 160)->nullable();
                $table->json('filters_json')->nullable();
                $table->json('columns_json')->nullable();
                $table->string('layout', 32)->default('submissions');
                $table->string('format', 16)->default('csv');
                $table->string('grid_field_id', 36)->nullable();
                $table->unsignedInteger('matched_rows')->default(0);
                $table->unsignedInteger('exported_rows')->default(0);
                $table->boolean('truncated')->default(false);
                $table->string('status', 32)->default('completed');
                $table->string('triggered_by', 32)->default('manual');
                $table->string('filename', 255)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'eapr_export_hist_user_created_idx');
                $table->index(['report_definition_id', 'created_at'], 'eapr_export_hist_report_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_export_histories');
        Schema::dropIfExists('e_approval_report_definitions');
    }
};
