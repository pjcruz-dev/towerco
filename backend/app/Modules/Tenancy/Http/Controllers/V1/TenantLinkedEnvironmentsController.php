<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantLinkedEnvironmentsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantLinkedEnvironmentsController extends AbstractApiController
{
    public function __invoke(TenantLinkedEnvironmentsService $environments): JsonResponse
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException(__('Tenant context is required.'));
        }

        return $this->ok($environments->listForTenant($tenant));
    }
}
