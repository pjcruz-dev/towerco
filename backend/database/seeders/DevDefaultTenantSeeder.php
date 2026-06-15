<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tenancy\Services\TenantOnboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

/**
 * Local/docker bootstrap: ensures a default tenant hostname exists after central db:seed
 * so tenant modules work immediately on that hostname (optional local convenience).
 */
class DevDefaultTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('toweros.seed_dev_default_tenant')) {
            return;
        }

        $domain = strtolower(trim((string) (config('toweros.dev_default_tenant.domain') ?? '')));
        if ($domain === '') {
            return;
        }

        $slug = trim((string) (config('toweros.dev_default_tenant.slug') ?? ''));
        if ($slug === '') {
            $slug = explode('.', $domain)[0] ?? 'tenant';
        }

        $existingDomain = Domain::query()->where('domain', $domain)->first();
        if ($existingDomain !== null) {
            $this->ensureRbacForTenantId((string) $existingDomain->tenant_id, $domain);
            return;
        }

        try {
            $result = app(TenantOnboardingService::class)->createTenant([
                'domain' => $domain,
                'slug' => $slug,
                'brand_domain' => (string) config('toweros.dev_default_tenant.brand_domain', 'example.com'),
                'environment' => 'local',
                'migrate' => true,
            ]);

            $this->ensureRbacForTenantId((string) $result['tenant']->id, $domain);

            $this->command?->info("Dev default tenant provisioned: {$domain} ({$result['tenant']->id})");
        } catch (Throwable $e) {
            Log::warning('DevDefaultTenantSeeder skipped: '.$e->getMessage());
            $this->command?->warn('Dev default tenant was not created: '.$e->getMessage());
        }
    }

    private function ensureRbacForTenantId(string $tenantId, string $domain): void
    {
        try {
            Artisan::call('tenants:ensure-rbac', [
                '--tenants' => [$tenantId],
            ]);
            $this->command?->info("Dev default tenant RBAC ensured: {$domain} ({$tenantId})");
        } catch (Throwable $e) {
            Log::warning("DevDefaultTenantSeeder RBAC ensure failed for {$domain}: ".$e->getMessage());
            $this->command?->warn("Could not ensure RBAC for {$domain}: ".$e->getMessage());
        }
    }
}
