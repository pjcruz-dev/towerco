<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollout_playbook_versions', function (Blueprint $table) {
            $table->boolean('milestone_derived_from_timeline')->default(false)->after('changelog');
        });
    }

    public function down(): void
    {
        Schema::table('rollout_playbook_versions', function (Blueprint $table) {
            $table->dropColumn('milestone_derived_from_timeline');
        });
    }
};
