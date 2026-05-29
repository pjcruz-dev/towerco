<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_custom_phases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('phase_key', 64)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('owner_role', 64)->nullable();
            $table->string('default_anchor', 32)->default('tssr_approved');
            $table->unsignedSmallInteger('default_working_day_start')->default(1);
            $table->unsignedSmallInteger('default_working_day_end')->default(5);
            $table->string('default_gate')->nullable();
            $table->boolean('counts_toward_sla')->default(true);
            $table->json('applicable_templates');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_custom_phases');
    }
};
