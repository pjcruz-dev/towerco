<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\Identity\Models\TenantUser;

/**
 * Resolves Reports/export row visibility: non-admins only see submissions they created;
 * audit/forms managers may export all (or force mine via viewer_scope=mine).
 */
final class EApprovalExportViewerScope
{
    /**
     * @return array{viewer: TenantUser, can_view_all: bool, created_only: bool}
     */
    public static function forUser(TenantUser $user, ?string $viewerScope = null): array
    {
        $scope = strtolower(trim((string) $viewerScope));
        if ($scope === '') {
            $scope = 'all';
        }

        $canAudit = $user->can('e_approval:audit:view') || $user->can('e_approval:forms:manage');
        $wantAll = $scope !== 'mine';

        return [
            'viewer' => $user,
            'can_view_all' => $canAudit && $wantAll,
            'created_only' => true,
        ];
    }

    public static function userCanExport(TenantUser $user): bool
    {
        return $user->can('e_approval:audit:view')
            || $user->can('e_approval:submissions:view');
    }
}
