<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantDatabaseBackupPathTest extends TestCase
{
    #[Test]
    public function storage_path_must_be_under_tenant_backups_prefix(): void
    {
        $tenant = new Tenant;
        $tenant->id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $service = app(TenantDatabaseBackupService::class);

        $service->assertStoragePathOwnedByTenant(
            $tenant,
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/backups/2026/08/file.sql.gz',
        );

        $this->expectException(ValidationException::class);
        $service->assertStoragePathOwnedByTenant(
            $tenant,
            'other-tenant/backups/2026/08/file.sql.gz',
        );
    }

    #[Test]
    public function storage_path_rejects_traversal(): void
    {
        $tenant = new Tenant;
        $tenant->id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->expectException(ValidationException::class);
        app(TenantDatabaseBackupService::class)->assertStoragePathOwnedByTenant(
            $tenant,
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/backups/../secret.sql.gz',
        );
    }
}
