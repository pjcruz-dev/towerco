<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_payment_batches')) {
            Schema::create('procurement_payment_batches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->date('scheduled_date')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('currency_code', 8)->default('PHP');
                $table->timestamp('exported_at')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->foreignUuid('created_by_id')->constrained('users')->cascadeOnDelete();
                $table->uuid('exported_by_id')->nullable();
                $table->uuid('reconciled_by_id')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_date'], 'ppb_status_sched_idx');
            });
        }

        if (! Schema::hasTable('procurement_payment_requests')) {
            Schema::create('procurement_payment_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->foreignUuid('ap_invoice_id')->constrained('procurement_ap_invoices')->cascadeOnDelete();
                $table->uuid('payment_batch_id')->nullable();
                $table->string('vendor_code', 64)->nullable();
                $table->string('vendor_name')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('currency_code', 8)->default('PHP');
                $table->date('scheduled_date')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->string('payment_reference', 120)->nullable();
                $table->foreignUuid('requestor_id')->constrained('users')->cascadeOnDelete();
                $table->uuid('approved_by_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('paid_by_id')->nullable();
                $table->uuid('reconciled_by_id')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['ap_invoice_id', 'status'], 'ppr_inv_status_idx');
                $table->index(['vendor_code', 'status'], 'ppr_vendor_status_idx');
                $table->index('payment_batch_id', 'ppr_batch_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_payment_requests');
        Schema::dropIfExists('procurement_payment_batches');
    }
};
