<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalFormImportExportService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ImportEApprovalFormSampleCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'e-approval:import-form-sample
        {file : Sample JSON filename under docs/samples/e-approval-imports/}
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--publish : Import as published (default: draft)}
        {--dry-run : Validate only}
    ';

    protected $description = 'Import an atc-form-export JSON sample into a tenant.';

    public function handle(EApprovalFormImportExportService $importExport): int
    {
        $file = trim((string) $this->argument('file'));
        $path = $this->resolveSamplePath($file);
        if ($path === null) {
            $this->error("Sample not found: {$file} (looked under docs/samples/e-approval-imports/)");

            return self::FAILURE;
        }

        $body = json_decode(File::get($path), true);
        if (! is_array($body)) {
            $this->error('Invalid JSON in sample file.');

            return self::FAILURE;
        }

        if ($this->option('publish')) {
            $inner = is_array($body['form'] ?? null) ? $body['form'] : $body;
            if (is_array($body['form'] ?? null)) {
                $body['form']['status'] = 'published';
            } else {
                $body['status'] = 'published';
            }
        }

        $tenant = $this->resolveTenantFromOptions();
        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found. Use --tenant=UUID or --domain=app.towerone.localhost');

            return self::FAILURE;
        }

        $checked = $importExport->validateImportPayload($body);
        if ($checked['ok'] === false) {
            $this->error($checked['error']);

            return self::FAILURE;
        }

        $formName = (string) ($checked['inner']['name'] ?? 'form');
        $this->info("Sample: {$formName}");
        foreach ($checked['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — valid sample, no import performed.');

            return self::SUCCESS;
        }

        return $tenant->run(function () use ($importExport, $body, $formName): int {
            $actor = TenantUser::query()->orderBy('created_at')->first();
            if ($actor === null) {
                $this->error('No tenant user found.');

                return self::FAILURE;
            }

            $result = $importExport->import($body, $actor);
            $form = $result['form'];

            foreach ($result['warnings'] as $warning) {
                $this->warn($warning);
            }

            $this->info("Imported: {$form->name} ({$form->id}) — status {$form->status}");

            return self::SUCCESS;
        });
    }

    private function resolveSamplePath(string $file): ?string
    {
        $basename = basename($file);
        $candidates = [
            base_path('docs/samples/e-approval-imports/'.$basename),
            dirname(base_path()).'/docs/samples/e-approval-imports/'.$basename,
        ];

        foreach ($candidates as $path) {
            if (File::isFile($path)) {
                return $path;
            }
        }

        return null;
    }
}
