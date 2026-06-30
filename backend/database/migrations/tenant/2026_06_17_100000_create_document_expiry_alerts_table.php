<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_expiry_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('window_days');
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['document_id', 'window_days']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_expiry_alerts');
    }
};
