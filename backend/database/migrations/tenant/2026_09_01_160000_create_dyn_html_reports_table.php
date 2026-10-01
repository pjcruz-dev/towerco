<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_html_reports')) {
            return;
        }

        Schema::create('dyn_html_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->longText('html_source')->nullable();
            $table->longText('css_source')->nullable();
            $table->longText('js_source')->nullable();
            $table->boolean('is_system')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_html_reports');
    }
};
