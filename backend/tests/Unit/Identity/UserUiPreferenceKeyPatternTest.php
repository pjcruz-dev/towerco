<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Services\UserUiPreferenceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserUiPreferenceKeyPatternTest extends TestCase
{
    #[Test]
    public function accepts_module_list_and_dashboard_layout_keys(): void
    {
        $pattern = UserUiPreferenceService::KEY_PATTERN;

        $this->assertSame(1, preg_match($pattern, 'module-list.ticketing.tickets.columns'));
        $this->assertSame(1, preg_match($pattern, 'dashboard-layout.toweros.ticketing.tickets.layout'));
        $this->assertSame(1, preg_match($pattern, 'dashboard-layout.toweros.doc-extract.dashboard.layout'));
        $this->assertSame(1, preg_match($pattern, 'dashboard-layout.toweros.e-approval.workspace.layout.Leave-Request'));
        $this->assertSame(0, preg_match($pattern, 'toweros.ticketing.tickets.layout'));
        $this->assertSame(0, preg_match($pattern, 'dashboard.layout.foo'));
    }

    #[Test]
    public function detects_dashboard_layout_key_prefix(): void
    {
        $this->assertTrue(str_starts_with('dashboard-layout.toweros.ticketing.tickets.layout', 'dashboard-layout.'));
        $this->assertFalse(str_starts_with('module-list.ticketing.tickets.columns', 'dashboard-layout.'));
    }
}
