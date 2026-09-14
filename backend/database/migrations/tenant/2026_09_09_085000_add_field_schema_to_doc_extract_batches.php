<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doc_extract_batches')) {
            return;
        }

        Schema::table('doc_extract_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('doc_extract_batches', 'field_schema')) {
                $table->json('field_schema')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('doc_extract_batches')) {
            return;
        }

        Schema::table('doc_extract_batches', function (Blueprint $table): void {
            if (Schema::hasColumn('doc_extract_batches', 'field_schema')) {
                $table->dropColumn('field_schema');
            }
        });
    }
};
