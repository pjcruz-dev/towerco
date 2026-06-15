<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Http\JsonResponse;

final class CentralTenantModulesCatalogController extends AbstractApiController
{
    public function __invoke(TenantEnabledModulesResolver $resolver): JsonResponse
    {
        return $this->ok($resolver->catalogForPlatformApi());
    }
}
