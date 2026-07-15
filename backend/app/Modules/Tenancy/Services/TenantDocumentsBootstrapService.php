<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Documents\Services\DocumentSiteReviewFormProvisionerService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;

final class TenantDocumentsBootstrapService
{
    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    public function provisionSiteDocumentReviewForm(Tenant $tenant): void
    {
        $enabled = $this->enabledModules->resolveForTenant($tenant);
        if (! in_array('documents', $enabled, true) || ! in_array('e_approval', $enabled, true)) {
            return;
        }

        $tenant->run(function (): void {
            $admin = TenantUser::query()->role('tenant_admin')->orderBy('created_at')->first();
            if ($admin === null) {
                return;
            }

            app(DocumentSiteReviewFormProvisionerService::class)->ensure($admin);
        });
    }
}
