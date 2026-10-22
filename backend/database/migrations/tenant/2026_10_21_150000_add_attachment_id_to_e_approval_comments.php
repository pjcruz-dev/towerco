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
            $table->foreignUuid('attachment_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('e_approval_attachments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attachment_id');
        });
    }
};
