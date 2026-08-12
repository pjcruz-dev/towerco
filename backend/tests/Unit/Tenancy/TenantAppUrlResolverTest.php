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

    public function test_mail_brand_label_uses_tenant_slug_not_app_name(): void
    {
        $this->testTenant->slug = 'atc';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);

        $resolver = app(TenantAppUrlResolver::class);

        $this->assertSame('ATC', $resolver->mailBrandLabel());
        $this->assertSame('[ATC]', $resolver->subjectPrefix());
        $this->assertStringNotContainsString('TowerOS', $resolver->subjectPrefix());

        tenancy()->end();
    }

    public function test_mail_brand_label_falls_back_to_domain_when_slug_empty(): void
    {
        $this->testTenant->slug = null;
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);

        $this->assertSame('TEST', app(TenantAppUrlResolver::class)->mailBrandLabel());

        tenancy()->end();
    }
}
