<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_contracts')) {
            Schema::create('procurement_contracts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->string('title');
                $table->text('description')->nullable();
                $table->uuid('vendor_id');
                $table->uuid('site_id')->nullable();
                $table->uuid('primary_document_id')->nullable();
                $table->decimal('spend_ceiling', 14, 2)->nullable();
                $table->decimal('committed_po_amount', 14, 2)->default(0);
                $table->string('currency_code', 8)->default('PHP');
                $table->date('effective_from')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('terminated_at')->nullable();
                $table->uuid('terminated_by_id')->nullable();
                $table->text('termination_reason')->nullable();
                $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['status', 'end_date'], 'proc_contr_status_end_idx');
                $table->index('vendor_id', 'proc_contr_vendor_idx');

                $table->foreign('vendor_id', 'proc_contr_vendor_fk')
                    ->references('id')
                    ->on('procurement_vendors')
                    ->cascadeOnDelete();
                $table->foreign('primary_document_id', 'proc_contr_doc_fk')
                    ->references('id')
                    ->on('documents')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('procurement_contract_documents')) {
            Schema::create('procurement_contract_documents', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('contract_id');
                $table->uuid('document_id')->nullable();
                $table->string('document_kind', 64)->default('ancillary');
                $table->string('label');
                $table->string('file_name')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();

                $table->foreign('contract_id', 'proc_contrdoc_contr_fk')
                    ->references('id')
                    ->on('procurement_contracts')
                    ->cascadeOnDelete();
                $table->foreign('document_id', 'proc_contrdoc_doc_fk')
                    ->references('id')
                    ->on('documents')
                    ->nullOnDelete();
                $table->index(['contract_id', 'linked_at'], 'proc_contrdoc_contr_idx');
            });
        }

        if (Schema::hasTable('procurement_pos') && ! Schema::hasColumn('procurement_pos', 'contract_id')) {
            Schema::table('procurement_pos', function (Blueprint $table): void {
                $table->uuid('contract_id')->nullable()->after('requestor_id');
                $table->foreign('contract_id', 'proc_po_contract_fk')
                    ->references('id')
                    ->on('procurement_contracts')
                    ->nullOnDelete();
                $table->index('contract_id', 'proc_po_contract_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('procurement_pos') && Schema::hasColumn('procurement_pos', 'contract_id')) {
            Schema::table('procurement_pos', function (Blueprint $table): void {
                $table->dropForeign('proc_po_contract_fk');
                $table->dropIndex('proc_po_contract_idx');
                $table->dropColumn('contract_id');
            });
        }

        Schema::dropIfExists('procurement_contract_documents');
        Schema::dropIfExists('procurement_contracts');
    }
};
