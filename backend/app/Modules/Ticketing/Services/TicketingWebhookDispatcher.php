<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TicketingWebhookDispatcher
{
    public function __construct(
        private readonly TicketingSettingsService $settings,
    ) {}

    public function dispatchIfEnabled(TicketingTicket $ticket, string $event, string $summary): void
    {
        if (! $this->shouldSend($event)) {
            return;
        }

        $url = trim((string) $this->settings->getString(TicketingSettingsService::TEAMS_WEBHOOK_URL, ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $ticket->loadMissing(['requester:id,name', 'assignee:id,name']);
        $resolver = app(TenantAppUrlResolver::class);
        $path = TicketingNotificationCategory::hrefFor((string) $ticket->id);
        $ticketUrl = $resolver->urlForCurrentTenant($path);

        $themeColor = match ($event) {
            'sla_escalation' => 'DC2626',
            'sla_reminder' => 'D97706',
            default => '2563EB',
        };

        $lines = [
            '**'.__('Ticket').':** '.$ticket->displayNumber(),
            '**'.__('Title').':** '.$ticket->title,
            '**'.__('Priority').':** '.$ticket->priority,
            '**'.__('Status').':** '.$ticket->status,
        ];

        if ($ticket->requester !== null) {
            $lines[] = '**'.__('Requester').':** '.$ticket->requester->name;
        }

        if ($ticket->assignee !== null) {
            $lines[] = '**'.__('Assignee').':** '.$ticket->assignee->name;
        }

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => $summary,
            'themeColor' => $themeColor,
            'title' => $summary,
            'text' => implode("\n\n", $lines),
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => __('Open ticket'),
                    'targets' => [
                        ['os' => 'default', 'uri' => $ticketUrl],
                    ],
                ],
            ],
        ];

        try {
            Http::timeout(10)->post($url, $payload)->throw();
        } catch (\Throwable $e) {
            Log::warning('ticketing.webhook_failed', [
                'event' => $event,
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function shouldSend(string $event): bool
    {
        return match ($event) {
            'created' => $this->settings->getBool(TicketingSettingsService::NOTIFY_TEAMS_ON_CREATE, false),
            'sla_reminder' => $this->settings->getBool(TicketingSettingsService::NOTIFY_TEAMS_ON_SLA_REMINDER, true),
            'sla_escalation' => $this->settings->getBool(TicketingSettingsService::NOTIFY_TEAMS_ON_SLA_ESCALATION, true),
            default => false,
        };
    }
}
