<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Notifications;

use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProcurementRfqVendorMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly ?string $tenantId;

    public function __construct(
        private readonly string $subject,
        private readonly string $body,
        private readonly ?string $actionUrl,
        private readonly string $actionLabel,
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
            $brand = app(TenantAppUrlResolver::class)->mailBrandLabel();
            $lines = preg_split('/\r\n|\r|\n/', $this->body) ?: [$this->body];

            $message = (new MailMessage)
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->subject($this->subject)
                ->greeting("{$brand} — ".__('Procurement'));

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $message->line($trimmed);
                }
            }

            if ($this->actionUrl !== null && $this->actionUrl !== '') {
                $message->action($this->actionLabel, $this->actionUrl);
            }

            return $message->salutation(__('Regards,')."\n".$brand);
        });
    }
}
