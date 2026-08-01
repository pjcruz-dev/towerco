<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_geography_lookups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->string('code', 32);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'code']);
            $table->index(['kind', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_geography_lookups');
    }
};
