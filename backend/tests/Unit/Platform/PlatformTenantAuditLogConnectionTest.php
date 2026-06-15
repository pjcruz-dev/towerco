<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Models\PlatformTenantAuditLog;
use Tests\TestCase;

final class PlatformTenantAuditLogConnectionTest extends TestCase
{
    public function test_uses_central_connection(): void
    {
        $this->assertSame('central', (new PlatformTenantAuditLog())->getConnectionName());
    }
}
