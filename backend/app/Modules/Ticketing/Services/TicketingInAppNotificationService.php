<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;

final class TicketingInAppNotificationService
{
    public function __construct(
        private readonly TenantNotificationService $tenantNotifications,
    ) {}

    public function notifyUser(
        string $userId,
        string $type,
        TicketingTicket $ticket,
        string $message,
        ?TenantUser $actor = null,
        ?string $bodyPreview = null,
    ): void {
        $this->tenantNotifications->notify(
            userId: $userId,
            module: TenantNotificationModule::TICKETING,
            type: $type,
            message: $message,
            subjectType: 'ticket',
            subjectId: (string) $ticket->id,
            contextPrimary: $ticket->displayNumber(),
            contextSecondary: $ticket->title,
            bodyPreview: $bodyPreview,
            href: TicketingNotificationCategory::hrefFor((string) $ticket->id),
            actor: $actor,
            category: TicketingNotificationCategory::forType($type),
        );
    }

    /**
     * @param  list<TenantUser>  $users
     */
    public function notifyUsers(
        array $users,
        string $type,
        TicketingTicket $ticket,
        string $message,
        ?TenantUser $actor = null,
        ?string $bodyPreview = null,
    ): void {
        $actorId = $actor !== null ? (string) $actor->id : null;

        foreach ($users as $user) {
            if ($actorId !== null && (string) $user->id === $actorId) {
                continue;
            }

            $this->notifyUser((string) $user->id, $type, $ticket, $message, $actor, $bodyPreview);
        }
    }
}
