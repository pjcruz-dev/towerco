<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_inventory_locations')) {
            Schema::create('procurement_inventory_locations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->string('location_kind', 32)->default('warehouse');
                $table->uuid('site_id')->nullable()->index();
                $table->boolean('is_default_receipt')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('procurement_inventory_stock_balances')) {
            Schema::create('procurement_inventory_stock_balances', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('location_id');
                $table->uuid('po_line_id')->nullable();
                $table->string('stock_key', 128);
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity_on_hand', 14, 4)->default(0);
                $table->timestamps();

                $table->foreign('location_id', 'proc_inv_stbal_loc_fk')
                    ->references('id')
                    ->on('procurement_inventory_locations')
                    ->cascadeOnDelete();
                $table->foreign('po_line_id', 'proc_inv_stbal_pol_fk')
                    ->references('id')
                    ->on('procurement_po_lines')
                    ->nullOnDelete();
                $table->unique(['location_id', 'stock_key'], 'proc_inv_stock_bal_loc_key_unq');
            });
        }

        if (! Schema::hasTable('procurement_inventory_stock_movements')) {
            Schema::create('procurement_inventory_stock_movements', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('movement_type', 32);
                $table->uuid('transfer_batch_id')->nullable()->index('proc_inv_stmov_xfer_batch_idx');
                $table->uuid('location_id');
                $table->uuid('counterparty_location_id')->nullable();
                $table->uuid('grn_id')->nullable()->index('proc_inv_stmov_grn_idx');
                $table->uuid('grn_line_id')->nullable();
                $table->uuid('po_line_id')->nullable()->index('proc_inv_stmov_pol_idx');
                $table->uuid('asset_id')->nullable()->index('proc_inv_stmov_asset_idx');
                $table->string('stock_key', 128);
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity', 14, 4);
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->uuid('created_by_id')->nullable();
                $table->timestamps();

                $table->foreign('location_id', 'proc_inv_stmov_loc_fk')
                    ->references('id')
                    ->on('procurement_inventory_locations')
                    ->cascadeOnDelete();
                $table->foreign('counterparty_location_id', 'proc_inv_stmov_cpty_loc_fk')
                    ->references('id')
                    ->on('procurement_inventory_locations')
                    ->nullOnDelete();
                $table->foreign('grn_id', 'proc_inv_stmov_grn_fk')
                    ->references('id')
                    ->on('procurement_grns')
                    ->nullOnDelete();
                $table->foreign('grn_line_id', 'proc_inv_stmov_grnl_fk')
                    ->references('id')
                    ->on('procurement_grn_lines')
                    ->nullOnDelete();
                $table->foreign('po_line_id', 'proc_inv_stmov_pol_fk')
                    ->references('id')
                    ->on('procurement_po_lines')
                    ->nullOnDelete();
                $table->foreign('asset_id', 'proc_inv_stmov_asset_fk')
                    ->references('id')
                    ->on('assets')
                    ->nullOnDelete();
                $table->foreign('created_by_id', 'proc_inv_stmov_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('procurement_grns') && ! Schema::hasColumn('procurement_grns', 'inventory_location_id')) {
            Schema::table('procurement_grns', function (Blueprint $table): void {
                $table->uuid('inventory_location_id')->nullable()->after('site_id');
                $table->foreign('inventory_location_id', 'proc_grn_inv_loc_fk')
                    ->references('id')
                    ->on('procurement_inventory_locations')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('assets', 'source_grn_line_id')) {
                    $table->uuid('source_grn_line_id')->nullable()->after('purchase_value');
                }
                if (! Schema::hasColumn('assets', 'source_po_line_id')) {
                    $table->uuid('source_po_line_id')->nullable()->after('source_grn_line_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('procurement_grns') && Schema::hasColumn('procurement_grns', 'inventory_location_id')) {
            Schema::table('procurement_grns', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('inventory_location_id');
            });
        }

        Schema::dropIfExists('procurement_inventory_stock_movements');
        Schema::dropIfExists('procurement_inventory_stock_balances');
        Schema::dropIfExists('procurement_inventory_locations');

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table): void {
                foreach (['source_po_line_id', 'source_grn_line_id'] as $column) {
                    if (Schema::hasColumn('assets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
