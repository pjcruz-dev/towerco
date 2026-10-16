<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_messages') || Schema::hasColumn('ai_messages', 'activity')) {
            return;
        }

        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->json('activity')->nullable()->after('citations');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropColumn('activity');
        });
    }
};
