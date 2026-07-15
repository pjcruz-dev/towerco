<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->uuid('source_rollout_file_id')->nullable()->after('approval_status');

            $table->unique('source_rollout_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['source_rollout_file_id']);
            $table->dropColumn('source_rollout_file_id');
        });
    }
};
