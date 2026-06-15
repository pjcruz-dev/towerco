<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\ProjectOneNotificationCategory;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use App\Modules\Rollout\Notifications\RolloutGateApprovalNotification;

final class RolloutGateApprovalNotificationDispatcher
{
    public function __construct(
        private readonly RolloutEmailNotificationPolicyService $emailPolicies,
        private readonly TenantNotificationService $tenantNotifications,
    ) {}

    public function dispatch(
        RolloutGateApprovalRequest $request,
        string $event,
        ?string $actorName = null,
        ?TenantUser $actor = null,
    ): void {
        $request->loadMissing(['rolloutProgram', 'timelinePhase']);
        $config = TenantRolloutPlaybookConfig::query()->first();
        $recipients = $this->emailPolicies->resolveGateEventRecipients($event, $request, $config);

        if ($recipients !== []) {
            SafeMailNotificationSender::sendAfterResponse(
                $recipients,
                new RolloutGateApprovalNotification($request, $event, $actorName),
            );
        }

        $this->dispatchInApp($request, $event, $recipients, $actor);
    }

    /**
     * @param  list<TenantUser>  $emailRecipients
     */
    private function dispatchInApp(
        RolloutGateApprovalRequest $request,
        string $event,
        array $emailRecipients,
        ?TenantUser $actor,
    ): void {
        $program = $request->rolloutProgram;
        $phase = $request->timelinePhase;
        $type = ProjectOneNotificationCategory::typeForGateEvent($event);
        $programId = $program !== null ? (string) $program->id : null;
        $href = ProjectOneNotificationCategory::hrefFor($type, (string) $request->id, $programId);
        $message = $this->inAppMessage($event, $request, $program?->rollout_ref, $phase?->label);

        $recipientIds = [];
        foreach ($emailRecipients as $user) {
            $recipientIds[(string) $user->id] = $user;
        }

        foreach ($recipientIds as $userId => $user) {
            if ($actor !== null && $userId === (string) $actor->id) {
                continue;
            }

            $this->tenantNotifications->notify(
                userId: $userId,
                module: TenantNotificationModule::PROJECT_ONE,
                type: $type,
                message: $message,
                subjectType: 'gate_approval_request',
                subjectId: (string) $request->id,
                contextPrimary: $program?->rollout_ref,
                contextSecondary: $request->gate_label,
                href: $href,
                actor: $actor,
                category: ProjectOneNotificationCategory::forGateEvent($event),
            );
        }
    }

    private function inAppMessage(
        string $event,
        RolloutGateApprovalRequest $request,
        ?string $rolloutRef,
        ?string $phaseLabel,
    ): string {
        $ref = $rolloutRef ?? 'Rollout';
        $gate = $request->gate_label;
        $phase = $phaseLabel ?? 'Phase';

        return match ($event) {
            'submitted' => "{$ref} — gate approval requested for {$gate} ({$phase}).",
            'step_approved' => "{$ref} — gate approval advanced on {$gate}.",
            'approved' => "{$ref} — gate approved for {$gate}.",
            'rejected' => "{$ref} — gate approval rejected for {$gate}.",
            'escalated' => "{$ref} — gate approval escalation on {$gate}.",
            default => "{$ref} — gate update on {$gate}.",
        };
    }
}
