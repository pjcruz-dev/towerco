<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_knowledge_sources', function (Blueprint $table): void {
            $table->longText('body')->nullable()->after('source_url');
            $table->timestamp('last_indexed_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_sources', function (Blueprint $table): void {
            $table->dropColumn(['body', 'last_indexed_at']);
        });
    }
};
