<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('e_approval_notifications')) {
            return;
        }

        // Core e-approval migration (2026_06_01_100000) already includes these columns on fresh tenants.
        if (Schema::hasColumn('e_approval_notifications', 'actor_user_id')) {
            return;
        }

        Schema::table('e_approval_notifications', function (Blueprint $table): void {
            $table->foreignUuid('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable()->after('actor_user_id');
            $table->string('category', 16)->default('update')->after('type');
            $table->string('document_no', 64)->nullable()->after('submission_id');
            $table->string('form_name', 255)->nullable()->after('document_no');
            $table->text('body_preview')->nullable()->after('message');
            $table->string('href', 512)->nullable()->after('body_preview');

            $table->index(['user_id', 'category', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'category', 'is_read']);
            $table->dropConstrainedForeignId('actor_user_id');
            $table->dropColumn([
                'actor_name',
                'category',
                'document_no',
                'form_name',
                'body_preview',
                'href',
            ]);
        });
    }
};
