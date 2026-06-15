<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class CorsAllowedOriginResolverTest extends TestCase
{
    public function test_it_builds_explicit_origins_from_configured_urls_and_extras(): void
    {
        Config::set('toweros.cors.allowed_origin_cache_ttl', 0);
        Config::set('toweros.tenant_app_url', 'http://localhost');
        Config::set('app.url', 'http://localhost:8000');
        Config::set('toweros.cors.allowed_origin_extras', 'atc.localhost,https://platform.example.com');

        $origins = app(CorsAllowedOriginResolver::class)->resolve();

        $this->assertContains('http://localhost', $origins);
        $this->assertContains('http://localhost:8000', $origins);
        $this->assertContains('http://atc.localhost', $origins);
        $this->assertContains('https://platform.example.com', $origins);
        $this->assertNotContains('http://evil.example', $origins);
    }

    public function test_it_exposes_configured_origin_patterns(): void
    {
        Config::set('toweros.cors.allowed_origin_patterns', 'https://*.example.com');

        $patterns = app(CorsAllowedOriginResolver::class)->resolvePatterns();

        $this->assertSame(['https://*.example.com'], $patterns);
    }
}
