<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Synchronous test message — verifies tenant mail transport (Microsoft 365 SMTP / SES), not legacy formbuilder mail.
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
        $resolver = app(TenantAppUrlResolver::class);
        $brand = $resolver->mailBrandLabel();
        $prefix = $resolver->subjectPrefix();

        return (new MailMessage())
            ->mailer($mailer)
            ->from((string) config('mail.from.address'), $brand)
            ->subject("{$prefix} E-Approval test email")
            ->greeting("{$brand} — ".__('E-Approval mail test'))
            ->line(__('This message confirms :brand can send E-Approval notifications using the configured mail transport.', [
                'brand' => $brand,
            ]))
            ->line(__('Organization: :tenant', ['tenant' => $this->tenantLabel]))
            ->line(__('Mailer: :mailer', ['mailer' => $mailer]))
            ->line(__('If you received this email, approvers and requestors will receive workflow notifications when submissions move through approval.'))
            ->salutation(__('Regards,')."\n".$brand);
    }
}
