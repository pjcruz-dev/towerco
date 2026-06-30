<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', static function (Blueprint $table): void {
                $table->index('name', 'sites_name_idx');
                $table->index('status', 'sites_status_idx');
            });
        }

        if (Schema::hasTable('ticketing_tickets')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                $table->index(['requester_id', 'status'], 'ticketing_tickets_requester_status_idx');
                $table->index('updated_at', 'ticketing_tickets_updated_at_idx');
            });
        }

        if (Schema::hasTable('e_approval_request_approvals')) {
            Schema::table('e_approval_request_approvals', static function (Blueprint $table): void {
                $table->index(['status', 'created_at'], 'ea_req_approvals_status_created_idx');
            });
        }

        if (Schema::hasTable('e_approval_submissions')) {
            Schema::table('e_approval_submissions', static function (Blueprint $table): void {
                $table->index(['status', 'created_at'], 'ea_submissions_status_created_idx');
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', static function (Blueprint $table): void {
                $table->index(['site_node_id', 'sort_order', 'last_touched_at'], 'documents_node_sort_touched_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', static function (Blueprint $table): void {
                $table->dropIndex('sites_name_idx');
                $table->dropIndex('sites_status_idx');
            });
        }

        if (Schema::hasTable('ticketing_tickets')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                $table->dropIndex('ticketing_tickets_requester_status_idx');
                $table->dropIndex('ticketing_tickets_updated_at_idx');
            });
        }

        if (Schema::hasTable('e_approval_request_approvals')) {
            Schema::table('e_approval_request_approvals', static function (Blueprint $table): void {
                $table->dropIndex('ea_req_approvals_status_created_idx');
            });
        }

        if (Schema::hasTable('e_approval_submissions')) {
            Schema::table('e_approval_submissions', static function (Blueprint $table): void {
                $table->dropIndex('ea_submissions_status_created_idx');
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', static function (Blueprint $table): void {
                $table->dropIndex('documents_node_sort_touched_idx');
            });
        }
    }
};
