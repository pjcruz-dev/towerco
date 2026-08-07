<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use App\Modules\Notifications\Support\TeamsWebhookCardFactory;
use App\Modules\Notifications\Support\TeamsWebhookHttpPoster;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;

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

        $accentColor = match ($event) {
            'sla_escalation' => 'Attention',
            'sla_reminder' => 'Warning',
            default => 'Accent',
        };

        $facts = [
            ['title' => __('Ticket'), 'value' => $ticket->displayNumber()],
            ['title' => __('Title'), 'value' => (string) $ticket->title],
            ['title' => __('Priority'), 'value' => (string) $ticket->priority],
            ['title' => __('Status'), 'value' => (string) $ticket->status],
        ];

        if ($ticket->requester !== null) {
            $facts[] = ['title' => __('Requester'), 'value' => (string) $ticket->requester->name];
        }

        if ($ticket->assignee !== null) {
            $facts[] = ['title' => __('Assignee'), 'value' => (string) $ticket->assignee->name];
        }

        $payload = TeamsWebhookCardFactory::build(
            title: $summary,
            facts: $facts,
            accentColor: $accentColor,
            actionUrl: $ticketUrl,
            actionLabel: __('Open ticket'),
        );

        TeamsWebhookHttpPoster::postOrLog(
            $url,
            $payload,
            10,
            'ticketing.webhook_failed',
            [
                'event' => $event,
                'ticket_id' => $ticket->id,
            ],
        );
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
