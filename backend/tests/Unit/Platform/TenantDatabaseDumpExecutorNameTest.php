<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseDumpExecutor;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class TenantDatabaseDumpExecutorNameTest extends TestCase
{
    #[Test]
    public function accepts_stancl_tenant_uuid_database_names(): void
    {
        $method = new ReflectionMethod(TenantDatabaseDumpExecutor::class, 'assertSafeDatabaseName');
        $method->setAccessible(true);

        $method->invoke(new TenantDatabaseDumpExecutor, 'tenantf59b7369-8ad3-4ece-ac7a-8b015a75f595');

        $this->expectException(RuntimeException::class);
        $method->invoke(new TenantDatabaseDumpExecutor, 'toweros; drop database');
    }
}
