<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-field counters for Dynamic Entities "Automatic ID" fields.
 * period_key encodes date tokens from the format (e.g. year / year-month) so
 * sequences can reset when the format includes YYYY / MM / DD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_field_sequences')) {
            return;
        }

        Schema::create('dyn_field_sequences', function (Blueprint $table) {
            $table->uuid('entity_id');
            $table->string('field_name', 64);
            $table->string('period_key', 32)->default('');
            $table->unsignedInteger('next_no')->default(1);

            $table->primary(['entity_id', 'field_name', 'period_key'], 'dyn_field_sequences_pk');
            $table->foreign('entity_id')->references('id')->on('dyn_entities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_field_sequences');
    }
};
