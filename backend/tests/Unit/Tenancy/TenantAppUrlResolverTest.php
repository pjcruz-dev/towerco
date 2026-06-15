<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantAppUrlResolverTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_url_for_current_tenant_uses_tenant_hostname_not_localhost(): void
    {
        tenancy()->initialize($this->testTenant);

        $url = app(TenantAppUrlResolver::class)->urlForCurrentTenant('/project-one/gate-approvals');

        $this->assertSame('http://test.localhost/project-one/gate-approvals', $url);
        $this->assertStringNotContainsString('://localhost/', $url);

        tenancy()->end();
    }

    public function test_run_for_tenant_restores_context_for_mail_urls(): void
    {
        $tenantId = (string) $this->testTenant->getTenantKey();

        $url = app(TenantAppUrlResolver::class)->runForTenant($tenantId, function (): string {
            return app(TenantAppUrlResolver::class)->urlForCurrentTenant('/project-one/gate-approvals');
        });

        $this->assertSame('http://test.localhost/project-one/gate-approvals', $url);
        $this->assertNull(tenant());
    }
}
