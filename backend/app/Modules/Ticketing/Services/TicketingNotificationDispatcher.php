<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\Ticketing\Notifications\TicketingTicketMailNotification;
use Illuminate\Support\Facades\Notification;

final class TicketingNotificationDispatcher
{
    public function __construct(
        private readonly TicketingSettingsService $settings,
        private readonly TicketingInAppNotificationService $inApp,
        private readonly TicketingWebhookDispatcher $webhooks,
    ) {}

    public function dispatchCreated(TicketingTicket $ticket, TenantUser $requester): void
    {
        $ticket->loadMissing('requester:id,name,email');

        if ($this->settings->getBool(TicketingSettingsService::NOTIFY_IT_ON_CREATE, true)) {
            $this->sendItMail($ticket, 'created', $requester->name);
        }

        $message = __('New ticket :number — :title', [
            'number' => $ticket->displayNumber(),
            'title' => $ticket->title,
        ]);
        $this->inApp->notifyUsers(
            $this->resolverUsers(),
            'ticket_created',
            $ticket,
            $message,
            $requester,
            $ticket->description,
        );

        $this->webhooks->dispatchIfEnabled(
            $ticket,
            'created',
            __('New ticket — :number', ['number' => $ticket->displayNumber()]),
        );
    }

    public function dispatchResolved(
        TicketingTicket $ticket,
        TenantUser $resolver,
        string $resolutionComment,
    ): void {
        $ticket->loadMissing('requester:id,name,email');

        if (
            $this->settings->getBool(TicketingSettingsService::NOTIFY_REQUESTOR_ON_RESOLVE, true)
            && $ticket->requester instanceof TenantUser
        ) {
            SafeMailNotificationSender::sendAfterResponse(
                [$ticket->requester],
                new TicketingTicketMailNotification(
                    $ticket,
                    'resolved',
                    $resolver->name,
                    $resolutionComment,
                ),
            );
        }

        if ($ticket->requester instanceof TenantUser) {
            $this->inApp->notifyUser(
                (string) $ticket->requester_id,
                'ticket_resolved',
                $ticket,
                __('Your ticket :number was resolved.', ['number' => $ticket->displayNumber()]),
                $resolver,
                $resolutionComment,
            );
        }
    }

    public function dispatchAssigned(TicketingTicket $ticket, TenantUser $actor, TenantUser $assignee): void
    {
        if ((string) $assignee->id === (string) $actor->id) {
            return;
        }

        $ticket->loadMissing(['requester:id,name,email', 'assignee:id,name,email']);

        if ($this->settings->getBool(TicketingSettingsService::NOTIFY_ASSIGNEE_ON_ASSIGN, true)) {
            SafeMailNotificationSender::sendAfterResponse(
                [$assignee],
                new TicketingTicketMailNotification($ticket, 'assigned', $actor->name),
            );
        }

        $this->inApp->notifyUser(
            (string) $assignee->id,
            'ticket_assigned',
            $ticket,
            __('Ticket :number assigned to you.', ['number' => $ticket->displayNumber()]),
            $actor,
            $ticket->title,
        );
    }

    public function dispatchReopened(TicketingTicket $ticket, TenantUser $requester): void
    {
        $ticket->loadMissing('requester:id,name,email');

        if ($this->settings->getBool(TicketingSettingsService::NOTIFY_IT_ON_REOPEN, true)) {
            $this->sendItMail($ticket, 'reopened', $requester->name);
        }

        $message = __('Ticket reopened :number — :title', [
            'number' => $ticket->displayNumber(),
            'title' => $ticket->title,
        ]);
        $this->inApp->notifyUsers(
            $this->resolverUsers(),
            'ticket_reopened',
            $ticket,
            $message,
            $requester,
        );
    }

    private function sendItMail(TicketingTicket $ticket, string $event, ?string $actorName): void
    {
        $emails = $this->settings->itSupportEmails();
        if ($emails === []) {
            return;
        }

        SafeMailNotificationSender::sendAfterResponse(
            [Notification::route('mail', $emails)],
            new TicketingTicketMailNotification($ticket, $event, $actorName),
        );
    }

    /**
     * @return list<TenantUser>
     */
    private function resolverUsers(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->get()
            ->filter(static fn (TenantUser $user): bool => $user->can('ticketing:tickets:manage'))
            ->values()
            ->all();
    }
}
