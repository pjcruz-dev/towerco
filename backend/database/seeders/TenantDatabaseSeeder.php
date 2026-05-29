<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Database\Seeder;

/**
 * Optional tenant seed hook (for example `tenants:migrate --seed`).
 *
 * Baseline RBAC is also ensured when provisioning creates the initial tenant admin.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantRbacBaselineService::class)->ensure();

        if (config('toweros.demo.seed_on_tenant_migrate')) {
            $this->call(AllianceDemoSeeder::class);
        }
    }
}
