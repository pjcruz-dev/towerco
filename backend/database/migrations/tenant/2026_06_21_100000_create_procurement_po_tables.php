<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_pos')) {
            Schema::create('procurement_pos', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->uuid('e_approval_submission_id')->nullable()->unique();
                $table->uuid('e_approval_form_id')->nullable();
                $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
                $table->string('vendor_code', 64)->nullable();
                $table->string('vendor_name')->nullable();
                $table->text('supplier')->nullable();
                $table->text('ship_to')->nullable();
                $table->date('delivery_date')->nullable();
                $table->string('payment_terms', 120)->nullable();
                $table->string('currency_code', 8)->default('PHP');
                $table->decimal('exchange_rate', 12, 6)->default(1);
                $table->string('delivery_location')->nullable();
                $table->decimal('vatable_amount', 15, 2)->default(0);
                $table->decimal('vat_exempt_amount', 15, 2)->default(0);
                $table->decimal('zero_rated_amount', 15, 2)->default(0);
                $table->decimal('vat_rate', 8, 4)->default(12);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_vat_inclusive', 15, 2)->default(0);
                $table->decimal('less_discount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'ppo_status_created_idx');
                $table->index('requestor_id', 'ppo_requestor_idx');
                $table->index('vendor_code', 'ppo_vendor_idx');
            });
        }

        if (! Schema::hasTable('procurement_po_lines')) {
            Schema::create('procurement_po_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('po_id')->constrained('procurement_pos')->cascadeOnDelete();
                $table->uuid('pr_id')->nullable();
                $table->uuid('pr_line_id')->nullable();
                $table->unsignedSmallInteger('line_order')->default(0);
                $table->string('item', 120)->nullable();
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['po_id', 'line_order'], 'ppol_po_order_idx');
            });
        }

        if (! Schema::hasTable('procurement_po_pr_links')) {
            Schema::create('procurement_po_pr_links', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('po_id')->constrained('procurement_pos')->cascadeOnDelete();
                $table->foreignUuid('pr_id')->constrained('procurement_prs')->cascadeOnDelete();
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['po_id', 'pr_id'], 'ppopl_po_pr_unique');
                $table->index('pr_id', 'ppopl_pr_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_po_pr_links');
        Schema::dropIfExists('procurement_po_lines');
        Schema::dropIfExists('procurement_pos');
    }
};
