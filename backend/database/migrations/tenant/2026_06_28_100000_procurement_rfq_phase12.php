<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_rfqs')) {
            Schema::create('procurement_rfqs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->string('title');
                $table->text('description')->nullable();
                $table->uuid('pr_id')->nullable();
                $table->uuid('project_id')->nullable();
                $table->uuid('rollout_id')->nullable();
                $table->uuid('site_id')->nullable();
                $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('bidding_opens_at')->nullable();
                $table->timestamp('bidding_closes_at')->nullable();
                $table->uuid('awarded_vendor_id')->nullable();
                $table->uuid('awarded_bid_id')->nullable();
                $table->timestamp('awarded_at')->nullable();
                $table->uuid('awarded_by_id')->nullable();
                $table->string('currency_code', 8)->default('PHP');
                $table->decimal('estimated_total', 14, 2)->default(0);
                $table->text('award_notes')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['status', 'bidding_closes_at'], 'proc_rfq_status_close_idx');
                $table->index('pr_id', 'proc_rfq_pr_idx');

                $table->foreign('pr_id', 'proc_rfq_pr_fk')
                    ->references('id')
                    ->on('procurement_prs')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('procurement_rfq_lines')) {
            Schema::create('procurement_rfq_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rfq_id');
                $table->unsignedInteger('line_order')->default(1);
                $table->uuid('pr_line_id')->nullable();
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('target_unit_price', 14, 2)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->foreign('rfq_id', 'proc_rfq_line_rfq_fk')
                    ->references('id')
                    ->on('procurement_rfqs')
                    ->cascadeOnDelete();
                $table->foreign('pr_line_id', 'proc_rfq_line_prline_fk')
                    ->references('id')
                    ->on('procurement_pr_lines')
                    ->nullOnDelete();
                $table->index(['rfq_id', 'line_order'], 'proc_rfq_line_order_idx');
            });
        }

        if (! Schema::hasTable('procurement_rfq_vendors')) {
            Schema::create('procurement_rfq_vendors', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rfq_id');
                $table->uuid('vendor_id');
                $table->string('invitation_status', 32)->default('invited');
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->foreign('rfq_id', 'proc_rfq_vend_rfq_fk')
                    ->references('id')
                    ->on('procurement_rfqs')
                    ->cascadeOnDelete();
                $table->foreign('vendor_id', 'proc_rfq_vend_vendor_fk')
                    ->references('id')
                    ->on('procurement_vendors')
                    ->cascadeOnDelete();
                $table->unique(['rfq_id', 'vendor_id'], 'proc_rfq_vend_rfq_vendor_unq');
            });
        }

        if (! Schema::hasTable('procurement_rfq_bids')) {
            Schema::create('procurement_rfq_bids', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rfq_id');
                $table->uuid('vendor_id');
                $table->string('status', 32)->default('draft');
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('currency_code', 8)->default('PHP');
                $table->date('validity_until')->nullable();
                $table->unsignedInteger('avg_lead_time_days')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('captured_by_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->foreign('rfq_id', 'proc_rfq_bid_rfq_fk')
                    ->references('id')
                    ->on('procurement_rfqs')
                    ->cascadeOnDelete();
                $table->foreign('vendor_id', 'proc_rfq_bid_vendor_fk')
                    ->references('id')
                    ->on('procurement_vendors')
                    ->cascadeOnDelete();
                $table->foreign('captured_by_id', 'proc_rfq_bid_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->unique(['rfq_id', 'vendor_id'], 'proc_rfq_bid_rfq_vendor_unq');
            });
        }

        if (! Schema::hasTable('procurement_rfq_bid_lines')) {
            Schema::create('procurement_rfq_bid_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('bid_id');
                $table->uuid('rfq_line_id');
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('bid_id', 'proc_rfq_bidline_bid_fk')
                    ->references('id')
                    ->on('procurement_rfq_bids')
                    ->cascadeOnDelete();
                $table->foreign('rfq_line_id', 'proc_rfq_bidline_rfqline_fk')
                    ->references('id')
                    ->on('procurement_rfq_lines')
                    ->cascadeOnDelete();
                $table->unique(['bid_id', 'rfq_line_id'], 'proc_rfq_bidline_bid_line_unq');
            });
        }

        if (! Schema::hasTable('procurement_rfq_po_links')) {
            Schema::create('procurement_rfq_po_links', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rfq_id');
                $table->uuid('po_id');
                $table->uuid('bid_id')->nullable();
                $table->decimal('allocated_amount', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('rfq_id', 'proc_rfq_polink_rfq_fk')
                    ->references('id')
                    ->on('procurement_rfqs')
                    ->cascadeOnDelete();
                $table->foreign('po_id', 'proc_rfq_polink_po_fk')
                    ->references('id')
                    ->on('procurement_pos')
                    ->cascadeOnDelete();
                $table->foreign('bid_id', 'proc_rfq_polink_bid_fk')
                    ->references('id')
                    ->on('procurement_rfq_bids')
                    ->nullOnDelete();
                $table->unique('rfq_id', 'proc_rfq_polink_rfq_unq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_rfq_po_links');
        Schema::dropIfExists('procurement_rfq_bid_lines');
        Schema::dropIfExists('procurement_rfq_bids');
        Schema::dropIfExists('procurement_rfq_vendors');
        Schema::dropIfExists('procurement_rfq_lines');
        Schema::dropIfExists('procurement_rfqs');
    }
};
