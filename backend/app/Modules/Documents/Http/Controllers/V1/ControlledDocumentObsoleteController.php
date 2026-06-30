<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentObsoleteController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        ControlledDocumentRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:controlled:manage'), 403);

        $updated = $registry->markObsolete($controlledDocument, $request->user());

        return $this->ok([
            'id' => (string) $updated->id,
            'status' => $updated->status,
        ]);
    }
}
