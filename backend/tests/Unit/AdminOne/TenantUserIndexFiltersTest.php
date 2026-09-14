<?php

declare(strict_types=1);

namespace Tests\Unit\AdminOne;

use App\Modules\AdminOne\Services\TenantUserIndexFilters;
use PHPUnit\Framework\TestCase;

final class TenantUserIndexFiltersTest extends TestCase
{
    public function test_from_request_normalizes_none_sentinels(): void
    {
        $filters = TenantUserIndexFilters::fromRequest([
            'status' => 'all',
            'department' => TenantUserIndexFilters::NONE,
            'manager_id' => TenantUserIndexFilters::NONE,
            'license' => TenantUserIndexFilters::NONE,
            'role' => 'all',
        ]);

        $this->assertNull($filters->status);
        $this->assertNull($filters->role);
        $this->assertSame(TenantUserIndexFilters::NONE, $filters->department);
        $this->assertSame(TenantUserIndexFilters::NONE, $filters->managerId);
        $this->assertSame(TenantUserIndexFilters::NONE, $filters->license);
    }
}
