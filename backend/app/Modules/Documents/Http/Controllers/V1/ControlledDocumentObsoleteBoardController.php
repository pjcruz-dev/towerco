<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentObsoleteBoardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $access->canAccessRegistry($user), 403);

        return $this->ok($registry->obsoleteBoard($user));
    }
}
