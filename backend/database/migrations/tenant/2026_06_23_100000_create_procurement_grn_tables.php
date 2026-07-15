<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_grns')) {
            Schema::create('procurement_grns', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_no', 64)->nullable();
                $table->string('status', 32)->default('draft');
                $table->foreignUuid('po_id')->constrained('procurement_pos')->cascadeOnDelete();
                $table->foreignUuid('received_by_id')->constrained('users')->cascadeOnDelete();
                $table->uuid('project_id')->nullable();
                $table->uuid('rollout_id')->nullable();
                $table->uuid('site_id')->nullable();
                $table->decimal('gps_latitude', 10, 7)->nullable();
                $table->decimal('gps_longitude', 10, 7)->nullable();
                $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['po_id', 'status'], 'pgrn_po_status_idx');
                $table->index(['status', 'created_at'], 'pgrn_status_created_idx');
            });
        }

        if (! Schema::hasTable('procurement_grn_lines')) {
            Schema::create('procurement_grn_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('grn_id')->constrained('procurement_grns')->cascadeOnDelete();
                $table->foreignUuid('po_line_id')->constrained('procurement_po_lines')->cascadeOnDelete();
                $table->unsignedSmallInteger('line_order')->default(0);
                $table->string('description');
                $table->string('uom', 32)->nullable();
                $table->decimal('quantity_ordered', 12, 4)->default(0);
                $table->decimal('quantity_received', 12, 4)->default(0);
                $table->text('line_notes')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['grn_id', 'line_order'], 'pgrnl_grn_order_idx');
                $table->index('po_line_id', 'pgrnl_po_line_idx');
            });
        }

        if (! Schema::hasTable('procurement_grn_attachments')) {
            Schema::create('procurement_grn_attachments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('grn_id')->constrained('procurement_grns')->cascadeOnDelete();
                $table->string('field_name', 64)->default('delivery_photo');
                $table->string('file_name');
                $table->string('stored_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->foreignUuid('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('grn_id', 'pgrna_grn_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_grn_attachments');
        Schema::dropIfExists('procurement_grn_lines');
        Schema::dropIfExists('procurement_grns');
    }
};
