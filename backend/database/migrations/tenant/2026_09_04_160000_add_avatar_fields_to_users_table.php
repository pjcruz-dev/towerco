<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('entra_manager_parent_id');
            }
            if (! Schema::hasColumn('users', 'avatar_synced_at')) {
                $table->timestamp('avatar_synced_at')->nullable()->after('avatar_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'avatar_synced_at')) {
                $table->dropColumn('avatar_synced_at');
            }
            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
