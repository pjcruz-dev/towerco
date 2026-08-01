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
            $table->string('slug', 128)->nullable()->after('id');
            $table->string('content_checksum', 64)->nullable()->after('version');
            $table->unique(['scope', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_sources', function (Blueprint $table): void {
            $table->dropUnique(['scope', 'slug']);
            $table->dropColumn(['slug', 'content_checksum']);
        });
    }
};
