<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_submissions')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                if (! Schema::hasColumn('e_approval_submissions', 'external_resubmit_token_hash')) {
                    $table->string('external_resubmit_token_hash', 64)->nullable();
                }
                if (! Schema::hasColumn('e_approval_submissions', 'external_resubmit_token_expires_at')) {
                    $table->timestamp('external_resubmit_token_expires_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('e_approval_external_download_tokens')) {
            Schema::create('e_approval_external_download_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('submission_id')->index();
                $table->uuid('attachment_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('downloaded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_external_download_tokens');

        if (Schema::hasTable('e_approval_submissions')) {
            Schema::table('e_approval_submissions', function (Blueprint $table): void {
                if (Schema::hasColumn('e_approval_submissions', 'external_resubmit_token_expires_at')) {
                    $table->dropColumn('external_resubmit_token_expires_at');
                }
                if (Schema::hasColumn('e_approval_submissions', 'external_resubmit_token_hash')) {
                    $table->dropColumn('external_resubmit_token_hash');
                }
            });
        }
    }
};
