<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_rollout_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
            $table->string('context', 64);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignUuid('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rollout_program_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_rollout_files');
    }
};
