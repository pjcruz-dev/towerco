<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Models\TenantNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limits the account inbox to the module the person currently has open.
 */
final class TenantNotificationShell
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return ['edcs', 'e_approval', 'ticketing', 'asset_management'];
    }

    /**
     * @param  Builder<TenantNotification>  $query
     */
    public static function apply(Builder $query, string $shell): void
    {
        if ($shell === 'ticketing') {
            $query->where('module', TenantNotificationModule::TICKETING);

            return;
        }

        if ($shell === 'asset_management') {
            $query->where('module', TenantNotificationModule::ASSETS);

            return;
        }

        if ($shell === 'edcs') {
            $query->where(static function (Builder $inner): void {
                $inner->where('module', TenantNotificationModule::DOCUMENTS)
                    ->orWhere(static function (Builder $approval): void {
                        $approval->where('module', TenantNotificationModule::E_APPROVAL)
                            ->where('href', 'like', '/documents%');
                    });
            });

            return;
        }

        if ($shell === 'e_approval') {
            $query->where('module', TenantNotificationModule::E_APPROVAL)
                ->where(static function (Builder $inner): void {
                    $inner->whereNull('href')
                        ->orWhere('href', 'not like', '/documents%');
                });
        }
    }
}
