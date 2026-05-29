<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Default gate-approval email notification policies (platform / tenant baseline).
 *
 * @phpstan-type EventPolicy array{enabled: bool, recipients: list<string>}
 * @phpstan-type GateApprovalEmailPolicy array{
 *     enabled: bool,
 *     events: array<string, EventPolicy>
 * }
 */
final class RolloutEmailNotificationPolicyDefaults
{
    public const EVENT_SUBMITTED = 'submitted';

    public const EVENT_STEP_APPROVED = 'step_approved';

    public const EVENT_APPROVED = 'approved';

    public const EVENT_REJECTED = 'rejected';

    public const EVENT_ESCALATED = 'escalated';

    /** @var list<string> */
    public const GATE_EVENTS = [
        self::EVENT_SUBMITTED,
        self::EVENT_STEP_APPROVED,
        self::EVENT_APPROVED,
        self::EVENT_REJECTED,
        self::EVENT_ESCALATED,
    ];

    /**
     * @return array{gate_approval: GateApprovalEmailPolicy}
     */
    public static function all(): array
    {
        return [
            'gate_approval' => self::gateApprovalDefaults(),
        ];
    }

    /**
     * @return GateApprovalEmailPolicy
     */
    public static function gateApprovalDefaults(): array
    {
        return [
            'enabled' => true,
            'events' => [
                self::EVENT_SUBMITTED => [
                    'enabled' => true,
                    'recipients' => [RolloutEmailNotificationRecipients::CURRENT_APPROVER],
                ],
                self::EVENT_STEP_APPROVED => [
                    'enabled' => true,
                    'recipients' => [
                        RolloutEmailNotificationRecipients::CURRENT_APPROVER,
                        RolloutEmailNotificationRecipients::REQUESTER,
                    ],
                ],
                self::EVENT_APPROVED => [
                    'enabled' => true,
                    'recipients' => [RolloutEmailNotificationRecipients::REQUESTER],
                ],
                self::EVENT_REJECTED => [
                    'enabled' => true,
                    'recipients' => [RolloutEmailNotificationRecipients::REQUESTER],
                ],
                self::EVENT_ESCALATED => [
                    'enabled' => true,
                    'recipients' => [RolloutEmailNotificationRecipients::CURRENT_APPROVER],
                ],
            ],
        ];
    }
}
