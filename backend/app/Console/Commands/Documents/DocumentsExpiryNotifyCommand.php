<?php

declare(strict_types=1);

namespace App\Console\Commands\Documents;

use App\Models\Tenant;
use App\Modules\Documents\Services\ControlledDocumentReviewNotificationService;
use App\Modules\Documents\Services\DocumentExpiryNotificationService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

class DocumentsExpiryNotifyCommand extends Command
{
    protected $signature = 'documents:expiry-notify
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Send in-app alerts when a published controlled document is due for review in 90, 60, or 30 days.';

    public function handle(
        ControlledDocumentReviewNotificationService $reviews,
        DocumentExpiryNotificationService $leaseDocuments,
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

            $tenant->run(function () use ($reviews, $leaseDocuments, $modules, $tenant, &$totalAlerts, &$totalDocuments): void {
                $enabled = $modules->resolveForCurrentTenant();
                $alerts = 0;
                $scanned = 0;

                if (in_array('document_register', $enabled, true)) {
                    $review = $reviews->run();
                    $alerts += $review['alerts_sent'];
                    $scanned += $review['documents_scanned'];
                }

                if (in_array('documents', $enabled, true)) {
                    $lease = $leaseDocuments->run();
                    $alerts += $lease['alerts_sent'];
                    $scanned += $lease['documents_scanned'];
                }

                $totalAlerts += $alerts;
                $totalDocuments += $scanned;

                if ($alerts > 0) {
                    $this->line(sprintf(
                        'Tenant %s: %d alert(s) for %d document(s) scanned.',
                        $tenant->id,
                        $alerts,
                        $scanned,
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
