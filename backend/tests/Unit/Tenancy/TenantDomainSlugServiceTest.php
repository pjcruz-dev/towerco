<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantDomainSlugService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class TenantDomainSlugServiceTest extends TestCase
{
    public function test_recommends_localhost_domains_for_all_environments_when_app_is_local(): void
    {
        $this->app['env'] = 'local';
        Config::set('toweros.tenant_app_url', 'http://localhost');

        $tenant = new Tenant([
            'slug' => 'acme',
            'brand_domain' => 'alliancetowers.com',
        ]);

        $service = app(TenantDomainSlugService::class);

        $local = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'local');
        $this->assertSame('acme.localhost', $local['endpoints'][0]['hostname']);
        $this->assertSame('http://acme.localhost/login', $local['endpoints'][0]['login_url']);

        $test = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'test');
        $this->assertSame('test.acme.localhost', $test['endpoints'][0]['hostname']);

        $staging = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'staging');
        $this->assertSame('staging.acme.localhost', $staging['endpoints'][0]['hostname']);

        $production = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'production');
        $this->assertSame('app.acme.localhost', $production['endpoints'][0]['hostname']);
        $this->assertSame('http://app.acme.localhost/login', $production['endpoints'][0]['login_url']);
    }

    public function test_recommends_brand_domains_for_deployed_environments_when_app_is_production(): void
    {
        $this->app['env'] = 'production';

        $tenant = new Tenant([
            'slug' => 'acme',
            'brand_domain' => 'alliancetowers.com',
        ]);

        $service = app(TenantDomainSlugService::class);
        $production = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'production');

        $this->assertSame('app.acme.alliancetowers.com', $production['endpoints'][0]['hostname']);
        $this->assertSame('https://app.acme.alliancetowers.com/login', $production['endpoints'][0]['login_url']);
    }
}
