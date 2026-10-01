<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynEmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEmailTemplateIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynEmailTemplateService $service): JsonResponse
    {
        abort_unless($request->user()?->can('email_templates:manage'), 403);

        $rows = $service->list();

        return $this->okWithMeta($rows, ['total' => count($rows)]);
    }
}
