<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TenantNotificationCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $userId,
        public readonly string $notificationId,
        public readonly string $module,
        public readonly string $category,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.user.'.$this->userId.'.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TenantNotificationCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'module' => $this->module,
            'category' => $this->category,
        ];
    }
}
