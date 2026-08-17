<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserOrgChartTest extends TestCase
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
        $this->ensureTenantSsoConfigTable();
    }

    private function ensureTenantSsoConfigTable(): void
    {
        if (Schema::connection('central')->hasTable('tenant_sso_configs')) {
            return;
        }

        Schema::connection('central')->create('tenant_sso_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('provider', 50)->default('azure');
            $table->string('issuer')->nullable();
            $table->string('client_id');
            $table->text('client_secret_encrypted');
            $table->string('tenant_identifier')->default('common');
            $table->json('group_mapping_rules')->nullable();
            $table->json('allowed_email_domains')->nullable();
            $table->boolean('auto_provision_users')->default(true);
            $table->boolean('disable_password_login_when_enabled')->default(true);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function test_org_chart_returns_reporting_lines(): void
    {
        tenancy()->initialize($this->testTenant);
        $manager = TenantUser::query()->create([
            'name' => 'Alvin Tolentino',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users/org-chart');

        $response->assertOk();
        $people = collect($response->json('data.people'));
        $this->assertSame(
            (string) $manager->id,
            $people->firstWhere('email', 'terrence@example.com')['manager_id'],
        );
        $this->assertSame(
            1,
            $people->firstWhere('email', 'alvin@example.com')['direct_report_count'],
        );
    }

    public function test_user_index_includes_manager_fields(): void
    {
        tenancy()->initialize($this->testTenant);
        $manager = TenantUser::query()->create([
            'name' => 'Alvin Tolentino',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'manager_id' => $manager->id,
            'job_title' => 'Engineer',
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=terrence@example.com');

        $response->assertOk()
            ->assertJsonPath('data.0.email', 'terrence@example.com')
            ->assertJsonPath('data.0.job_title', 'Engineer')
            ->assertJsonPath('data.0.manager.email', 'alvin@example.com')
            ->assertJsonPath('data.0.direct_report_count', 0);
    }

    public function test_viewer_cannot_read_org_chart_or_sync(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions(['dashboard:view']);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users/org-chart')
            ->assertForbidden();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/entra-org-sync')
            ->assertForbidden();
    }

    public function test_org_sync_reports_not_configured_without_entra_app(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/entra-org-sync');

        $response->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.code', 'not_configured');
    }
}
