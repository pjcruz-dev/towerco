<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_rfq_vendors')) {
            return;
        }

        Schema::table('procurement_rfq_vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_token_hash')) {
                $table->string('invitation_token_hash', 64)->nullable()->after('responded_at');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_token_expires_at')) {
                $table->timestamp('invitation_token_expires_at')->nullable()->after('invitation_token_hash');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_email')) {
                $table->string('invitation_email', 255)->nullable()->after('invitation_token_expires_at');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_sent_at')) {
                $table->timestamp('invitation_sent_at')->nullable()->after('invitation_email');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_opened_at')) {
                $table->timestamp('invitation_opened_at')->nullable()->after('invitation_sent_at');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'submitted_via')) {
                $table->string('submitted_via', 16)->nullable()->after('invitation_opened_at');
            }
            if (! Schema::hasColumn('procurement_rfq_vendors', 'portal_contact_name')) {
                $table->string('portal_contact_name', 255)->nullable()->after('submitted_via');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('procurement_rfq_vendors')) {
            return;
        }

        Schema::table('procurement_rfq_vendors', function (Blueprint $table): void {
            $columns = [
                'invitation_token_hash',
                'invitation_token_expires_at',
                'invitation_email',
                'invitation_sent_at',
                'invitation_opened_at',
                'submitted_via',
                'portal_contact_name',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('procurement_rfq_vendors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
