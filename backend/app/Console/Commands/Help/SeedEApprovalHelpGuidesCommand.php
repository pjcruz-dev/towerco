<?php

declare(strict_types=1);

namespace App\Console\Commands\Help;

use App\Models\Tenant;
use App\Modules\Help\Models\HelpGuide;
use App\Modules\Help\Services\HelpGuideService;
use Illuminate\Console\Command;

final class SeedEApprovalHelpGuidesCommand extends Command
{
    protected $signature = 'help:seed-e-approval-guides
        {--tenants=* : Tenant UUID(s)}
        {--domain= : Run for a single tenant domain}
        {--force : Overwrite guides even if admins edited them}
    ';

    protected $description = 'Seed editable E-Approval requestor and approver user guides for tenant(s).';

    public function handle(HelpGuideService $service): int
    {
        $templates = $this->templates();
        if ($templates === []) {
            $this->error('Guide template files were not found under docs/modules.');

            return self::FAILURE;
        }

        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $templates, $force, $tenant, &$created, &$updated, &$skipped): void {
                foreach ($templates as $template) {
                    $result = $service->seedGuide(
                        moduleKey: 'e_approval',
                        slug: $template['slug'],
                        role: $template['role'],
                        title: $template['title'],
                        body: $template['body'],
                        sortOrder: $template['sort_order'],
                        force: $force,
                    );

                    match ($result['action']) {
                        'created' => $created++,
                        'updated' => $updated++,
                        default => $skipped++,
                    };
                }

                $this->line(sprintf('Tenant %s seeded.', $tenant->id));
            });
        }

        $this->info("Done. created={$created} updated={$updated} skipped={$skipped}");

        return self::SUCCESS;
    }

    private function docsModulesPath(): ?string
    {
        $candidates = [
            app_path('Modules/Help/Resources/guides'),
            base_path('../docs/modules'),
            base_path('docs/modules'),
            dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'modules',
        ];

        foreach ($candidates as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<array{slug: string, role: string, title: string, body: string, sort_order: int}>
     */
    private function templates(): array
    {
        $base = $this->docsModulesPath();
        if ($base === null) {
            return [];
        }

        $requestorCandidates = [
            $base.DIRECTORY_SEPARATOR.'e-approval-for-requestors.md',
            $base.DIRECTORY_SEPARATOR.'e-approval-guide-requestor.md',
        ];
        $approverCandidates = [
            $base.DIRECTORY_SEPARATOR.'e-approval-for-approvers.md',
            $base.DIRECTORY_SEPARATOR.'e-approval-guide-approver.md',
        ];

        $requestorPath = $this->firstExistingFile($requestorCandidates);
        $approverPath = $this->firstExistingFile($approverCandidates);

        if ($requestorPath === null || $approverPath === null) {
            return [];
        }

        return [
            [
                'slug' => 'e-approval-for-requestors',
                'role' => HelpGuide::ROLE_REQUESTOR,
                'title' => 'E-Approval for requestors',
                'body' => (string) file_get_contents($requestorPath),
                'sort_order' => 10,
            ],
            [
                'slug' => 'e-approval-for-approvers',
                'role' => HelpGuide::ROLE_APPROVER,
                'title' => 'E-Approval for approvers',
                'body' => (string) file_get_contents($approverPath),
                'sort_order' => 20,
            ],
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    private function firstExistingFile(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter(
            (array) $this->option('tenants'),
            static fn ($id) => is_string($id) && $id !== '',
        ));
        if ($explicit !== []) {
            return $explicit;
        }

        $domain = (string) ($this->option('domain') ?: '');
        if ($domain !== '') {
            $tenant = Tenant::query()
                ->whereHas('domains', static fn ($q) => $q->where('domain', $domain))
                ->first();

            return $tenant ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
