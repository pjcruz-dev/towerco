<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_permits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->string('permit_type', 64);
            $table->date('applied_date')->nullable();
            $table->date('secured_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('rollout_program_id')
                ->references('id')
                ->on('rollout_programs')
                ->cascadeOnDelete();

            $table->unique(['rollout_program_id', 'permit_type']);
            $table->index(['rollout_program_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_permits');
    }
};
