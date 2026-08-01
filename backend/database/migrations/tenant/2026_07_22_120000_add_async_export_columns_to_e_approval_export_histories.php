<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('e_approval_export_histories')) {
            return;
        }

        Schema::table('e_approval_export_histories', function (Blueprint $table): void {
            if (! Schema::hasColumn('e_approval_export_histories', 'file_path')) {
                $table->string('file_path', 500)->nullable()->after('filename');
            }
            if (! Schema::hasColumn('e_approval_export_histories', 'disk')) {
                $table->string('disk', 64)->nullable()->after('file_path');
            }
            if (! Schema::hasColumn('e_approval_export_histories', 'content_type')) {
                $table->string('content_type', 120)->nullable()->after('disk');
            }
            if (! Schema::hasColumn('e_approval_export_histories', 'byte_size')) {
                $table->unsignedBigInteger('byte_size')->nullable()->after('content_type');
            }
            if (! Schema::hasColumn('e_approval_export_histories', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('byte_size');
            }
            if (! Schema::hasColumn('e_approval_export_histories', 'error_message')) {
                $table->text('error_message')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('e_approval_export_histories')) {
            return;
        }

        Schema::table('e_approval_export_histories', function (Blueprint $table): void {
            foreach (['file_path', 'disk', 'content_type', 'byte_size', 'expires_at', 'error_message'] as $column) {
                if (Schema::hasColumn('e_approval_export_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
