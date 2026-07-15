<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\Rollout\Services\ManualTrackerImportService;
use Illuminate\Console\Command;

final class ImportManualTrackerCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'project-one:import-manual-tracker
        {file : Path to CSV or XLSX export from the legacy manual tracker}
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
        {--dry-run : Parse rows without writing}
        {--inspect : Show detected layout and site count only}
    ';

    protected $description = 'One-time import of legacy manual site tracker rows into Project-One rollouts.';

    public function handle(ManualTrackerImportService $import): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $inspect = (bool) $this->option('inspect');

        $result = $tenant->run(function () use ($import, $path, $dryRun, $inspect): array {
            config(['broadcasting.default' => 'null']);

            if ($inspect) {
                $parsed = $import->inspectFile($path);

                return [
                    'imported' => count($parsed['payloads']),
                    'skipped' => 0,
                    'errors' => [],
                    'sheet' => $parsed['sheet'],
                    'layout' => $parsed['layout'],
                    'site_count' => count($parsed['payloads']),
                    'hints' => $parsed['hints'] ?? [],
                    'inspect' => true,
                ];
            }

            return $import->importFile($path, $dryRun);
        });

        if (! empty($result['inspect'])) {
            $this->info("Sheet: {$result['sheet']}");
            $this->info("Layout: {$result['layout']}");
            $this->info("Sites detected: {$result['site_count']}");
            foreach ($result['hints'] ?? [] as $hint) {
                $this->warn($hint);
            }
            $this->line('Row layout = headers across row 1, sites in rows 2…609 (your screenshot).');
            $this->line('Transposed layout = field names down column A/B, one site per column to the right.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Dry run — would import' : 'Imported').": {$result['imported']} row(s); skipped {$result['skipped']}.");
        if (isset($result['sheet'], $result['layout'])) {
            $this->line("Source sheet: {$result['sheet']} ({$result['layout']} layout, {$result['site_count']} site(s) detected).");
        }

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
