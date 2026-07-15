<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_vendors')) {
            Schema::table('procurement_vendors', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_vendors', 'portal_inbox_token_hash')) {
                    $table->string('portal_inbox_token_hash', 64)->nullable()->after('is_active');
                }
                if (! Schema::hasColumn('procurement_vendors', 'portal_inbox_token_encrypted')) {
                    $table->text('portal_inbox_token_encrypted')->nullable()->after('portal_inbox_token_hash');
                }
                if (! Schema::hasColumn('procurement_vendors', 'portal_inbox_opened_at')) {
                    $table->timestamp('portal_inbox_opened_at')->nullable()->after('portal_inbox_token_encrypted');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('procurement_vendors')) {
            return;
        }

        Schema::table('procurement_vendors', function (Blueprint $table): void {
            foreach (['portal_inbox_token_hash', 'portal_inbox_token_encrypted', 'portal_inbox_opened_at'] as $column) {
                if (Schema::hasColumn('procurement_vendors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
