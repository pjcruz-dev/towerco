<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

final class ProcurementRfqPruneDuplicateDraftsCommand extends Command
{
    protected $signature = 'procurement:rfq-prune-duplicate-drafts
        {--domain= : Tenant domain (e.g. towerone.localhost, not the full app URL)}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Cancel draft RFQs on PRs that already have an in-progress or awarded RFQ.';

    public function handle(
        ProcurementRfqService $service,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            return self::FAILURE;
        }

        $totalCancelled = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $modules, $tenant, &$totalCancelled): void {
                if (! in_array('procurement_one', $modules->resolveForCurrentTenant(), true)) {
                    $this->warn(sprintf('Tenant %s: procurement_one module is not enabled.', $tenant->id));

                    return;
                }

                $cancelled = $service->pruneDuplicateDraftRfqs();
                $totalCancelled += $cancelled;

                if ($cancelled > 0) {
                    $this->line(sprintf('Tenant %s: cancelled %d duplicate draft RFQ(s).', $tenant->id, $cancelled));
                } else {
                    $this->line(sprintf('Tenant %s: no duplicate draft RFQs to cancel.', $tenant->id));
                }
            });
        }

        $this->info("Duplicate draft RFQ prune complete. {$totalCancelled} cancelled.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter((array) $this->option('tenants'), static fn ($id) => is_string($id) && $id !== ''));
        if ($explicit !== []) {
            return $explicit;
        }

        $domain = $this->normalizeDomainOption((string) ($this->option('domain') ?: ''));
        if ($domain !== '') {
            $tenant = $this->findTenantByDomain($domain);
            if ($tenant === null) {
                $this->reportUnknownDomain($domain);

                return [];
            }

            return [(string) $tenant->id];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }

    private function normalizeDomainOption(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https?://#i', '', $value) ?? $value;
        $value = strtok($value, '/') ?: $value;

        return strtolower(trim($value));
    }

    private function findTenantByDomain(string $domain): ?Tenant
    {
        foreach ($this->domainCandidates($domain) as $candidate) {
            $tenant = Tenant::query()
                ->whereHas('domains', static fn ($query) => $query->where('domain', $candidate))
                ->first();

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function domainCandidates(string $domain): array
    {
        $candidates = [$domain];

        if (str_starts_with($domain, 'app.')) {
            $candidates[] = substr($domain, 4);
        } else {
            $candidates[] = "app.{$domain}";
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate) => $candidate !== '')));
    }

    private function reportUnknownDomain(string $domain): void
    {
        $this->error("No tenant found for domain [{$domain}].");
        $this->line('Use --domain=<tenant-domain>, not the browser URL flag style.');
        $this->line('Example: php artisan procurement:rfq-prune-duplicate-drafts --domain=towerone.localhost');

        $registered = Domain::query()->orderBy('domain')->pluck('domain')->all();
        if ($registered !== []) {
            $this->newLine();
            $this->line('Registered tenant domains:');
            foreach ($registered as $registeredDomain) {
                $this->line("  - {$registeredDomain}");
            }
        }
    }
}
