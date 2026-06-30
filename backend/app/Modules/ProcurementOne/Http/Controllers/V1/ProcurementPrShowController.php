<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementDocumentScopeService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $pr, ProcurementPrRegistryService $registry, ProcurementDocumentScopeService $scope): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:view'), 403);

        /** @var TenantUser $user */
        $user = $request->user();

        $model = $registry->find($pr);
        abort_if($model === null, 404);

        $scope->assertCanView($user, (string) $model->requestor_id);

        return $this->ok($registry->toDetailPayload($model));
    }
}
