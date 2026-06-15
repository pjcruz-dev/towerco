<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_login_exempt')->default(false)->after('is_active');
        });

        $bootstrapAdminIds = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_uuid', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Modules\\Identity\\Models\\TenantUser');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'tenant_admin')
            ->where('users.email', 'like', 'admin@%')
            ->pluck('users.id');

        if ($bootstrapAdminIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $bootstrapAdminIds)
                ->update(['password_login_exempt' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_login_exempt');
        });
    }
};
