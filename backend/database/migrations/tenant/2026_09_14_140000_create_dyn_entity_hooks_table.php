<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_entity_hooks')) {
            return;
        }

        Schema::create('dyn_entity_hooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('entity_slug', 160);
            $table->json('events');
            $table->json('definition_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['entity_slug', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_entity_hooks');
    }
};
