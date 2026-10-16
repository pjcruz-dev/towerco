<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('controlled_document_report_definitions')) {
            return;
        }

        Schema::create('controlled_document_report_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->json('filters_json');
            $table->json('schedule_json')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_export_path', 500)->nullable();
            $table->string('last_export_filename', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'cdr_report_user_name_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_document_report_definitions');
    }
};
