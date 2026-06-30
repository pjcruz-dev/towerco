<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementLineMetadataRepairService;
use Illuminate\Console\Command;

final class ProcurementOneRepairLineMetadataCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'procurement-one:repair-line-metadata
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--all : Run for every tenant}
        {--dry-run : Preview changes without writing}
        {--pr= : Optional purchase requisition UUID}
        {--po= : Optional purchase order UUID}
    ';

    protected $description = 'Backfill procurement PR/PO line metadata_json from E-Approval submission grid values.';

    public function handle(ProcurementLineMetadataRepairService $repair): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched. Pass --tenant=UUID, --domain=hostname, or --all.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prId = $this->stringOption('pr');
        $poId = $this->stringOption('po');

        if ($dryRun) {
            $this->warn('Dry run — no database writes will be performed.');
        }

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->value('domain') ?? $tenant->id;

            $result = $tenant->run(function () use ($repair, $dryRun, $prId, $poId): array {
                return $repair->repair($dryRun, $prId, $poId);
            });

            $this->line(sprintf(
                '[%s] PR docs: %d | PR lines updated: %d/%d scanned (%d skipped) | PO docs: %d | PO lines updated: %d/%d scanned (%d skipped) | RFQs synced: %d%s',
                $domain,
                $result['pr_documents'],
                $result['pr_lines_updated'],
                $result['pr_lines_scanned'],
                $result['pr_lines_skipped'],
                $result['po_documents'],
                $result['po_lines_updated'],
                $result['po_lines_scanned'],
                $result['po_lines_skipped'],
                $result['rfqs_synced'] ?? 0,
                $dryRun ? ' [dry-run]' : '',
            ));
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply changes.'
            : 'Line metadata repair complete.');

        return self::SUCCESS;
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('id')->get()->all();
        }

        $tenant = $this->resolveTenantFromOptions();

        return $tenant instanceof Tenant ? [$tenant] : [];
    }

    private function stringOption(string $key): ?string
    {
        $value = trim((string) $this->option($key));

        return $value !== '' ? $value : null;
    }
}
