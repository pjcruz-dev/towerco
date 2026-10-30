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
            $table->json('image_attachment_ids')->nullable()->after('pin_h');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->dropColumn('image_attachment_ids');
        });
    }
};
