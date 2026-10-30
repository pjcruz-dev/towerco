<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalGridCellText;
use PHPUnit\Framework\TestCase;

final class EApprovalGridCellTextTest extends TestCase
{
    public function test_plain_text_and_amounts_pass_through(): void
    {
        $this->assertSame('Cable', EApprovalGridCellText::present('Cable'));
        $this->assertSame('150', EApprovalGridCellText::present('150'));
        $this->assertSame('', EApprovalGridCellText::present(null));
    }

    public function test_date_range_json_is_readable(): void
    {
        $raw = json_encode(['from' => '2026-10-14', 'to' => '2026-10-16']);

        $this->assertSame('2026-10-14 – 2026-10-16', EApprovalGridCellText::present($raw));
        $this->assertSame(
            '2026-10-14 08:00 – 2026-10-16 18:00',
            EApprovalGridCellText::present(['from' => '2026-10-14T08:00', 'to' => '2026-10-16T18:00']),
        );
    }

    public function test_site_stop_json_uses_site_and_location(): void
    {
        $raw = json_encode(['site' => 'ATC-01', 'location' => 'Lobby']);

        $this->assertSame('ATC-01 — Lobby', EApprovalGridCellText::present($raw));
    }
}
