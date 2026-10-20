<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('controlled_documents') && ! Schema::hasColumn('controlled_documents', 'dcf_number')) {
            Schema::table('controlled_documents', function (Blueprint $table): void {
                $table->string('dcf_number', 16)->nullable()->after('document_code');
                $table->unique('dcf_number', 'cd_doc_dcf_uq');
            });
        }

        if (Schema::hasTable('controlled_document_revisions') && ! Schema::hasColumn('controlled_document_revisions', 'dcf_number')) {
            Schema::table('controlled_document_revisions', function (Blueprint $table): void {
                $table->string('dcf_number', 16)->nullable()->after('revision_number');
                $table->unique('dcf_number', 'cd_rev_dcf_uq');
            });
        }

        if (! Schema::hasTable('e_approval_released_document_numbers')) {
            Schema::create('e_approval_released_document_numbers', function (Blueprint $table): void {
                $table->string('prefix', 128);
                $table->unsignedInteger('seq_no');
                $table->primary(['prefix', 'seq_no']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_released_document_numbers');

        if (Schema::hasTable('controlled_document_revisions') && Schema::hasColumn('controlled_document_revisions', 'dcf_number')) {
            Schema::table('controlled_document_revisions', function (Blueprint $table): void {
                $table->dropUnique('cd_rev_dcf_uq');
                $table->dropColumn('dcf_number');
            });
        }

        if (Schema::hasTable('controlled_documents') && Schema::hasColumn('controlled_documents', 'dcf_number')) {
            Schema::table('controlled_documents', function (Blueprint $table): void {
                $table->dropUnique('cd_doc_dcf_uq');
                $table->dropColumn('dcf_number');
            });
        }
    }
};
