<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\Identity\Services\EntraGraphAppService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EntraGraphAppServiceTest extends TestCase
{
    public function test_get_manager_email_for_user(): void
    {
        Config::set('services.azure.client_id', 'test-client');
        Config::set('services.azure.client_secret', 'test-secret');
        Config::set('services.azure.tenant', 'test-tenant');

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/users/*' => Http::response([
                'mail' => 'manager@example.com',
                'userPrincipalName' => 'manager@example.com',
            ]),
        ]);

        $service = app(EntraGraphAppService::class);
        $email = $service->getManagerEmailForUser('requestor@example.com');

        $this->assertSame('manager@example.com', $email);
    }

    public function test_returns_null_when_not_configured(): void
    {
        Config::set('services.azure.client_id', '');
        Config::set('services.azure.client_secret', '');

        $service = app(EntraGraphAppService::class);
        $this->assertNull($service->getManagerEmailForUser('requestor@example.com'));
    }
}
