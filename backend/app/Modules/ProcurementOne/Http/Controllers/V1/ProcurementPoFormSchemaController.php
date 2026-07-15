<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementFormSchemaPresenter;
use App\Modules\ProcurementOne\Services\ProcurementPoFormResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoFormSchemaController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementPoFormResolverService $formResolver,
        ProcurementFormSchemaPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);

        return $this->ok($presenter->present($formResolver->resolvePublishedForm()));
    }
}
