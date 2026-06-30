<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_site_workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->uuid('rollout_program_id')->nullable();
            $table->timestamps();

            $table->unique('site_id');
            $table->index('rollout_program_id');
        });

        Schema::create('document_site_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('document_site_workspaces')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('document_site_nodes')->cascadeOnDelete();
            $table->string('node_key', 80);
            $table->string('label');
            $table->string('node_type', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('lessor_name', 255)->nullable();
            $table->string('lessor_contact', 255)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUuid('site_node_id')->constrained('document_site_nodes')->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('stored_path', 512);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('uploaded_by_id')->constrained('users');
            $table->foreignUuid('last_touched_by_id')->nullable()->constrained('users');
            $table->timestamp('last_touched_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['site_id', 'site_node_id']);
            $table->index(['site_id', 'expires_at']);
            $table->index('status');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_filename');
            $table->string('stored_path', 512);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignUuid('uploaded_by_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version']);
        });

        Schema::create('document_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('event', 64);
            $table->foreignUuid('actor_id')->nullable()->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_site_nodes');
        Schema::dropIfExists('document_site_workspaces');
    }
};
