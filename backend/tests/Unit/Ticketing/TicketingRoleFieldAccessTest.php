<?php

declare(strict_types=1);

namespace Tests\Unit\Ticketing;

use App\Modules\Ticketing\Support\TicketingRoleFieldAccess;
use PHPUnit\Framework\TestCase;

final class TicketingRoleFieldAccessTest extends TestCase
{
    public function test_hidden_reports_page_does_not_hide_overview_or_tickets(): void
    {
        $access = new TicketingRoleFieldAccess;
        $matrix = [
            'fields' => [
                TicketingRoleFieldAccess::ENTITY => [
                    'nav:/ticketing/reports' => 'hide',
                ],
            ],
        ];

        $this->assertTrue($access->navTargetHiddenIn($matrix, '/ticketing/reports'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/ticketing'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/ticketing/tickets'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/ticketing/tickets/new'));
    }

    public function test_hidden_tickets_page_keeps_a_new_ticket_open(): void
    {
        $access = new TicketingRoleFieldAccess;
        $matrix = [
            'fields' => [
                TicketingRoleFieldAccess::ENTITY => [
                    'nav:/ticketing/tickets' => 'hide',
                ],
            ],
        ];

        $this->assertTrue($access->navTargetHiddenIn($matrix, '/ticketing/tickets/abc'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/ticketing/tickets/new'));
    }
}
