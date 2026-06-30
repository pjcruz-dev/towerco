<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EApprovalNotificationCategoryTest extends TestCase
{
    #[DataProvider('actionTypesProvider')]
    public function test_it_maps_action_types(string $type): void
    {
        $this->assertSame(EApprovalNotificationCategory::ACTION, EApprovalNotificationCategory::forType($type));
    }

    #[DataProvider('updateTypesProvider')]
    public function test_it_maps_update_types(string $type): void
    {
        $this->assertSame(EApprovalNotificationCategory::UPDATE, EApprovalNotificationCategory::forType($type));
    }

    public function test_it_builds_hrefs_for_approval_inbox_and_submission_tabs(): void
    {
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=workflow',
            EApprovalNotificationCategory::hrefFor('approval_assigned', 'sub-1'),
        );
        $this->assertSame(
            '/e-approval/approvals?awaiting_me=1',
            EApprovalNotificationCategory::hrefFor('approval_assigned', null),
        );
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=workflow',
            EApprovalNotificationCategory::hrefFor('manual_follow_up', 'sub-1'),
        );
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=actions',
            EApprovalNotificationCategory::hrefFor('returned', 'sub-1'),
        );
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=workflow',
            EApprovalNotificationCategory::hrefFor('approved', 'sub-1'),
        );
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=comments',
            EApprovalNotificationCategory::hrefFor('comment_added', 'sub-1'),
        );
        $this->assertSame(EApprovalNotificationCategory::UPDATE, EApprovalNotificationCategory::forType('comment_added'));
        $this->assertSame(
            '/e-approval/submissions/sub-1?tab=comments',
            EApprovalNotificationCategory::hrefFor('comment_replied', 'sub-1'),
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function actionTypesProvider(): array
    {
        return array_map(static fn (string $type): array => [$type], EApprovalNotificationCategory::actionTypes());
    }

    /**
     * @return list<array{string}>
     */
    public static function updateTypesProvider(): array
    {
        return [
            ['approved'],
            ['rejected'],
            ['approval_rerouted'],
            ['comment_added'],
            ['comment_replied'],
        ];
    }
}
