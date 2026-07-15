<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentRegisterAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ControlledDocumentRegisterAccessShowController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentRegisterAccessService $access): JsonResponse
    {
        abort_unless($request->user()?->can('documents:controlled:manage'), 403);

        return $this->ok($access->payload());
    }
}
