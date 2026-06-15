<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CorsAllowlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('toweros.tenant_app_url', 'http://localhost');
        Config::set('toweros.cors.allowed_origin_cache_ttl', 0);
        $this->refreshCorsConfig();
    }

    #[Test]
    public function preflight_from_allowed_origin_returns_access_control_allow_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/platform/me');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost');
    }

    #[Test]
    public function preflight_allows_tenant_session_header_required_by_spa(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://staging.towerone.localhost',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,x-tenant-domain,x-session-id',
        ])->options('/api/v1/notifications');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://staging.towerone.localhost');

        $allowed = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('x-session-id', $allowed);
    }

    #[Test]
    public function preflight_from_disallowed_origin_does_not_reflect_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/platform/me');

        $this->assertNotSame('http://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    private function refreshCorsConfig(): void
    {
        $resolver = app(CorsAllowedOriginResolver::class);

        config([
            'cors.allowed_origins' => $resolver->resolve(),
            'cors.allowed_origins_patterns' => $resolver->resolvePatterns(),
        ]);
    }
}
