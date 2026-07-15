<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormWorkspaceShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceService $workspaces,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        return $this->ok($workspaces->buildDashboard($slug, $request->user()));
    }
}
