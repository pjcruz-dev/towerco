<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_prompt_modules')) {
            return;
        }

        Schema::create('ai_prompt_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 80)->unique();
            $table->string('name', 255);
            $table->string('filename', 120)->nullable();
            $table->text('description')->nullable();
            /** router | always | domain */
            $table->string('kind', 32)->default('domain');
            /** Intent label matched by AiPromptIntentDetector (null for always/router). */
            $table->string('intent_key', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->longText('body');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_system')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['kind', 'sort_order']);
            $table->index(['intent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_modules');
    }
};
