<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceFormResolverService;
use App\Modules\ProcurementOne\Services\ProcurementFormSchemaPresenter;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementApInvoiceFormSchemaController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementApInvoiceFormResolverService $formResolver,
        ProcurementFormSchemaPresenter $presenter,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());

        return $this->ok($presenter->present($formResolver->resolvePublishedForm()));
    }
}
