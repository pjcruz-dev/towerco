<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('e_approval_form_outbound_files')) {
            Schema::create('e_approval_form_outbound_files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('form_id')->constrained('e_approval_forms')->cascadeOnDelete();
                $table->string('file_path', 512);
                $table->string('file_name', 255);
                $table->unsignedInteger('byte_size')->default(0);
                $table->foreignUuid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['form_id', 'created_at']);
            });
        }

        if (Schema::hasTable('e_approval_external_download_tokens')) {
            Schema::table('e_approval_external_download_tokens', function (Blueprint $table): void {
                if (! Schema::hasColumn('e_approval_external_download_tokens', 'form_outbound_file_id')) {
                    $table->uuid('form_outbound_file_id')->nullable()->after('attachment_id')->index();
                }
            });

            if (Schema::hasColumn('e_approval_external_download_tokens', 'attachment_id')) {
                Schema::table('e_approval_external_download_tokens', function (Blueprint $table): void {
                    $table->uuid('attachment_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_approval_external_download_tokens')) {
            if (Schema::hasColumn('e_approval_external_download_tokens', 'form_outbound_file_id')) {
                Schema::table('e_approval_external_download_tokens', function (Blueprint $table): void {
                    $table->dropColumn('form_outbound_file_id');
                });
            }
        }

        Schema::dropIfExists('e_approval_form_outbound_files');
    }
};
