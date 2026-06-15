<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EApprovalSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly ?string $tenantId;

    public function __construct(
        private readonly EApprovalSubmission $submission,
        private readonly string $event,
        private readonly ?string $actorName = null,
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

            $this->submission->loadMissing(['form:id,name']);

            $documentNo = $this->submission->document_no;
            $formName = $this->submission->form?->name ?? __('Form');
            $submitter = $this->submission->mailSubmitterContext();
            $isExternal = $this->submission->isExternalSubmission();

            $subject = match ($this->event) {
                'approval_assigned' => "{$prefix} Approval required — {$documentNo}",
                'external_received' => "{$prefix} External submission received — {$documentNo}",
                'submitted' => "{$prefix} Request submitted — {$documentNo}",
                'approved' => "{$prefix} Request approved — {$documentNo}",
                'rejected' => "{$prefix} Request rejected — {$documentNo}",
                'returned' => "{$prefix} Revision requested — {$documentNo}",
                'awaiting_dcf' => "{$prefix} Document control required — {$documentNo}",
                'sla_reminder' => "{$prefix} Approval reminder — {$documentNo}",
                'sla_escalation' => "{$prefix} Approval escalation — {$documentNo}",
                default => "{$prefix} E-Approval update — {$documentNo}",
            };

            $actionPath = EApprovalNotificationCategory::hrefFor(
                $this->event,
                (string) $this->submission->id,
            );

            $actionLabel = str_contains($actionPath, '/approvals')
                ? 'Open approval inbox'
                : 'Open submission';

            $message = (new MailMessage())
                ->mailer((string) config('toweros.notifications_mail_mailer', config('mail.default')))
                ->subject($subject)
                ->greeting("{$brand} — ".__('E-Approval'))
                ->line(__('Document: **:document**', ['document' => $documentNo]))
                ->line(__('Form: :form', ['form' => $formName]));

            if ($isExternal) {
                $message->line(__('Submitted by: :name', ['name' => $submitter['name']]));
                if ($submitter['email'] !== null) {
                    $message->line(__('Contact email: :email', ['email' => $submitter['email']]));
                }
                if ($submitter['internal_sponsor'] !== null) {
                    $message->line(__('Internal sponsor: :name', ['name' => $submitter['internal_sponsor']]));
                }
            } else {
                $message->line(__('Requestor: :name', ['name' => $submitter['name']]));
            }

            if ($this->event === 'approval_assigned') {
                $message->line(__('You have a pending approval step. Please review and decide when ready.'));
            } elseif ($this->event === 'external_received') {
                $message->line(__('A public form link was used to submit this request. It is now in your approval workflow.'));
            } elseif ($this->event === 'submitted') {
                $message->line(__('Your request was submitted successfully and is pending approval. You will receive another email when the workflow completes.'));
            } elseif ($this->actorName !== null) {
                $message->line(__('By: :name', ['name' => $this->actorName]));
            }

            return $message
                ->action($actionLabel, $resolver->urlForCurrentTenant($actionPath))
                ->salutation(__('Regards,')."\n".$brand);
        });
    }
}
