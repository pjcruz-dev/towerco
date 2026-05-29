<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Notifications\RolloutGateApprovalNotification;
use Illuminate\Support\Facades\Notification;

final class RolloutGateApprovalNotificationDispatcher
{
    public function __construct(
        private readonly RolloutEmailNotificationPolicyService $emailPolicies,
    ) {}

    public function dispatch(RolloutGateApprovalRequest $request, string $event, ?string $actorName = null): void
    {
        $config = TenantRolloutPlaybookConfig::query()->first();
        $recipients = $this->emailPolicies->resolveGateEventRecipients($event, $request, $config);

        if ($recipients === []) {
            return;
        }

        Notification::send(
            $recipients,
            new RolloutGateApprovalNotification($request, $event, $actorName),
        );
    }
}
