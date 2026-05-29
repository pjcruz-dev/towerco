<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Data\RolloutEmailNotificationPolicyDefaults;
use App\Modules\Rollout\Data\RolloutEmailNotificationRecipients;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;

final class RolloutEmailNotificationPolicyService
{
    public function __construct(
        private readonly RolloutGateApproverResolver $approvers,
    ) {}

    /**
     * @return array{gate_approval: array{enabled: bool, events: array<string, array{enabled: bool, recipients: list<string>}>}}
     */
    public function mergedPolicies(?TenantRolloutPlaybookConfig $config = null): array
    {
        $defaults = RolloutEmailNotificationPolicyDefaults::all();
        $overrides = $config?->email_notification_policies ?? [];

        if (! is_array($overrides) || $overrides === []) {
            return $defaults;
        }

        $merged = $defaults;
        $gateOverride = $overrides['gate_approval'] ?? null;

        if (! is_array($gateOverride)) {
            return $merged;
        }

        if (array_key_exists('enabled', $gateOverride)) {
            $merged['gate_approval']['enabled'] = (bool) $gateOverride['enabled'];
        }

        $eventOverrides = $gateOverride['events'] ?? null;
        if (! is_array($eventOverrides)) {
            return $merged;
        }

        foreach ($eventOverrides as $eventKey => $eventPolicy) {
            if (! is_string($eventKey) || ! is_array($eventPolicy)) {
                continue;
            }

            if (! isset($merged['gate_approval']['events'][$eventKey])) {
                $merged['gate_approval']['events'][$eventKey] = [
                    'enabled' => false,
                    'recipients' => [],
                ];
            }

            if (array_key_exists('enabled', $eventPolicy)) {
                $merged['gate_approval']['events'][$eventKey]['enabled'] = (bool) $eventPolicy['enabled'];
            }

            if (isset($eventPolicy['recipients']) && is_array($eventPolicy['recipients'])) {
                $merged['gate_approval']['events'][$eventKey]['recipients'] = RolloutEmailNotificationRecipients::sanitize(
                    $eventPolicy['recipients'],
                );
            }
        }

        return $merged;
    }

    public function shouldNotifyGateEvent(string $event, ?TenantRolloutPlaybookConfig $config = null): bool
    {
        if (! in_array($event, RolloutEmailNotificationPolicyDefaults::GATE_EVENTS, true)) {
            return false;
        }

        $policies = $this->mergedPolicies($config);
        $gate = $policies['gate_approval'] ?? null;

        if ($gate === null || ! ($gate['enabled'] ?? false)) {
            return false;
        }

        $eventPolicy = $gate['events'][$event] ?? null;

        return is_array($eventPolicy) && ($eventPolicy['enabled'] ?? false);
    }

    /**
     * @return list<TenantUser>
     */
    public function resolveGateEventRecipients(
        string $event,
        RolloutGateApprovalRequest $request,
        ?TenantRolloutPlaybookConfig $config = null,
    ): array {
        if (! $this->shouldNotifyGateEvent($event, $config)) {
            return [];
        }

        $policies = $this->mergedPolicies($config);
        $recipients = $policies['gate_approval']['events'][$event]['recipients'] ?? [];

        if ($recipients === []) {
            return [];
        }

        $program = $request->rolloutProgram;
        if ($program === null) {
            return [];
        }

        $users = [];
        $seen = [];

        foreach ($recipients as $token) {
            foreach ($this->usersForRecipientToken($token, $request) as $user) {
                if (! isset($seen[$user->id])) {
                    $seen[$user->id] = true;
                    $users[] = $user;
                }
            }
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $policies
     */
    public function saveTenantPolicies(array $policies): void
    {
        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();
        $config->email_notification_policies = $this->normalizeTenantInput($policies);
        $config->save();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeTenantInput(array $input): array
    {
        $gate = $input['gate_approval'] ?? null;
        if (! is_array($gate)) {
            return RolloutEmailNotificationPolicyDefaults::all();
        }

        $defaults = RolloutEmailNotificationPolicyDefaults::gateApprovalDefaults();
        $events = $defaults['events'];
        $rawEvents = $gate['events'] ?? [];

        if (is_array($rawEvents)) {
            foreach (RolloutEmailNotificationPolicyDefaults::GATE_EVENTS as $eventKey) {
                $eventPolicy = $rawEvents[$eventKey] ?? null;
                if (! is_array($eventPolicy)) {
                    continue;
                }

                $events[$eventKey] = [
                    'enabled' => (bool) ($eventPolicy['enabled'] ?? ($events[$eventKey]['enabled'] ?? false)),
                    'recipients' => isset($eventPolicy['recipients']) && is_array($eventPolicy['recipients'])
                        ? RolloutEmailNotificationRecipients::sanitize($eventPolicy['recipients'])
                        : ($events[$eventKey]['recipients'] ?? []),
                ];
            }
        }

        return [
            'gate_approval' => [
                'enabled' => (bool) ($gate['enabled'] ?? true),
                'events' => $events,
            ],
        ];
    }

    /**
     * @return list<TenantUser>
     */
    private function usersForRecipientToken(string $token, RolloutGateApprovalRequest $request): array
    {
        $program = $request->rolloutProgram;
        if ($program === null) {
            return [];
        }

        return match ($token) {
            RolloutEmailNotificationRecipients::CURRENT_APPROVER => $this->currentApprovers($request),
            RolloutEmailNotificationRecipients::REQUESTER => $request->requestedBy !== null ? [$request->requestedBy] : [],
            RolloutEmailNotificationRecipients::PMO_OWNER => $this->approvers->usersForRole($program, 'pmo'),
            RolloutEmailNotificationRecipients::SAQ_OWNER => $this->approvers->usersForRole($program, 'saq'),
            RolloutEmailNotificationRecipients::CME_LEAD => $this->approvers->usersForRole($program, 'cme'),
            default => [],
        };
    }

    /**
     * @return list<TenantUser>
     */
    private function currentApprovers(RolloutGateApprovalRequest $request): array
    {
        $program = $request->rolloutProgram;
        $role = $request->currentApproverRole();

        if ($program === null || $role === null) {
            return [];
        }

        return $this->approvers->usersForRole($program, $role);
    }
}
