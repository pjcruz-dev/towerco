<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentPresignedUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentUploadCapabilitiesShowController extends AbstractApiController
{
    public function __invoke(Request $request, DocumentPresignedUploadService $presigned): JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        return $this->ok($presigned->capabilities());
    }
}
