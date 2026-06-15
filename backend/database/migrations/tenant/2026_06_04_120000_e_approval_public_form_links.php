<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_approval_public_form_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('e_approval_forms')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->string('token_hash', 64);
            $table->string('password_hash', 255)->nullable();
            $table->foreignUuid('sponsor_user_id')->constrained('users');
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_submissions')->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'is_enabled']);
            $table->index('token_hash');
        });

        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->string('submission_source', 20)->default('internal')->after('requestor_id');
            $table->string('external_submitter_name', 255)->nullable()->after('submission_source');
            $table->string('external_submitter_email', 255)->nullable()->after('external_submitter_name');
            $table->foreignUuid('public_link_id')->nullable()->after('external_submitter_email')
                ->constrained('e_approval_public_form_links')->nullOnDelete();
            $table->string('external_upload_token_hash', 64)->nullable()->after('public_link_id');
            $table->timestamp('external_upload_token_expires_at')->nullable()->after('external_upload_token_hash');
            $table->string('external_client_ip', 45)->nullable()->after('external_upload_token_expires_at');
            $table->string('external_user_agent', 512)->nullable()->after('external_client_ip');
        });

        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->dropForeign(['requestor_id']);
        });

        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->uuid('requestor_id')->nullable()->change();
            $table->foreign('requestor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->dropForeign(['requestor_id']);
            $table->dropForeign(['public_link_id']);
            $table->dropColumn([
                'submission_source',
                'external_submitter_name',
                'external_submitter_email',
                'public_link_id',
                'external_upload_token_hash',
                'external_upload_token_expires_at',
                'external_client_ip',
                'external_user_agent',
            ]);
        });

        Schema::table('e_approval_submissions', function (Blueprint $table): void {
            $table->uuid('requestor_id')->nullable(false)->change();
            $table->foreign('requestor_id')->references('id')->on('users');
        });

        Schema::dropIfExists('e_approval_public_form_links');
    }
};
