<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEmailTemplate;
use App\Modules\DynamicEntities\Services\DynEmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEmailTemplateShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynEmailTemplate $template,
        DynEmailTemplateService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('email_templates:manage'), 403);

        return $this->ok($service->show($template));
    }
}
