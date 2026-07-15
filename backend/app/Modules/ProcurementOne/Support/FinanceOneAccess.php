<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Finance-One RBAC gates (AP, payments, budget, contracts, reports).
 */
final class FinanceOneAccess
{
    public static function authorizeView(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:view'), 403);
    }

    public static function authorizeDocumentsCreate(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:documents:create'), 403);
    }

    public static function authorizeDocumentsManage(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:documents:manage'), 403);
    }

    public static function authorizeBudgetManage(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:budget:manage'), 403);
    }

    public static function authorizeContractsManage(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:contracts:manage'), 403);
    }

    public static function authorizePaymentsManage(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:payments:manage'), 403);
    }

    public static function authorizeReportsView(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:reports:view'), 403);
    }

    public static function authorizeSettingsManage(?Authenticatable $user): void
    {
        abort_unless($user?->can('finance_one:settings:manage'), 403);
    }
}
