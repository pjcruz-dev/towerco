<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roles = (string) (config('permission.table_names.roles') ?? 'roles');
        if (! Schema::hasTable($roles)) {
            return;
        }
        if (Schema::hasColumn($roles, 'access_matrix_json')) {
            return;
        }

        Schema::table($roles, static function (Blueprint $table): void {
            $table->json('access_matrix_json')->nullable()->after('guard_name');
        });
    }

    public function down(): void
    {
        $roles = (string) (config('permission.table_names.roles') ?? 'roles');
        if (! Schema::hasTable($roles) || ! Schema::hasColumn($roles, 'access_matrix_json')) {
            return;
        }

        Schema::table($roles, static function (Blueprint $table): void {
            $table->dropColumn('access_matrix_json');
        });
    }
};
