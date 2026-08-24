<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\Identity\Services\EntraGraphAppService;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use App\Modules\Identity\Support\EntraUserManagerMatch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EntraGraphAppServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('services.azure.client_id', 'test-client');
        Config::set('services.azure.client_secret', 'test-secret');
        Config::set('services.azure.tenant', '11111111-1111-1111-1111-111111111111');
    }

    public function test_get_manager_email_for_user(): void
    {
        $this->fakeGraph(
            user: [
                'id' => 'req-1',
                'mail' => 'requestor@example.com',
                'userPrincipalName' => 'requestor@example.com',
                'displayName' => 'Requestor',
                'jobTitle' => 'Engineer',
                'assignedLicenses' => [
                    ['skuId' => '05e9a617-0261-4cee-bb44-138d3ef5d965'],
                ],
            ],
            manager: [
                'id' => 'mgr-1',
                'mail' => 'manager@example.com',
                'userPrincipalName' => 'manager@example.com',
                'displayName' => 'Alvin Tolentino',
                'jobTitle' => 'Director',
            ],
        );

        $service = app(EntraGraphAppService::class);
        $lookup = $service->lookupManagerForEmail('requestor@example.com');

        $this->assertTrue($lookup->ok);
        $this->assertSame(EntraManagerLookupResult::CODE_OK, $lookup->code);
        $this->assertSame('manager@example.com', $lookup->manager?->email);
        $this->assertSame('manager@example.com', $service->getManagerEmailForUser('requestor@example.com'));
    }

    public function test_graph_user_includes_assigned_licenses(): void
    {
        $this->fakeGraph(
            user: [
                'id' => 'req-1',
                'mail' => 'requestor@example.com',
                'userPrincipalName' => 'requestor@example.com',
                'displayName' => 'Requestor',
                'assignedLicenses' => [
                    ['skuId' => '05e9a617-0261-4cee-bb44-138d3ef5d965'],
                ],
            ],
            managerStatus: 404,
        );

        $service = app(EntraGraphAppService::class);
        $token = $service->getAppAccessToken();
        $this->assertNotNull($token);
        $match = $service->findUserWithManager($token, 'requestor@example.com');

        $this->assertInstanceOf(EntraUserManagerMatch::class, $match);
        $this->assertTrue($match->person->isLicensed());
        $this->assertSame(['05e9a617-0261-4cee-bb44-138d3ef5d965'], $match->person->assignedSkuIds);
    }

    public function test_retries_user_lookup_without_assigned_licenses_on_400(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]);
            }
            if (str_contains($request->url(), '/manager')) {
                return Http::response([], 404);
            }

            $select = (string) ($request['$select'] ?? $request->data()['$select'] ?? '');
            if (str_contains($select, 'assignedLicenses') || str_contains($request->url(), 'assignedLicenses')) {
                return Http::response(['error' => ['code' => 'Request_UnsupportedQuery']], 400);
            }

            return Http::response([
                'id' => 'req-1',
                'mail' => 'requestor@example.com',
                'userPrincipalName' => 'requestor@example.com',
                'displayName' => 'Requestor',
            ]);
        });

        $service = app(EntraGraphAppService::class);
        $token = $service->getAppAccessToken();
        $this->assertNotNull($token);
        $match = $service->findUserWithManager($token, 'requestor@example.com');

        $this->assertInstanceOf(EntraUserManagerMatch::class, $match);
        $this->assertSame('requestor@example.com', $match->person->email);
        $this->assertFalse($match->person->isLicensed());
    }

    public function test_token_failure_explains_invalid_client_secret(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'AADSTS7000215: Invalid client secret provided.',
            ], 401),
        ]);

        $service = app(EntraGraphAppService::class);
        $this->assertNull($service->getAppAccessToken());
        $this->assertStringContainsString('rejected the client secret', (string) $service->tokenFailureMessage());
    }

    public function test_returns_null_when_not_configured(): void
    {
        Config::set('services.azure.client_id', '');
        Config::set('services.azure.client_secret', '');

        $service = app(EntraGraphAppService::class);
        $this->assertNull($service->getManagerEmailForUser('requestor@example.com'));
        $this->assertSame(
            EntraManagerLookupResult::CODE_NOT_CONFIGURED,
            $service->lookupManagerForEmail('requestor@example.com')->code,
        );
    }

    public function test_rejects_common_directory_for_app_token_lookup(): void
    {
        Config::set('services.azure.tenant', 'common');

        $service = app(EntraGraphAppService::class);
        $lookup = $service->lookupManagerForEmail('requestor@example.com');

        $this->assertFalse($lookup->ok);
        $this->assertSame(EntraManagerLookupResult::CODE_DIRECTORY_COMMON, $lookup->code);
        $this->assertNull($service->getManagerEmailForUser('requestor@example.com'));
        Http::assertNothingSent();
    }

    public function test_retries_forbidden_with_a_fresh_app_token(): void
    {
        $graphCalls = 0;
        Http::fake(function (Request $request) use (&$graphCalls) {
            if (str_contains($request->url(), 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]);
            }

            $graphCalls++;
            if ($graphCalls === 1) {
                return Http::response(['error' => ['code' => 'Authorization_RequestDenied']], 403);
            }
            if (str_contains($request->url(), '/manager')) {
                return Http::response([
                    'id' => 'mgr-1',
                    'mail' => 'manager@example.com',
                    'userPrincipalName' => 'manager@example.com',
                    'displayName' => 'Alvin Tolentino',
                ]);
            }

            return Http::response([
                'id' => 'req-1',
                'mail' => 'requestor@example.com',
                'userPrincipalName' => 'requestor@example.com',
                'displayName' => 'Requestor',
            ]);
        });

        $lookup = app(EntraGraphAppService::class)->lookupManagerForEmail('requestor@example.com');

        $this->assertTrue($lookup->ok);
        $this->assertSame('manager@example.com', $lookup->manager?->email);
    }

    public function test_forbidden_when_graph_rejects_app_token(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]);
            }

            return Http::response(['error' => ['code' => 'Authorization_RequestDenied']], 403);
        });

        $lookup = app(EntraGraphAppService::class)->lookupManagerForEmail('requestor@example.com');

        $this->assertFalse($lookup->ok);
        $this->assertSame(EntraManagerLookupResult::CODE_FORBIDDEN, $lookup->code);
        $this->assertStringContainsString('Application permission User.Read.All', $lookup->message);
    }

    public function test_no_manager_when_graph_returns_404(): void
    {
        $this->fakeGraph(
            user: [
                'id' => 'req-1',
                'mail' => 'requestor@example.com',
                'userPrincipalName' => 'requestor@example.com',
                'displayName' => 'Requestor',
            ],
            managerStatus: 404,
        );

        $lookup = app(EntraGraphAppService::class)->lookupManagerForEmail('requestor@example.com');

        $this->assertFalse($lookup->ok);
        $this->assertSame(EntraManagerLookupResult::CODE_NO_MANAGER, $lookup->code);
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>|null  $manager
     */
    private function fakeGraph(array $user, ?array $manager = null, int $managerStatus = 200): void
    {
        Http::fake(function (Request $request) use ($user, $manager, $managerStatus) {
            $url = $request->url();
            if (str_contains($url, 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/manager')) {
                return Http::response($manager ?? [], $managerStatus);
            }

            return Http::response($user);
        });
    }
}
