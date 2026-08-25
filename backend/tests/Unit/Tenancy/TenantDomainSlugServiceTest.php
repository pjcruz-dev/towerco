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

        // Non-local envs with a real brand domain prefer brand DNS even when APP_URL is localhost.
        $test = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'test');
        $this->assertSame('test.alliancetowers.com', $test['endpoints'][0]['hostname']);

        $staging = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'staging');
        $this->assertSame('staging.alliancetowers.com', $staging['endpoints'][0]['hostname']);

        $production = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'production');
        $this->assertSame('app.alliancetowers.com', $production['endpoints'][0]['hostname']);
        $this->assertSame('http://app.alliancetowers.com/login', $production['endpoints'][0]['login_url']);
    }

    public function test_keeps_slug_localhost_hosts_when_brand_is_not_public(): void
    {
        $this->app['env'] = 'local';
        Config::set('toweros.tenant_app_url', 'http://localhost');
        Config::set('app.url', 'http://localhost');

        $tenant = new Tenant([
            'slug' => 'acme',
            'brand_domain' => 'dev',
        ]);

        $service = app(TenantDomainSlugService::class);
        $staging = $service->recommend($tenant, 'acme', 'dev', 'staging');
        $this->assertSame('staging.acme.localhost', $staging['endpoints'][0]['hostname']);
    }

    public function test_recommends_brand_domains_for_deployed_environments_when_app_is_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('toweros.tenant_app_url', 'https://console.example.com');

        $tenant = new Tenant([
            'slug' => 'acme',
            'brand_domain' => 'alliancetowers.com',
        ]);

        $service = app(TenantDomainSlugService::class);
        $production = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'production');

        $this->assertSame('app.alliancetowers.com', $production['endpoints'][0]['hostname']);
        $this->assertSame('https://app.alliancetowers.com/login', $production['endpoints'][0]['login_url']);
        $this->assertSame('alliancetowers.com', $production['endpoints'][1]['hostname']);
        $this->assertFalse($production['endpoints'][1]['is_primary']);

        $staging = $service->recommend($tenant, 'acme', 'alliancetowers.com', 'staging');
        $this->assertSame('staging.alliancetowers.com', $staging['endpoints'][0]['hostname']);
    }

    public function test_recommends_brand_domains_on_lan_even_when_app_env_is_local(): void
    {
        $this->app['env'] = 'local';
        Config::set('toweros.tenant_app_url', 'http://192.168.90.24');

        $tenant = new Tenant([
            'slug' => 'atc',
            'brand_domain' => 'toweros.lan',
        ]);

        $service = app(TenantDomainSlugService::class);

        $local = $service->recommend($tenant, 'atc', 'toweros.lan', 'local');
        $this->assertSame('local.toweros.lan', $local['endpoints'][0]['hostname']);

        $test = $service->recommend($tenant, 'atc', 'toweros.lan', 'test');
        $this->assertSame('test.toweros.lan', $test['endpoints'][0]['hostname']);

        $staging = $service->recommend($tenant, 'atc', 'toweros.lan', 'staging');
        $this->assertSame('staging.toweros.lan', $staging['endpoints'][0]['hostname']);
    }
}
