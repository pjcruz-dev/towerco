<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Billings was previously always available under Team & Access.
 * Tenants with an explicit enabled_modules override keep access unless operators turn it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()
            ->whereNotNull('enabled_modules')
            ->cursor()
            ->each(function (Tenant $tenant): void {
                $modules = $tenant->enabled_modules;
                if (! is_array($modules) || $modules === []) {
                    return;
                }

                if (! in_array('billings', $modules, true)) {
                    $modules[] = 'billings';
                    $tenant->forceFill([
                        'enabled_modules' => array_values(array_unique($modules)),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Tenant::query()
            ->whereNotNull('enabled_modules')
            ->cursor()
            ->each(function (Tenant $tenant): void {
                $modules = $tenant->enabled_modules;
                if (! is_array($modules) || $modules === []) {
                    return;
                }

                $filtered = array_values(array_filter(
                    $modules,
                    static fn (string $module): bool => $module !== 'billings',
                ));

                if (count($filtered) !== count($modules)) {
                    $tenant->forceFill(['enabled_modules' => $filtered])->saveQuietly();
                }
            });
    }
};
