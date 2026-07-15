<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Notifications;

use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProcurementScheduledExportMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly ?string $tenantId;

    public function __construct(
        private readonly string $periodLabel,
        private readonly string $filename,
        private readonly string $binary,
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

            return (new MailMessage)
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->subject(__('Procurement export — :period', ['period' => $this->periodLabel]))
                ->greeting("{$brand} — ".__('Procurement reporting'))
                ->line(__('Attached is the monthly procurement Excel pack for :period.', ['period' => $this->periodLabel]))
                ->line(__('Sheets: Vendors, PRs, PR lines, POs, and PO lines.'))
                ->attachData(
                    $this->binary,
                    $this->filename,
                    ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                )
                ->action(__('Open procurement dashboard'), app(TenantAppUrlResolver::class)->urlForCurrentTenant('/procurement'))
                ->salutation(__('Regards,')."\n".$brand);
        });
    }
}
