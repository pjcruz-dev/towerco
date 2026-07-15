<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_ap_invoices')) {
            Schema::create('procurement_ap_invoices', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->foreignUuid('po_id')->constrained('procurement_pos')->cascadeOnDelete();
                $table->uuid('grn_id')->nullable();
                $table->string('vendor_code', 64)->nullable();
                $table->string('vendor_name')->nullable();
                $table->string('vendor_invoice_no', 120)->nullable();
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('payment_terms', 120)->nullable();
                $table->string('currency_code', 8)->default('PHP');
                $table->decimal('exchange_rate', 14, 6)->default(1);
                $table->decimal('vatable_amount', 14, 2)->default(0);
                $table->decimal('vat_exempt_amount', 14, 2)->default(0);
                $table->decimal('zero_rated_amount', 14, 2)->default(0);
                $table->decimal('vat_rate', 8, 4)->default(12);
                $table->decimal('vat_amount', 14, 2)->default(0);
                $table->decimal('total_vat_inclusive', 14, 2)->default(0);
                $table->decimal('less_discount', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->string('match_mode', 16)->default('three_way');
                $table->string('match_status', 32)->default('pending');
                $table->decimal('match_variance_amount', 14, 2)->default(0);
                $table->uuid('e_approval_submission_id')->nullable()->unique();
                $table->uuid('e_approval_form_id')->nullable();
                $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->text('void_reason')->nullable();
                $table->uuid('voided_by_id')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['po_id', 'status'], 'papi_po_status_idx');
                $table->index(['status', 'due_date'], 'papi_status_due_idx');
                $table->index('grn_id', 'papi_grn_idx');
            });
        }

        if (! Schema::hasTable('procurement_ap_invoice_lines')) {
            Schema::create('procurement_ap_invoice_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('ap_invoice_id')->constrained('procurement_ap_invoices')->cascadeOnDelete();
                $table->foreignUuid('po_line_id')->constrained('procurement_po_lines')->cascadeOnDelete();
                $table->uuid('grn_line_id')->nullable();
                $table->unsignedSmallInteger('line_order')->default(0);
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity_invoiced', 12, 4)->default(0);
                $table->decimal('unit_price', 14, 4)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->uuid('cost_center_id')->nullable();
                $table->string('expense_type', 16)->nullable();
                $table->uuid('budget_line_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['ap_invoice_id', 'line_order'], 'pail_inv_order_idx');
                $table->index('po_line_id', 'pail_po_line_idx');
            });
        }

        if (! Schema::hasTable('procurement_credit_notes')) {
            Schema::create('procurement_credit_notes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->uuid('ap_invoice_id')->nullable();
                $table->foreignUuid('po_id')->constrained('procurement_pos')->cascadeOnDelete();
                $table->string('vendor_credit_note_no', 120)->nullable();
                $table->date('credit_date')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->text('reason')->nullable();
                $table->foreignUuid('created_by_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('approved_by_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['po_id', 'status'], 'pcn_po_status_idx');
                $table->index('ap_invoice_id', 'pcn_invoice_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_credit_notes');
        Schema::dropIfExists('procurement_ap_invoice_lines');
        Schema::dropIfExists('procurement_ap_invoices');
    }
};
