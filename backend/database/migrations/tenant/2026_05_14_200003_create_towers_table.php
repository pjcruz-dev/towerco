<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('towers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('tower_type', 32);
            $table->decimal('height_m', 8, 2)->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->unsignedInteger('max_tenants')->nullable();
            $table->string('status', 32)->default('operational');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('towers');
    }
};
