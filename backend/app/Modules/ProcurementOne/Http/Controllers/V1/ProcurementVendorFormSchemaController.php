<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementFormSchemaPresenter;
use App\Modules\ProcurementOne\Services\ProcurementVendorFormResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorFormSchemaController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementVendorFormResolverService $formResolver,
        ProcurementFormSchemaPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:vendors:view'), 403);

        return $this->ok($presenter->present($formResolver->resolvePublishedForm()));
    }
}
