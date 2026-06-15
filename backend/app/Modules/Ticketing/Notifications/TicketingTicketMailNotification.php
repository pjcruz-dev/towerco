<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Notifications;

use App\Models\TicketingTicket;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use App\Modules\Ticketing\Support\TicketingNotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketingTicketMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly ?string $tenantId;

    public function __construct(
        private readonly TicketingTicket $ticket,
        private readonly string $event,
        private readonly ?string $actorName = null,
        private readonly ?string $resolutionComment = null,
        ?string $tenantId = null,
    ) {
        $this->tenantId = $tenantId ?? (tenant()?->getTenantKey());
        $this->onQueue(config('toweros.queues.notifications', 'toweros-notifications'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(TenantAppUrlResolver::class)->runForTenant($this->tenantId, function (): MailMessage {
            $resolver = app(TenantAppUrlResolver::class);
            $prefix = $resolver->subjectPrefix();
            $brand = $resolver->mailBrandLabel();

            $this->ticket->loadMissing(['requester:id,name,email']);

            $number = $this->ticket->displayNumber();
            $title = $this->ticket->title;
            $path = TicketingNotificationCategory::hrefFor((string) $this->ticket->id);

            $subject = match ($this->event) {
                'created' => "{$prefix} New ticket {$number}",
                'reopened' => "{$prefix} Ticket reopened {$number}",
                'resolved' => "{$prefix} Ticket resolved {$number}",
                'assigned' => "{$prefix} Ticket assigned to you {$number}",
                default => "{$prefix} Ticket update {$number}",
            };

            $message = (new MailMessage())
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->subject($subject)
                ->greeting("{$brand} — ".__('Ticketing'))
                ->line(__('Ticket: **:number**', ['number' => $number]))
                ->line(__('Title: :title', ['title' => $title]));

            if ($this->ticket->requester !== null) {
                $message->line(__('Requester: :name', ['name' => $this->ticket->requester->name]));
            }

            if ($this->event === 'created') {
                $message->line(__('A new support ticket was submitted and is awaiting IT triage.'));
            } elseif ($this->event === 'reopened') {
                $message->line(__('The requester reopened this ticket. Please review and respond.'));
                if ($this->actorName !== null) {
                    $message->line(__('By: :name', ['name' => $this->actorName]));
                }
            } elseif ($this->event === 'resolved') {
                $message->line(__('Your ticket has been marked resolved.'));
                if ($this->resolutionComment !== null && trim($this->resolutionComment) !== '') {
                    $message->line(__('Resolution note: :note', ['note' => $this->resolutionComment]));
                }
                if ($this->actorName !== null) {
                    $message->line(__('Resolved by: :name', ['name' => $this->actorName]));
                }
            } elseif ($this->event === 'assigned') {
                $message->line(__('You have been assigned this ticket.'));
                if ($this->actorName !== null) {
                    $message->line(__('Assigned by: :name', ['name' => $this->actorName]));
                }
            }

            return $message
                ->action(__('Open ticket'), $resolver->urlForCurrentTenant($path))
                ->salutation(__('Regards,')."\n".$brand);
        });
    }
}
