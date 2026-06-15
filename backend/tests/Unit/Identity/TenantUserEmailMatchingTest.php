<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserEmailMatchingTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_import_skips_duplicate_email_different_casing(): void
    {
        tenancy()->initialize($this->testTenant);
        $service = app(TenantUserAdminService::class);

        $first = $service->importRows([
            ['email' => 'Jane.Doe@Example.com', 'name' => 'Jane Doe', 'role' => 'viewer'],
        ]);
        $second = $service->importRows([
            ['email' => 'jane.doe@example.com', 'name' => 'Jane Doe', 'role' => 'viewer'],
        ]);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertTrue(TenantUser::emailExists('jane.doe@example.com'));
        $this->assertSame(
            1,
            TenantUser::query()->whereRaw('LOWER(email) = ?', ['jane.doe@example.com'])->count(),
        );
        tenancy()->end();
    }

    public function test_find_by_email_matches_imported_user_for_microsoft_sign_in(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantUserAdminService::class)->importRows([
            ['email' => 'jane.doe@example.com', 'name' => 'Jane Doe', 'role' => 'e_approval_approver'],
        ]);
        $imported = TenantUser::findByEmail('jane.doe@example.com');
        $this->assertNotNull($imported);
        $matched = TenantUser::findByEmail('Jane.Doe@Example.com');

        $this->assertNotNull($matched);
        $this->assertSame((string) $imported->id, (string) $matched->id);
        $this->assertSame(
            1,
            TenantUser::query()->whereRaw('LOWER(email) = ?', ['jane.doe@example.com'])->count(),
        );
        $this->assertTrue($matched->hasRole('e_approval_approver'));
        tenancy()->end();
    }
}
