<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticketing_tickets')) {
            return;
        }

        Schema::table('ticketing_tickets', static function (Blueprint $table): void {
            if (! Schema::hasColumn('ticketing_tickets', 'sla_status')) {
                // Denormalized on_track|at_risk|breached (null when SLA off / not active),
                // maintained on create/update and by the SLA runner so dashboards aggregate in SQL.
                $table->string('sla_status', 16)->nullable()->after('sla_escalated_at');
            }
        });

        if (Schema::hasColumn('ticketing_tickets', 'sla_status')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                $table->index(['status', 'sla_status'], 'ticketing_tickets_status_sla_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticketing_tickets')) {
            return;
        }

        if (Schema::hasColumn('ticketing_tickets', 'sla_status')) {
            Schema::table('ticketing_tickets', static function (Blueprint $table): void {
                $table->dropIndex('ticketing_tickets_status_sla_status_idx');
                $table->dropColumn('sla_status');
            });
        }
    }
};
