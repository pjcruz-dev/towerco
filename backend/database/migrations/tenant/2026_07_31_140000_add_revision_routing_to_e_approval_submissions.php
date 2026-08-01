<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('e_approval_submissions', 'returned_from_step')) {
                $table->unsignedInteger('returned_from_step')->nullable()->after('current_step');
            }
            if (! Schema::hasColumn('e_approval_submissions', 'force_full_restart')) {
                $table->boolean('force_full_restart')->default(false)->after('returned_from_step');
            }
            if (! Schema::hasColumn('e_approval_submissions', 'approval_cycle')) {
                $table->unsignedInteger('approval_cycle')->default(1)->after('force_full_restart');
            }
            if (! Schema::hasColumn('e_approval_submissions', 'last_revision_routing')) {
                $table->string('last_revision_routing', 40)->nullable()->after('approval_cycle');
            }
            if (! Schema::hasColumn('e_approval_submissions', 'last_revision_routing_reason')) {
                $table->string('last_revision_routing_reason', 120)->nullable()->after('last_revision_routing');
            }
        });

        Schema::table('e_approval_request_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('e_approval_request_approvals', 'approval_cycle')) {
                $table->unsignedInteger('approval_cycle')->default(1)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            foreach ([
                'returned_from_step',
                'force_full_restart',
                'approval_cycle',
                'last_revision_routing',
                'last_revision_routing_reason',
            ] as $column) {
                if (Schema::hasColumn('e_approval_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('e_approval_request_approvals', function (Blueprint $table): void {
            if (Schema::hasColumn('e_approval_request_approvals', 'approval_cycle')) {
                $table->dropColumn('approval_cycle');
            }
        });
    }
};
