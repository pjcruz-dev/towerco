<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_public_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('region', 64)->nullable();
            $table->unsignedSmallInteger('calendar_year');
            $table->timestamps();

            $table->unique(['holiday_date', 'region']);
            $table->index(['calendar_year', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_public_holidays');
    }
};
