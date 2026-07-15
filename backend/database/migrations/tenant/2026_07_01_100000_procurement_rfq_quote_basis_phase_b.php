<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_rfq_bid_lines')) {
            Schema::table('procurement_rfq_bid_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'monthly_unit_price')) {
                    $table->decimal('monthly_unit_price', 14, 2)->nullable()->after('unit_price');
                }
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'yearly_unit_price')) {
                    $table->decimal('yearly_unit_price', 14, 2)->nullable()->after('monthly_unit_price');
                }
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'amount_monthly')) {
                    $table->decimal('amount_monthly', 14, 2)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'amount_yearly')) {
                    $table->decimal('amount_yearly', 14, 2)->nullable()->after('amount_monthly');
                }
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'normalized_annual_amount')) {
                    $table->decimal('normalized_annual_amount', 14, 2)->nullable()->after('amount_yearly');
                }
                if (! Schema::hasColumn('procurement_rfq_bid_lines', 'quote_basis')) {
                    $table->string('quote_basis', 32)->nullable()->after('notes');
                }
            });
        }

        if (Schema::hasTable('procurement_rfq_bids')) {
            Schema::table('procurement_rfq_bids', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_rfq_bids', 'total_amount_monthly')) {
                    $table->decimal('total_amount_monthly', 14, 2)->nullable()->after('total_amount');
                }
                if (! Schema::hasColumn('procurement_rfq_bids', 'total_amount_yearly')) {
                    $table->decimal('total_amount_yearly', 14, 2)->nullable()->after('total_amount_monthly');
                }
                if (! Schema::hasColumn('procurement_rfq_bids', 'normalized_annual_amount')) {
                    $table->decimal('normalized_annual_amount', 14, 2)->nullable()->after('total_amount_yearly');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('procurement_rfq_bid_lines')) {
            Schema::table('procurement_rfq_bid_lines', function (Blueprint $table): void {
                foreach ([
                    'monthly_unit_price',
                    'yearly_unit_price',
                    'amount_monthly',
                    'amount_yearly',
                    'normalized_annual_amount',
                    'quote_basis',
                ] as $column) {
                    if (Schema::hasColumn('procurement_rfq_bid_lines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('procurement_rfq_bids')) {
            Schema::table('procurement_rfq_bids', function (Blueprint $table): void {
                foreach (['total_amount_monthly', 'total_amount_yearly', 'normalized_annual_amount'] as $column) {
                    if (Schema::hasColumn('procurement_rfq_bids', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
