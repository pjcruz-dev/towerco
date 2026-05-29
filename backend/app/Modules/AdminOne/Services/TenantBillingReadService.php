<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;

class TenantBillingReadService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tenantKey = (string) tenant('id');
        /** @var Tenant|null $central */
        $central = Tenant::query()->find($tenantKey);

        $seatUsed = TenantUser::query()->count();
        $seatLimit = (int) ($central?->seat_limit ?? 25);

        return [
            'tenant_id' => $tenantKey,
            'plan_tier' => (string) ($central?->plan_tier ?? 'starter'),
            'subscription_status' => (string) ($central?->subscription_status ?? 'active'),
            'seat_limit' => $seatLimit,
            'seat_used' => $seatUsed,
            'seats_available' => max(0, $seatLimit - $seatUsed),
        ];
    }
}
