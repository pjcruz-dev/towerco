<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementRfqAutoCloseService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

final class ProcurementRfqAutoCloseCommand extends Command
{
    protected $signature = 'procurement:rfq-auto-close
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Close open RFQs whose bidding deadline has passed.';

    public function handle(
        ProcurementRfqAutoCloseService $service,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $totalClosed = 0;
        $totalScanned = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $modules, $tenant, &$totalClosed, &$totalScanned): void {
                if (! in_array('procurement_one', $modules->resolveForCurrentTenant(), true)) {
                    return;
                }

                $result = $service->run();
                $totalClosed += $result['rfqs_closed'];
                $totalScanned += $result['rfqs_scanned'];

                if ($result['rfqs_closed'] > 0) {
                    $this->line(sprintf(
                        'Tenant %s: closed %d RFQ(s) of %d expired.',
                        $tenant->id,
                        $result['rfqs_closed'],
                        $result['rfqs_scanned'],
                    ));
                }
            });
        }

        $this->info("RFQ auto-close complete. {$totalClosed} closed, {$totalScanned} expired scanned.");

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

        $domain = (string) ($this->option('domain') ?: '');
        if ($domain !== '') {
            $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

            return $tenant !== null ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
