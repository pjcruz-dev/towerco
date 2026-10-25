<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class EApprovalApprovedFilesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $submissionId,
        private readonly ?string $tenantId = null,
    ) {
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
        $tenantId = $this->tenantId ?? (tenant()?->getTenantKey());

        return app(TenantAppUrlResolver::class)->runForTenant($tenantId, function (): MailMessage {
            $resolver = app(TenantAppUrlResolver::class);
            $submission = EApprovalSubmission::query()->with(['form:id,name', 'attachments'])->find($this->submissionId);
            $documentNo = (string) ($submission?->document_no ?? '');
            $formName = $submission?->form?->name ?? __('Form');
            $brand = $resolver->mailBrandLabel();
            $prefix = $resolver->subjectPrefix();

            $message = (new MailMessage)
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->from((string) config('mail.from.address'), $brand)
                ->subject("{$prefix} Approved files — {$documentNo}")
                ->greeting("{$brand} — ".__('E-Forms'))
                ->line(__('Document: **:document**', ['document' => $documentNo]))
                ->line(__('Form: :form', ['form' => $formName]))
                ->line(__('This request is fully approved. The current files are attached.'));

            if ($submission !== null) {
                $disk = (string) config('toweros.tenant_files.disk', 'local');
                foreach ($submission->attachments as $attachment) {
                    if (! $attachment instanceof EApprovalAttachment || $attachment->isSuperseded()) {
                        continue;
                    }
                    $path = (string) $attachment->file_path;
                    if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                        continue;
                    }
                    $message->attachData(Storage::disk($disk)->get($path), (string) $attachment->file_name);
                }
            }

            return $message;
        });
    }
}
