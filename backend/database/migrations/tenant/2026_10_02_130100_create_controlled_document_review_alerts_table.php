<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('controlled_document_review_alerts')) {
            return;
        }

        Schema::create('controlled_document_review_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('controlled_document_id')
                ->constrained('controlled_documents')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('window_days');
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['controlled_document_id', 'window_days'], 'cd_review_alerts_doc_window_uniq');
            $table->index('sent_at', 'cd_review_alerts_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_document_review_alerts');
    }
};
