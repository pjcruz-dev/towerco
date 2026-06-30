<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_upload_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('upload_token', 64)->unique();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUuid('site_node_id')->constrained('document_site_nodes')->cascadeOnDelete();
            $table->uuid('document_id');
            $table->string('stored_path', 512);
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignUuid('uploaded_by_id')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['upload_token', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_upload_intents');
    }
};
