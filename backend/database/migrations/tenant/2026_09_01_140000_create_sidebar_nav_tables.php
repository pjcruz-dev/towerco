<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sidebar_nav_items')) {
            Schema::create('sidebar_nav_items', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('parent_id')->nullable()->constrained('sidebar_nav_items')->cascadeOnDelete();
                $table->string('key', 128)->nullable();
                $table->string('type', 32)->default('internal_page');
                $table->string('title');
                $table->string('icon', 64)->nullable();
                $table->string('href', 512)->nullable();
                $table->string('entity_slug', 128)->nullable();
                $table->string('permission_key', 128)->nullable();
                $table->json('required_permissions')->nullable();
                $table->string('permissions_match', 8)->default('all');
                $table->string('module', 64)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_global_default')->default(false);
                $table->timestamps();

                $table->unique('key');
                $table->index(['parent_id', 'sort_order']);
                $table->index(['is_visible', 'sort_order']);
            });
        }

        if (! Schema::hasTable('sidebar_nav_role_defaults')) {
            Schema::create('sidebar_nav_role_defaults', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id');
                $table->uuid('item_id');
                $table->timestamps();

                $table->primary('role_id');
                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('sidebar_nav_items')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_nav_role_defaults');
        Schema::dropIfExists('sidebar_nav_items');
    }
};
