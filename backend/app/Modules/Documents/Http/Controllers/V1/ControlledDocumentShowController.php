<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canViewDocument($user, $controlledDocument), 403);

        return $this->ok($registry->show($controlledDocument));
    }
}
