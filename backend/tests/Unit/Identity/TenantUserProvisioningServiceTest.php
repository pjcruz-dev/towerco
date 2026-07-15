<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Services\TenantUserProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserProvisioningServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootInMemoryTenantApi();
        $this->ensureTenantSsoConfigTable();
    }

    public function test_sso_auto_provision_assigns_requestor_and_ticketing_contributor_roles(): void
    {
        config([
            'toweros.tenant_auth.default_sso_roles' => [
                'e_approval_requestor',
                'ticketing_contributor',
            ],
        ]);

        $this->seedEnabledSsoConfig(autoProvision: true);

        tenancy()->initialize($this->testTenant);

        $user = app(TenantUserProvisioningService::class)
            ->findForSso((string) $this->testTenant->id, 'new.user@example.com', 'New User');

        $this->assertSame(
            ['e_approval_requestor', 'ticketing_contributor'],
            $user->getRoleNames()->sort()->values()->all(),
        );

        $this->assertTrue($user->can('dashboard:view'));
        $this->assertTrue($user->can('e_approval:submissions:create'));
        $this->assertTrue($user->can('ticketing:tickets:create'));
        $this->assertFalse($user->can('e_approval:approve'));

        tenancy()->end();
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

    private function seedEnabledSsoConfig(bool $autoProvision): void
    {
        DB::connection('central')->table('tenant_sso_configs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $this->testTenant->id,
            'provider' => 'azure',
            'client_id' => 'test-client',
            'client_secret_encrypted' => 'encrypted-secret',
            'tenant_identifier' => 'common',
            'group_mapping_rules' => null,
            'allowed_email_domains' => null,
            'auto_provision_users' => $autoProvision,
            'disable_password_login_when_enabled' => true,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
