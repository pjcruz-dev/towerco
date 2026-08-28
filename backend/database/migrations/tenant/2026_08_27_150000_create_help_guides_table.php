<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('help_guides')) {
            return;
        }

        Schema::create('help_guides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module_key', 64);
            $table->string('slug', 128);
            $table->string('role', 32)->default('all');
            $table->string('title');
            $table->longText('body');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('content_checksum', 64)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['module_key', 'slug']);
            $table->index(['module_key', 'status', 'role']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_guides');
    }
};
