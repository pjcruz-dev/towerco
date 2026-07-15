<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementFormSchemaPresenter;
use App\Modules\ProcurementOne\Services\ProcurementPrFormResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrFormSchemaController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementPrFormResolverService $formResolver,
        ProcurementFormSchemaPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);

        return $this->ok($presenter->present($formResolver->resolvePublishedForm()));
    }
}
