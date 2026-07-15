<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_prs')) {
            Schema::table('procurement_prs', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_prs', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('procurement_prs', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('voided_at');
                }
                if (! Schema::hasColumn('procurement_prs', 'voided_by_id')) {
                    $table->foreignUuid('voided_by_id')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('procurement_prs', 'lifecycle_reason')) {
                    $table->text('lifecycle_reason')->nullable()->after('voided_by_id');
                }
            });
        }

        if (Schema::hasTable('procurement_pos')) {
            Schema::table('procurement_pos', function (Blueprint $table): void {
                if (! Schema::hasColumn('procurement_pos', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('procurement_pos', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('voided_at');
                }
                if (! Schema::hasColumn('procurement_pos', 'voided_by_id')) {
                    $table->foreignUuid('voided_by_id')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('procurement_pos', 'lifecycle_reason')) {
                    $table->text('lifecycle_reason')->nullable()->after('voided_by_id');
                }
            });
        }

        if (! Schema::hasTable('procurement_lifecycle_events')) {
            Schema::create('procurement_lifecycle_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('document_type', 64);
                $table->uuid('document_id');
                $table->string('document_no', 64)->nullable();
                $table->string('action', 64);
                $table->text('reason')->nullable();
                $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata_json')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['document_type', 'document_id', 'created_at'], 'ple_doc_created_idx');
                $table->index('action', 'ple_action_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_lifecycle_events');

        if (Schema::hasTable('procurement_pos')) {
            Schema::table('procurement_pos', function (Blueprint $table): void {
                if (Schema::hasColumn('procurement_pos', 'voided_by_id')) {
                    $table->dropConstrainedForeignId('voided_by_id');
                }
                foreach (['lifecycle_reason', 'void_reason', 'voided_at'] as $column) {
                    if (Schema::hasColumn('procurement_pos', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('procurement_prs')) {
            Schema::table('procurement_prs', function (Blueprint $table): void {
                if (Schema::hasColumn('procurement_prs', 'voided_by_id')) {
                    $table->dropConstrainedForeignId('voided_by_id');
                }
                foreach (['lifecycle_reason', 'void_reason', 'voided_at'] as $column) {
                    if (Schema::hasColumn('procurement_prs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
