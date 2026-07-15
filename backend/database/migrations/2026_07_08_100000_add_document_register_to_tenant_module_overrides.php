<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

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

                if (
                    in_array('documents', $modules, true)
                    && ! in_array('document_register', $modules, true)
                ) {
                    $modules[] = 'document_register';
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
                    static fn (string $module): bool => $module !== 'document_register',
                ));

                if (count($filtered) !== count($modules)) {
                    $tenant->forceFill(['enabled_modules' => $filtered])->saveQuietly();
                }
            });
    }
};
