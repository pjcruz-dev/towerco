<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Help\Models\HelpGuide;
use App\Modules\Help\Services\HelpGuideService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class HelpGuidesTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
            ],
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_published_guide_is_visible_to_viewers_and_hidden_when_unpublished(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        app(HelpGuideService::class)->seedGuide(
            moduleKey: 'e_approval',
            slug: 'e-approval-for-requestors',
            role: HelpGuide::ROLE_REQUESTOR,
            title: 'Requestor guide',
            body: "# Requestor\n\nSubmit a request.",
            sortOrder: 10,
        );

        $viewer = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer-help@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        $viewer->givePermissionTo('e_approval:view');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/help/guides?module=e_approval')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'e-approval-for-requestors');

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/help/guides/e-approval-for-requestors')
            ->assertOk()
            ->assertJsonPath('data.title', 'Requestor guide');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/help/admin/guides/e-approval-for-requestors/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/help/guides/e-approval-for-requestors')
            ->assertNotFound();
    }

    public function test_admin_can_update_guide_and_seed_skips_edited_content(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $service = app(HelpGuideService::class);
        $seedBody = "# Original\n\nSeed body.";
        $service->seedGuide(
            moduleKey: 'e_approval',
            slug: 'e-approval-for-approvers',
            role: HelpGuide::ROLE_APPROVER,
            title: 'Approver guide',
            body: $seedBody,
            sortOrder: 20,
        );
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/help/admin/guides/e-approval-for-approvers', [
                'title' => 'Approver guide (edited)',
                'body' => "# Edited\n\nAdmin changed this.",
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Approver guide (edited)');

        tenancy()->initialize($this->testTenant);
        $result = $service->seedGuide(
            moduleKey: 'e_approval',
            slug: 'e-approval-for-approvers',
            role: HelpGuide::ROLE_APPROVER,
            title: 'Approver guide',
            body: $seedBody,
            sortOrder: 20,
            force: false,
        );
        $this->assertSame('skipped', $result['action']);
        $this->assertSame('Approver guide (edited)', $result['guide']->title);
        tenancy()->end();
    }

    public function test_admin_routes_require_settings_manage(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $viewer = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer-help-admin@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        $viewer->givePermissionTo('e_approval:view');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/help/admin/guides')
            ->assertForbidden();
    }
}
