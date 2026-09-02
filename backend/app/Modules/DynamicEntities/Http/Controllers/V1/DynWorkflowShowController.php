<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynWorkflow;
use App\Modules\DynamicEntities\Services\DynWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynWorkflowShowController extends AbstractApiController
{
    public function __invoke(Request $request, DynWorkflow $workflow, DynWorkflowService $service): JsonResponse
    {
        abort_unless($request->user()?->can('workflows:manage'), 403);

        return $this->ok($service->show($workflow));
    }
}
