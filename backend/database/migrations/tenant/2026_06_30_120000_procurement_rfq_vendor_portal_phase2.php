<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_rfq_bids')) {
            return;
        }

        if (! Schema::hasTable('procurement_rfq_bid_versions')) {
            Schema::create('procurement_rfq_bid_versions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('bid_id');
                $table->unsignedSmallInteger('version_no')->default(1);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('currency_code', 8)->default('PHP');
                $table->date('validity_until')->nullable();
                $table->unsignedInteger('avg_lead_time_days')->nullable();
                $table->text('notes')->nullable();
                $table->string('submitted_via', 16)->default('internal');
                $table->uuid('captured_by_id')->nullable();
                $table->string('portal_contact_name', 255)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->foreign('bid_id', 'proc_rfq_bidver_bid_fk')
                    ->references('id')
                    ->on('procurement_rfq_bids')
                    ->cascadeOnDelete();
                $table->foreign('captured_by_id', 'proc_rfq_bidver_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->unique(['bid_id', 'version_no'], 'proc_rfq_bidver_bid_ver_unq');
            });
        }

        if (! Schema::hasTable('procurement_rfq_bid_version_lines')) {
            Schema::create('procurement_rfq_bid_version_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('version_id');
                $table->uuid('rfq_line_id');
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('version_id', 'proc_rfq_bidverline_ver_fk')
                    ->references('id')
                    ->on('procurement_rfq_bid_versions')
                    ->cascadeOnDelete();
                $table->foreign('rfq_line_id', 'proc_rfq_bidverline_rfqline_fk')
                    ->references('id')
                    ->on('procurement_rfq_lines')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('procurement_rfq_bid_attachments')) {
            Schema::create('procurement_rfq_bid_attachments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('bid_id');
                $table->uuid('version_id')->nullable();
                $table->string('field_name', 64)->default('quotation');
                $table->string('file_name');
                $table->string('stored_path');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('uploaded_via', 16)->default('internal');
                $table->uuid('uploaded_by_id')->nullable();
                $table->timestamps();

                $table->foreign('bid_id', 'proc_rfq_bidatt_bid_fk')
                    ->references('id')
                    ->on('procurement_rfq_bids')
                    ->cascadeOnDelete();
                $table->foreign('version_id', 'proc_rfq_bidatt_ver_fk')
                    ->references('id')
                    ->on('procurement_rfq_bid_versions')
                    ->nullOnDelete();
                $table->foreign('uploaded_by_id', 'proc_rfq_bidatt_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('procurement_rfq_vendors')) {
            Schema::table('procurement_rfq_vendors', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_rfq_vendors', 'reminder_log_json')) {
                    $table->json('reminder_log_json')->nullable()->after('portal_contact_name');
                }
                if (! Schema::hasColumn('procurement_rfq_vendors', 'invitation_token_encrypted')) {
                    $table->text('invitation_token_encrypted')->nullable()->after('invitation_token_hash');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_rfq_bid_attachments');
        Schema::dropIfExists('procurement_rfq_bid_version_lines');
        Schema::dropIfExists('procurement_rfq_bid_versions');

        if (Schema::hasTable('procurement_rfq_vendors') && Schema::hasColumn('procurement_rfq_vendors', 'reminder_log_json')) {
            Schema::table('procurement_rfq_vendors', function (Blueprint $table): void {
                $table->dropColumn('reminder_log_json');
            });
        }
    }
};
