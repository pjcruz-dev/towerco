<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Support\ProjectOneNotificationCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProjectOneNotificationCategoryTest extends TestCase
{
    #[DataProvider('actionGateEvents')]
    public function test_gate_submitted_and_escalated_are_action_category(string $event): void
    {
        $this->assertSame(
            ProjectOneNotificationCategory::ACTION,
            ProjectOneNotificationCategory::forGateEvent($event),
        );
    }

    public static function actionGateEvents(): array
    {
        return [
            ['submitted'],
            ['escalated'],
        ];
    }

    public function test_href_for_submitted_points_to_inbox(): void
    {
        $this->assertSame(
            '/project-one/gate-approvals',
            ProjectOneNotificationCategory::hrefFor('gate_submitted', 'req-1', 'prog-1'),
        );
    }

    public function test_href_for_approved_points_to_rollout_timeline(): void
    {
        $this->assertSame(
            '/project-one/rollouts/prog-1?tab=timeline',
            ProjectOneNotificationCategory::hrefFor('gate_approved', 'req-1', 'prog-1'),
        );
    }
}
