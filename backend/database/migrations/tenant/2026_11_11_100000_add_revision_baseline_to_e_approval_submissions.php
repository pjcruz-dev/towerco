<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_submissions') && ! Schema::hasColumn('e_approval_submissions', 'revision_baseline_json')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                $table->json('revision_baseline_json')->nullable()->after('finance_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_approval_submissions') && Schema::hasColumn('e_approval_submissions', 'revision_baseline_json')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                $table->dropColumn('revision_baseline_json');
            });
        }
    }
};
