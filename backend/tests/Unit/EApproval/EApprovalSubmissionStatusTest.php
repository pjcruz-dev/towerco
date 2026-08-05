<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EApprovalSubmissionStatusTest extends TestCase
{
    public function test_returned_label_is_needs_revision(): void
    {
        $this->assertSame('Needs revision', EApprovalSubmissionStatus::label(EApprovalSubmissionStatus::RETURNED));
        $this->assertSame(
            'Awaiting document control',
            EApprovalSubmissionStatus::label(EApprovalSubmissionStatus::AWAITING_DCF),
        );
    }

    #[DataProvider('statusKeywordProvider')]
    public function test_statuses_matching_keywords(string $query, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, EApprovalSubmissionStatus::statusesMatching($query));
    }

    /**
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function statusKeywordProvider(): array
    {
        return [
            ['revision', [EApprovalSubmissionStatus::RETURNED]],
            ['needs revision', [EApprovalSubmissionStatus::RETURNED]],
            ['returned', [EApprovalSubmissionStatus::RETURNED]],
            ['dcf', [EApprovalSubmissionStatus::AWAITING_DCF]],
            ['document control', [EApprovalSubmissionStatus::AWAITING_DCF]],
            ['pending', [EApprovalSubmissionStatus::PENDING]],
            ['xy', []],
        ];
    }
}
