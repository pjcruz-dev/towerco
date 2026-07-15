<?php

declare(strict_types=1);

namespace App\Console\Commands\Documents;

use App\Models\Tenant;
use App\Modules\Documents\Services\DocumentSiteReviewFormProvisionerService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class DocumentsEnsureSiteReviewFormCommand extends Command
{
    protected $signature = 'documents:ensure-site-review-form
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Create and publish the Site document review E-Approval form for site binder requests.';

    public function handle(
        DocumentSiteReviewFormProvisionerService $provisioner,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($provisioner, $modules, $tenant): void {
                $enabled = $modules->resolveForCurrentTenant();
                if (! in_array('documents', $enabled, true) || ! in_array('e_approval', $enabled, true)) {
                    $this->warn("Tenant {$tenant->id}: documents or e_approval module disabled — skipped.");

                    return;
                }

                $actor = $this->resolveActor();
                if ($actor === null) {
                    $this->error("Tenant {$tenant->id}: no tenant_admin user found.");

                    return;
                }

                $form = $provisioner->ensure($actor);
                $this->info("Tenant {$tenant->id}: published form {$form->name} ({$form->id})");
            });
        }

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

    private function resolveActor(): ?TenantUser
    {
        $adminRole = Role::query()->where('name', 'tenant_admin')->where('guard_name', 'sanctum')->first();
        if ($adminRole !== null) {
            /** @var TenantUser|null $admin */
            $admin = TenantUser::query()->role('tenant_admin')->orderBy('created_at')->first();
            if ($admin !== null) {
                return $admin;
            }
        }

        return TenantUser::query()->orderBy('created_at')->first();
    }
}
