<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalExternalMailEvent;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EApprovalExternalSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly ?string $tenantId;

    /**
     * @param  list<array{file_name: string, url: string}>  $packageLinks
     */
    public function __construct(
        private readonly EApprovalSubmission $submission,
        private readonly string $event,
        private readonly ?string $actorName = null,
        private readonly ?string $detail = null,
        private readonly ?string $reviseUrl = null,
        private readonly array $packageLinks = [],
        private readonly ?string $packageNote = null,
        ?string $tenantId = null,
    ) {
        $this->tenantId = $tenantId ?? (tenant()?->getTenantKey());
        $this->onQueue(config('toweros.queues.notifications', 'toweros-notifications'));
    }

    public function eventName(): string
    {
        return $this->event;
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

            $this->submission->loadMissing(['form:id,name']);

            $documentNo = (string) $this->submission->document_no;
            $formName = $this->submission->form?->name ?? __('Form');
            $submitter = $this->submission->mailSubmitterContext();

            $subject = match ($this->event) {
                EApprovalExternalMailEvent::RECEIVED => "{$prefix} Submission received — {$documentNo}",
                EApprovalExternalMailEvent::APPROVED => "{$prefix} Submission approved — {$documentNo}",
                EApprovalExternalMailEvent::REJECTED => "{$prefix} Submission rejected — {$documentNo}",
                EApprovalExternalMailEvent::RETURNED => "{$prefix} Revision requested — {$documentNo}",
                default => "{$prefix} Submission update — {$documentNo}",
            };

            $message = (new MailMessage())
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->from((string) config('mail.from.address'), $brand)
                ->subject($subject)
                ->greeting("{$brand}")
                ->line(__('Hello :name,', ['name' => $submitter['name']]))
                ->line(__('Document: **:document**', ['document' => $documentNo]))
                ->line(__('Form: :form', ['form' => $formName]));

            if ($this->event === EApprovalExternalMailEvent::RECEIVED) {
                $message->line(__('We received your submission. It is now in review.'));
            } elseif ($this->event === EApprovalExternalMailEvent::APPROVED) {
                $message->line(__('Your submission was approved.'));
                if ($this->actorName !== null && $this->actorName !== '') {
                    $message->line(__('Approved by: :name', ['name' => $this->actorName]));
                }
                if ($this->packageNote !== null && $this->packageNote !== '') {
                    $message->line($this->packageNote);
                }
                foreach ($this->packageLinks as $link) {
                    $fileLabel = str_replace(['[', ']', '(', ')'], '', (string) $link['file_name']);
                    $url = (string) $link['url'];
                    // Markdown link → clickable <a> in HTML mail theme.
                    $message->line("- [{$fileLabel}]({$url})");
                }
            } elseif ($this->event === EApprovalExternalMailEvent::REJECTED) {
                $message->line(__('Your submission was rejected.'));
                if ($this->actorName !== null && $this->actorName !== '') {
                    $message->line(__('Reviewed by: :name', ['name' => $this->actorName]));
                }
                if ($this->detail !== null && trim($this->detail) !== '') {
                    $message->line(__('Reason: :reason', ['reason' => trim($this->detail)]));
                }
            } elseif ($this->event === EApprovalExternalMailEvent::RETURNED) {
                $message->line(__('Your submission needs revision before it can proceed.'));
                if ($this->actorName !== null && $this->actorName !== '') {
                    $message->line(__('Requested by: :name', ['name' => $this->actorName]));
                }
                if ($this->detail !== null && trim($this->detail) !== '') {
                    $message->line(__('Revision notes: :notes', ['notes' => trim($this->detail)]));
                }
                if ($this->reviseUrl !== null && $this->reviseUrl !== '') {
                    $message->action(__('Revise and resubmit'), $this->reviseUrl);
                    $message->line(__('This revise link expires. Contact the organization if you need a new link.'));
                }
            }

            $message->line(__('This message was sent because you submitted a form. Do not reply to this email.'));

            return $message;
        });
    }
}
