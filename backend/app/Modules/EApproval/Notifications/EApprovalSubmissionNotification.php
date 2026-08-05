<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Notifications;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\EApproval\Support\EApprovalRevisionRouting;
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
        private readonly ?string $detail = null,
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

            $this->submission->loadMissing(['form:id,name,metadata_json']);

            $documentNo = $this->submission->document_no;
            $formName = $this->submission->form?->name ?? __('Form');
            $submitter = $this->submission->mailSubmitterContext();
            $isExternal = $this->submission->isExternalSubmission();

            $subject = match ($this->event) {
                'approval_assigned' => "{$prefix} Approval required — {$documentNo}",
                'approval_assigned_revised' => "{$prefix} Revised approval required — {$documentNo}",
                'external_received' => "{$prefix} External submission received — {$documentNo}",
                'submitted' => "{$prefix} Request submitted — {$documentNo}",
                'resubmitted_resume' => "{$prefix} Request resubmitted — resumed — {$documentNo}",
                'resubmitted_restart' => "{$prefix} Request resubmitted — full restart — {$documentNo}",
                'approved' => "{$prefix} Request approved — {$documentNo}",
                'rejected' => "{$prefix} Request rejected — {$documentNo}",
                'returned' => "{$prefix} Revision requested — {$documentNo}",
                'awaiting_dcf' => "{$prefix} Document control required — {$documentNo}",
                'sla_reminder' => "{$prefix} Approval reminder — {$documentNo}",
                'sla_escalation' => "{$prefix} Approval escalation — {$documentNo}",
                'manual_follow_up' => "{$prefix} Follow-up reminder — {$documentNo}",
                'cancelled' => "{$prefix} Request cancelled — {$documentNo}",
                'approval_no_longer_needed' => "{$prefix} Approval no longer needed — {$documentNo}",
                'approval_rerouted' => "{$prefix} Approval reassigned — {$documentNo}",
                'workflow_steps_skipped' => "{$prefix} Workflow path update — {$documentNo}",
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
            } elseif ($this->event === 'approval_assigned_revised') {
                $message->line(__('This request was revised and needs your approval again. Please review the latest version and decide when ready.'));
            } elseif ($this->event === 'external_received') {
                $message->line(__('A public form link was used to submit this request. It is now in your approval workflow.'));
            } elseif ($this->event === 'submitted') {
                $message->line(__('Your request was submitted successfully and is pending approval. You will receive another email when the workflow completes.'));
            } elseif ($this->event === 'resubmitted_resume') {
                $step = max(1, (int) ($this->submission->current_step ?: 1));
                $message->line(__('Your revised request was resubmitted. Approval resumed at step :step — prior steps stay approved.', ['step' => $step]));
            } elseif ($this->event === 'resubmitted_restart') {
                $message->line(__('Your revised request was resubmitted. The workflow restarted from step 1 for full re-approval.'));
            } elseif ($this->event === 'returned') {
                $message->line(__('Your request was returned for revision. Please update it and resubmit.'));
                if ($this->actorName !== null) {
                    $message->line(__('Returned by: :name', ['name' => $this->actorName]));
                }
                foreach ($this->revisionOutlookLines() as $line) {
                    $message->line($line);
                }
            } elseif ($this->event === 'manual_follow_up') {
                $message->line(__('The requestor sent a follow-up reminder. Please review this approval when you can.'));
                if ($this->actorName !== null) {
                    $message->line(__('From: :name', ['name' => $this->actorName]));
                }
            } elseif ($this->event === 'cancelled') {
                $message->line(__('This request was cancelled. No further approval action is required.'));
                if ($this->actorName !== null) {
                    $message->line(__('Cancelled by: :name', ['name' => $this->actorName]));
                }
            } elseif ($this->event === 'approval_no_longer_needed') {
                $message->line(__('Your pending approval is no longer needed. Another approver already completed this parallel step.'));
            } elseif ($this->event === 'approval_rerouted') {
                $message->line(__('Your pending approval was reassigned to another approver. No further action is required from you.'));
                if ($this->actorName !== null) {
                    $message->line(__('Reassigned by: :name', ['name' => $this->actorName]));
                }
                if ($this->detail !== null && trim($this->detail) !== '') {
                    $message->line(__('Reason: :reason', ['reason' => $this->detail]));
                }
            } elseif ($this->event === 'workflow_steps_skipped') {
                $message->line(__('Some approval steps were skipped because their conditions were not met (exclusive path).'));
                if ($this->detail !== null && trim($this->detail) !== '') {
                    $message->line($this->detail);
                }
            } elseif ($this->actorName !== null) {
                $message->line(__('By: :name', ['name' => $this->actorName]));
            }

            return $message
                ->action($actionLabel, $resolver->urlForCurrentTenant($actionPath))
                ->salutation(__('Regards,')."\n".$brand);
        });
    }

    /**
     * @return list<string>
     */
    private function revisionOutlookLines(): array
    {
        if ((bool) $this->submission->force_full_restart) {
            return [
                __('After you resubmit, the workflow will restart from step 1 (the approver required full re-approval).'),
            ];
        }

        $config = EApprovalRevisionRouting::fromFormMetadata(
            is_array($this->submission->form?->metadata_json) ? $this->submission->form->metadata_json : [],
        );
        $returnStep = (int) ($this->submission->returned_from_step ?: 0);

        if ($config['routing'] === EApprovalRevisionRouting::RESUME_RETURNING_STEP && $returnStep > 0) {
            $lines = [
                __('After you resubmit, approval will typically resume at step :step.', ['step' => $returnStep]),
            ];
            if ($config['material_fields'] !== []) {
                $lines[] = __('If material fields change, the workflow may restart from step 1 instead.');
            }

            return $lines;
        }

        return [
            __('After you resubmit, the workflow will restart from step 1.'),
        ];
    }
}
