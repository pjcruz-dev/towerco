<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Services\TenantSsoConfigService;
use Illuminate\Http\Request;
use Tests\TestCase;

final class TenantSsoConfigServiceRedirectUriTest extends TestCase
{
    public function test_redirect_uri_uses_app_url_even_when_request_host_omits_port(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $request = Request::create('http://localhost/api/v1/admin/sso/config', 'GET');
        $this->app->instance('request', $request);

        $service = app(TenantSsoConfigService::class);

        $this->assertSame(
            'http://localhost:8000/api/v1/auth/sso/azure/callback',
            $service->redirectUri(),
        );
    }
}
