<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_vendors')) {
            Schema::create('procurement_vendors', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('master_data_row_id')->nullable()->unique();
                $table->string('vendor_code', 64)->unique();
                $table->string('company_name');
                $table->string('tax_id', 64);
                $table->string('category', 120)->nullable();
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->json('contact_json')->nullable();
                $table->json('banking_json')->nullable();
                $table->json('address_json')->nullable();
                $table->json('profile_json')->nullable();
                $table->string('accreditation_status', 32)->default('pending');
                $table->timestamp('accredited_at')->nullable();
                $table->timestamp('accreditation_expires_at')->nullable();
                $table->uuid('source_submission_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['accreditation_status', 'is_active'], 'pv_accred_active_idx');
                $table->index('tax_id', 'pv_tax_id_idx');
            });
        }

        if (! Schema::hasTable('procurement_vendor_accreditation_events')) {
            Schema::create('procurement_vendor_accreditation_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('vendor_id')->constrained('procurement_vendors')->cascadeOnDelete();
                $table->string('status_from', 32)->nullable();
                $table->string('status_to', 32);
                $table->text('reason')->nullable();
                $table->uuid('actor_user_id')->nullable();
                $table->uuid('submission_id')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'created_at'], 'pvae_vendor_created_idx');
            });
        }

        if (! Schema::hasTable('procurement_vendor_documents')) {
            Schema::create('procurement_vendor_documents', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('vendor_id')->constrained('procurement_vendors')->cascadeOnDelete();
                $table->uuid('document_id')->nullable();
                $table->uuid('e_approval_attachment_id')->nullable();
                $table->string('document_kind', 64)->default('accreditation');
                $table->string('label');
                $table->string('file_name')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'document_kind'], 'pvd_vendor_kind_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_vendor_documents');
        Schema::dropIfExists('procurement_vendor_accreditation_events');
        Schema::dropIfExists('procurement_vendors');
    }
};
