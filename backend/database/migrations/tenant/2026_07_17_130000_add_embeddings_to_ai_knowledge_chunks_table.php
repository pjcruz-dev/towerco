<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->json('embedding')->nullable()->after('vector_id');
            $table->unsignedSmallInteger('embedding_dimensions')->nullable()->after('embedding');
            $table->string('embedding_model', 128)->nullable()->after('embedding_dimensions');
            $table->timestamp('indexed_at')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->dropColumn(['embedding', 'embedding_dimensions', 'embedding_model', 'indexed_at']);
        });
    }
};
