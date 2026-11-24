<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalNotificationCategory
{
    public const ACTION = 'action';

    public const UPDATE = 'update';

    /**
     * @return list<string>
     */
    public static function actionTypes(): array
    {
        return [
            'approval_assigned',
            'approval_assigned_revised',
            'sla_reminder',
            'sla_escalation',
            'returned',
            'manual_follow_up',
            'awaiting_dcf',
            'comment_added',
            'comment_replied',
            'comment_mention',
        ];
    }

    public static function forType(string $type): string
    {
        return in_array($type, self::actionTypes(), true) ? self::ACTION : self::UPDATE;
    }

    public static function hrefFor(string $type, ?string $submissionId, ?string $submissionPath = null): string
    {
        $open = ($submissionId !== null && $submissionId !== '')
            ? ($submissionPath !== null && $submissionPath !== ''
                ? $submissionPath
                : "/e-approval/submissions/{$submissionId}")
            : null;

        if (in_array($type, [
            'approval_assigned',
            'approval_assigned_revised',
            'sla_reminder',
            'sla_escalation',
            'manual_follow_up',
        ], true)) {
            if ($open !== null) {
                return $open.'?tab=decide';
            }

            return '/e-approval/approvals?awaiting_me=1';
        }

        if ($open !== null) {
            $tab = match ($type) {
                'returned', 'awaiting_dcf' => 'decide',
                'approved', 'rejected', 'cancelled', 'approval_no_longer_needed',
                'approval_rerouted', 'workflow_steps_skipped',
                'resubmitted_resume', 'resubmitted_restart' => 'approvals',
                'comment_added', 'comment_replied', 'comment_mention' => 'activity',
                default => 'approvals',
            };

            return $open.'?tab='.$tab;
        }

        return '/e-approval';
    }
}
