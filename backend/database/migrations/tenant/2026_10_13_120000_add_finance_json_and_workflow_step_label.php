<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_submissions') && ! Schema::hasColumn('e_approval_submissions', 'finance_json')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                $table->json('finance_json')->nullable()->after('approval_policy_label');
            });
        }

        if (Schema::hasTable('e_approval_workflow_steps') && ! Schema::hasColumn('e_approval_workflow_steps', 'label')) {
            Schema::table('e_approval_workflow_steps', function (Blueprint $table): void {
                $table->string('label', 120)->nullable()->after('approver_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('e_approval_workflow_steps', function (Blueprint $table): void {
            $table->dropColumn('label');
        });

        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->dropColumn('finance_json');
        });
    }
};
