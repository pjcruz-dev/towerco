<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

final class ProjectOneNotificationCategory
{
    public const ACTION = 'action';

    public const UPDATE = 'update';

    public static function typeForGateEvent(string $event): string
    {
        return 'gate_'.$event;
    }

    public static function forGateEvent(string $event): string
    {
        return in_array($event, ['submitted', 'escalated'], true) ? self::ACTION : self::UPDATE;
    }

    public static function hrefFor(string $type, ?string $gateRequestId, ?string $rolloutProgramId): string
    {
        if (in_array($type, ['gate_submitted', 'gate_escalated'], true)) {
            return '/project-one/gate-approvals';
        }

        if ($rolloutProgramId !== null && $rolloutProgramId !== '') {
            return "/project-one/rollouts/{$rolloutProgramId}?tab=timeline";
        }

        return '/project-one/gate-approvals';
    }
}
