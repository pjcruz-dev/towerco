<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_list_shared_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('storage_key', 160)->unique();
            $table->json('layouts_json');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('module_list_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('module', 40);
            $table->string('filename', 255);
            $table->string('format', 10);
            $table->string('status', 20)->default('queued');
            $table->string('disk', 40)->default('local');
            $table->string('file_path', 500)->nullable();
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('exported_rows')->default(0);
            $table->boolean('truncated')->default(false);
            $table->json('filters_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_list_exports');
        Schema::dropIfExists('module_list_shared_layouts');
    }
};
