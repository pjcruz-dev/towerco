<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Events\TenantNotificationCreated;
use App\Modules\Notifications\Models\TenantNotification;

final class TenantNotificationBroadcaster
{
    public function created(TenantNotification $notification): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            return;
        }

        event(new TenantNotificationCreated(
            tenantId: $tenantId,
            userId: (string) $notification->user_id,
            notificationId: (string) $notification->id,
            module: (string) $notification->module,
            category: (string) $notification->category,
        ));
    }

    private function shouldBroadcast(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        return $driver !== 'null' && $driver !== '';
    }

    private function tenantId(): ?string
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenantId = tenant('id');

        return $tenantId !== null ? (string) $tenantId : null;
    }
}
