<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalAssignableUsersTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_assignable_users_only_include_users_with_approve_permission(): void
    {
        tenancy()->initialize($this->testTenant);

        $approver = TenantUser::query()->create([
            'name' => 'Approver Person',
            'email' => 'approver-picker@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver->assignRole('e_approval_approver');

        $requestorOnly = TenantUser::query()->create([
            'name' => 'Requestor Only',
            'email' => 'requestor-picker@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestorOnly->assignRole('e_approval_requestor');

        $inactiveApprover = TenantUser::query()->create([
            'name' => 'Inactive Approver',
            'email' => 'inactive-approver@test.localhost',
            'password' => 'password',
            'is_active' => false,
        ]);
        $inactiveApprover->assignRole('e_approval_approver');

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/assignable-users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains((string) $approver->id, $ids);
        $this->assertNotContains(
            (string) $this->testTenantAdmin->id,
            $ids,
            'Bootstrap break-glass admin must not appear in Approver pickers.',
        );
        $this->assertNotContains((string) $requestorOnly->id, $ids);
        $this->assertNotContains((string) $inactiveApprover->id, $ids);
    }
}
