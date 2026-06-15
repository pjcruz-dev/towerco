<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Synchronous test message — verifies TowerOS mail transport (Microsoft 365 SMTP / SES), not legacy formbuilder mail.
 */
final class EApprovalMailTestNotification extends Notification
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
            ->subject('[TowerOS] E-Approval test email')
            ->greeting(__('E-Approval mail test'))
            ->line(__('This message confirms TowerOS can send E-Approval notifications using the configured mail transport.'))
            ->line(__('Tenant: :tenant', ['tenant' => $this->tenantLabel]))
            ->line(__('Mailer: :mailer', ['mailer' => $mailer]))
            ->line(__('If you received this email, approvers and requestors will receive workflow notifications when submissions move through approval.'));
    }
}
