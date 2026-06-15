<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TicketingNotificationCategory
{
    public static function forType(string $type): string
    {
        return match ($type) {
            'ticket_created', 'ticket_reopened', 'ticket_assigned', 'ticket_sla_reminder', 'ticket_sla_escalation' => 'action',
            default => 'update',
        };
    }

    public static function hrefFor(?string $ticketId): string
    {
        if ($ticketId === null || $ticketId === '') {
            return '/ticketing/tickets';
        }

        return '/ticketing/tickets/'.$ticketId;
    }
}
