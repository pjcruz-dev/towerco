<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('e_approval_attachments')) {
            return;
        }

        Schema::table('e_approval_attachments', static function (Blueprint $table): void {
            if (! Schema::hasColumn('e_approval_attachments', 'metadata')) {
                // Per-photo geotag / caption / slot for camera fields (lat, lng, captured_at, caption, slot).
                $table->json('metadata')->nullable()->after('file_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('e_approval_attachments')) {
            return;
        }

        Schema::table('e_approval_attachments', static function (Blueprint $table): void {
            if (Schema::hasColumn('e_approval_attachments', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
