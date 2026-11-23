<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentObsoleteBoardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
        ControlledDocumentRoleFieldAccess $roleAccess,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $access->canAccessRegistry($user), 403);
        abort_unless($roleAccess->allows($user, 'view'), 403);
        abort_unless(! $roleAccess->navTargetHidden($user, '/documents/controlled/obsolete'), 403);

        $board = $registry->obsoleteBoard($user);
        foreach (['obsolete', 'approved', 'pending'] as $bucket) {
            $rows = is_array($board[$bucket] ?? null) ? $board[$bucket] : [];
            $board[$bucket] = array_map(
                fn (array $row): array => $roleAccess->redact($user, $row),
                $rows,
            );
        }

        return $this->ok($board);
    }
}
