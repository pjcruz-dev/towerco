<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('record_action_histories')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('record_action_histories'))->pluck('name');

        Schema::table('record_action_histories', function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains('record_action_created_idx')) {
                $table->index(['created_at', 'id'], 'record_action_created_idx');
            }
            if (! $indexes->contains('record_action_actor_created_idx')) {
                $table->index(['actor_user_id', 'created_at', 'id'], 'record_action_actor_created_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('record_action_histories')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('record_action_histories'))->pluck('name');

        Schema::table('record_action_histories', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('record_action_created_idx')) {
                $table->dropIndex('record_action_created_idx');
            }
            if ($indexes->contains('record_action_actor_created_idx')) {
                $table->dropIndex('record_action_actor_created_idx');
            }
        });
    }
};
