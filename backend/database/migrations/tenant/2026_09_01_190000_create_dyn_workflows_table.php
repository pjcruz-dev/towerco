<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_workflows')) {
            return;
        }

        Schema::create('dyn_workflows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('entity_slug', 160);
            $table->string('trigger_mode', 32)->default('manual'); // manual|on_create|on_update
            $table->string('status_field', 120)->default('status');
            $table->json('status_matches')->nullable();
            $table->json('role_ids')->nullable();
            $table->json('definition_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['entity_slug', 'trigger_mode', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_workflows');
    }
};
