<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynRoleAccessImportTest extends TestCase
{
    #[Test]
    public function import_follows_record_manage_permission_for_a_workspace_owner(): void
    {
        $user = Mockery::mock(TenantUser::class);
        $user->shouldReceive('can')
            ->with('dynamic_entities:records:manage')
            ->andReturn(true);
        $user->shouldReceive('hasRole')
            ->with(TenantRbacSystemRoles::FULL_ADMIN)
            ->andReturn(true);

        $access = app(DynRoleAccessService::class);

        $this->assertTrue($access->canEntity($user, 'tower_sites', 'import'));
        $this->assertTrue($access->canEntity($user, 'tower_sites', 'duplicate'));
    }

    #[Test]
    public function import_is_denied_without_record_manage_permission(): void
    {
        $user = Mockery::mock(TenantUser::class);
        $user->shouldReceive('can')->andReturn(false);

        $access = app(DynRoleAccessService::class);

        $this->assertFalse($access->canEntity($user, 'tower_sites', 'import'));
    }
}
