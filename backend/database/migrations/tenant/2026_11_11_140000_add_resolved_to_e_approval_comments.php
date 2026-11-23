<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable()->after('image_attachment_ids');
            $table->uuid('resolved_by_id')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->dropColumn(['resolved_at', 'resolved_by_id']);
        });
    }
};
