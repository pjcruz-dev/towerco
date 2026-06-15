<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketingMailTestNotification extends Notification
{
    public function __construct(
        private readonly string $tenantLabel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailer = (string) config('toweros.notifications_mail_mailer', config('mail.default'));

        return (new MailMessage())
            ->mailer($mailer)
            ->subject(__('Ticketing test email — :tenant', ['tenant' => $this->tenantLabel]))
            ->line(__('This is a test message from the Ticketing module.'))
            ->line(__('Mailer: :mailer', ['mailer' => $mailer]));
    }
}
