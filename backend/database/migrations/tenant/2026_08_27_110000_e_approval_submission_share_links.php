<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_submission_share_links')) {
            return;
        }

        Schema::create('e_approval_submission_share_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_submission_share_links');
    }
};
