<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceAuditIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceAuditEntityIndexController extends AbstractApiController
{
    public function __invoke(Request $request, WorkspaceAuditIndexService $audit): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        abort_unless($user?->can('workspace:audit:view'), 403);

        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:50'],
            'entity_id' => ['required', 'string', 'max:64'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = $audit->forEntity(
            $validated['entity_type'],
            $validated['entity_id'],
            (int) ($validated['limit'] ?? 20),
        );

        return $this->ok($rows);
    }
}
