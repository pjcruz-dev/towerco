<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalFundsNeededBy;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EApprovalAutoApproveRulesTest extends TestCase
{
    public function test_days_are_read_only_when_the_step_is_enabled(): void
    {
        $this->assertNull(EApprovalWorkflowStepDefinitionSupport::autoApproveDays([
            'auto_approve_enabled' => false,
            'auto_approve_days' => 2,
        ]));
        $this->assertSame(2, EApprovalWorkflowStepDefinitionSupport::autoApproveDays([
            'auto_approve_enabled' => true,
            'auto_approve_days' => 2,
        ]));
        $this->assertSame(30, EApprovalWorkflowStepDefinitionSupport::autoApproveDays([
            'auto_approve_enabled' => true,
            'auto_approve_days' => 90,
        ]));
    }

    public function test_the_clock_is_due_after_the_configured_days(): void
    {
        $now = new DateTimeImmutable('2026-10-28 09:00:00');

        $this->assertFalse(EApprovalWorkflowStepDefinitionSupport::approvalIsDue(
            new DateTimeImmutable('2026-10-27 09:00:00'),
            2,
            $now,
        ));
        $this->assertTrue(EApprovalWorkflowStepDefinitionSupport::approvalIsDue(
            new DateTimeImmutable('2026-10-26 09:00:00'),
            2,
            $now,
        ));
    }

    public function test_weekends_do_not_count_toward_auto_approve(): void
    {
        $monday = new DateTimeImmutable('2026-10-26 09:00:00');

        $this->assertFalse(EApprovalWorkflowStepDefinitionSupport::approvalIsDue(
            new DateTimeImmutable('2026-10-23 09:00:00'),
            1,
            new DateTimeImmutable('2026-10-25 09:00:00'),
        ));
        $this->assertTrue(EApprovalWorkflowStepDefinitionSupport::approvalIsDue(
            new DateTimeImmutable('2026-10-23 09:00:00'),
            1,
            $monday,
        ));
    }

    #[DataProvider('fundsNeededProvider')]
    public function test_funds_needed_by_requires_three_days(string $value, bool $ok): void
    {
        $today = new DateTimeImmutable('2026-10-28');
        $error = EApprovalFundsNeededBy::error($value, $today);

        if ($ok) {
            $this->assertNull($error);
        } else {
            $this->assertNotNull($error);
        }
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function fundsNeededProvider(): array
    {
        return [
            ['2026-10-30', false],
            ['2026-10-31', true],
            ['2026-11-04', true],
        ];
    }
}
