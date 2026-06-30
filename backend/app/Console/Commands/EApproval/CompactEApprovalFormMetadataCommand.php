<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormSnapshotSanitizer;
use Illuminate\Console\Command;

class CompactEApprovalFormMetadataCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'e-approval:compact-form-metadata
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
        {--form= : Optional form UUID}
    ';

    protected $description = 'Strip nested revision blobs from e-approval form metadata and published snapshots.';

    public function handle(): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $formId = $this->option('form');

        $compacted = $tenant->run(function () use ($formId): array {
            $updated = [];
            $query = EApprovalForm::query();

            if (is_string($formId) && $formId !== '') {
                $query->where('id', $formId);
            }

            $query->each(function (EApprovalForm $form) use (&$updated): void {
                if (EApprovalFormSnapshotSanitizer::compactStoredMetadata($form)) {
                    $updated[] = "{$form->id} ({$form->name})";
                }
            });

            return $updated;
        });

        foreach ($compacted as $line) {
            $this->line("Compacted {$line}");
        }

        $this->info('Compacted '.count($compacted).' form(s).');

        return self::SUCCESS;
    }
}
