<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Notifications;

use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RolloutGateApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RolloutGateApprovalRequest $request,
        private readonly string $event,
        private readonly ?string $actorName = null,
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
        $program = $this->request->rolloutProgram;
        $phase = $this->request->timelinePhase;
        $baseUrl = rtrim((string) config('toweros.tenant_app_url', env('TOWEROS_TENANT_APP_URL', 'http://localhost:3001')), '/');
        $rolloutUrl = $program !== null
            ? "{$baseUrl}/project-one/rollouts/{$program->id}?tab=timeline"
            : "{$baseUrl}/project-one/gate-approvals";
        $inboxUrl = "{$baseUrl}/project-one/gate-approvals";

        $subject = match ($this->event) {
            'submitted' => "[TowerOS] Gate approval requested — {$program?->rollout_ref}",
            'step_approved' => "[TowerOS] Gate approval advanced — {$program?->rollout_ref}",
            'approved' => "[TowerOS] Gate approved — {$program?->rollout_ref}",
            'rejected' => "[TowerOS] Gate approval rejected — {$program?->rollout_ref}",
            'escalated' => "[TowerOS] Gate approval escalation — {$program?->rollout_ref}",
            default => "[TowerOS] Rollout gate update — {$program?->rollout_ref}",
        };

        $message = (new MailMessage())
            ->mailer((string) config('toweros.gate_approval_mail_mailer', config('mail.default')))
            ->subject($subject)
            ->greeting('Rollout gate approval')
            ->line("Rollout: **{$program?->rollout_ref}**")
            ->line("Phase: **{$phase?->label}**")
            ->line("Gate: **{$this->request->gate_label}**");

        if ($this->actorName !== null) {
            $message->line("Action by: {$this->actorName}");
        }

        if ($this->event === 'rejected' && $this->request->rejection_notes) {
            $message->line("Notes: {$this->request->rejection_notes}");
        }

        if ($this->request->request_notes && in_array($this->event, ['submitted', 'step_approved'], true)) {
            $message->line("Request notes: {$this->request->request_notes}");
        }

        if ($this->request->isOpen()) {
            $role = $this->request->currentApproverRole();
            if ($role !== null) {
                $message->line("Pending approver role: **{$role}**");
            }
        }

        if ($this->event === 'escalated') {
            $message->line('This step has exceeded the configured escalation threshold. Please review promptly.');
        }

        return $message
            ->action('Open rollout timeline', $rolloutUrl)
            ->line('Review pending items in the gate approvals inbox.')
            ->action('Gate approvals inbox', $inboxUrl);
    }
}
