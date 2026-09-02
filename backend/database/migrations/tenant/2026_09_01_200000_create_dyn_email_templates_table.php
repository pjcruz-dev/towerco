<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_email_templates')) {
            return;
        }

        Schema::create('dyn_email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('entity_slug', 160)->nullable();
            $table->string('subject', 500);
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->string('default_to', 500)->nullable();
            $table->string('cc', 500)->nullable();
            $table->string('bcc', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['name']);
            $table->index(['entity_slug', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_email_templates');
    }
};
