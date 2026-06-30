<?php

declare(strict_types=1);

namespace App\Console\Commands\Documents;

use App\Models\Tenant;
use App\Modules\Documents\Services\DocumentExpiryNotificationService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

class DocumentsExpiryNotifyCommand extends Command
{
    protected $signature = 'documents:expiry-notify
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Send in-app alerts for documents expiring in 90, 60, or 30 days.';

    public function handle(
        DocumentExpiryNotificationService $service,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $totalAlerts = 0;
        $totalDocuments = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $modules, $tenant, &$totalAlerts, &$totalDocuments): void {
                if (! in_array('documents', $modules->resolveForCurrentTenant(), true)) {
                    return;
                }

                $result = $service->run();
                $totalAlerts += $result['alerts_sent'];
                $totalDocuments += $result['documents_scanned'];

                if ($result['alerts_sent'] > 0) {
                    $this->line(sprintf(
                        'Tenant %s: %d alert(s) for %d document(s) scanned.',
                        $tenant->id,
                        $result['alerts_sent'],
                        $result['documents_scanned'],
                    ));
                }
            });
        }

        $this->info("Expiry notify complete. {$totalAlerts} alert(s), {$totalDocuments} document(s) scanned.");

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

            return $tenant ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
