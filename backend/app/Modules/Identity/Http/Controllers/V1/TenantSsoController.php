<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\AzureGraphService;
use App\Modules\Identity\Services\AzureGroupRoleMapper;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantSsoConfigService;
use App\Modules\Identity\Services\TenantUserProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class TenantSsoController extends AbstractApiController
{
    public function __construct(
        private readonly TenantSsoConfigService $ssoConfig,
        private readonly TenantUserProvisioningService $provisioning,
        private readonly AzureGroupRoleMapper $roleMapper,
        private readonly AzureGraphService $graphService,
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthAuditService $auditService,
    ) {}

    public function redirect(Request $request): RedirectResponse|JsonResponse
    {
        $config = $this->ssoConfig->findEnabledForTenant((string) tenant('id'), 'azure');

        if ($config === null) {
            return response()->json(['message' => __('Microsoft sign-in is not enabled for this organization.')], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->ssoConfig->applyAzureSocialiteConfig($config);

        $driver = Socialite::driver('azure')->stateless();
        $tenantDomain = $this->resolveTenantDomainForSsoState($request);
        if ($tenantDomain !== null) {
            $state = json_encode(['tenant_domain' => $tenantDomain], JSON_THROW_ON_ERROR);
            $driver = $driver->with(['state' => $state]);
        }

        return $driver->redirect();
    }

    private function resolveTenantDomainForSsoState(Request $request): ?string
    {
        $fromQuery = $request->query('tenant_domain');
        if (is_string($fromQuery) && trim($fromQuery) !== '') {
            return strtolower(trim($fromQuery));
        }

        $tenantId = tenant('id');
        if ($tenantId === null) {
            return null;
        }

        $domain = \Stancl\Tenancy\Database\Models\Domain::query()
            ->where('tenant_id', (string) $tenantId)
            ->value('domain');

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $config = $this->ssoConfig->findEnabledForTenant((string) tenant('id'), 'azure');

        if ($config === null) {
            return response()->json(['message' => __('Microsoft sign-in is not enabled for this organization.')], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->ssoConfig->applyAzureSocialiteConfig($config);

        $socialUser = Socialite::driver('azure')->stateless()->user();

        $user = $this->provisioning->findForSso(
            tenantId: (string) tenant('id'),
            email: (string) $socialUser->getEmail(),
            name: $socialUser->getName(),
        );

        if (! $user->isActive()) {
            $this->auditService->log('auth.sso.azure.failed', (string) $user->id, null, [
                'email' => $socialUser->getEmail(),
                'reason' => 'inactive',
            ], 'medium');

            throw ValidationException::withMessages([
                'email' => [__('This account has been deactivated. Contact your administrator.')],
            ]);
        }

        $groupIds = [];
        if (is_string($socialUser->token) && $socialUser->token !== '') {
            $groupIds = $this->graphService->fetchGroupIds($socialUser->token);
        }
        $this->roleMapper->syncRolesForGroups($user, $groupIds);

        $sessionId = $this->sessionService->start((string) $user->id, 'azure_sso');
        $this->sessionService->markMfaVerified($sessionId);
        $accessToken = $user->createToken(
            'access',
            ['*', 'session:'.$sessionId],
            now()->addMinutes((int) env('TENANT_ACCESS_TOKEN_TTL_MINUTES', 60))
        )->plainTextToken;
        $refresh = $this->refreshTokenService->issue((string) $user->id, $sessionId);

        $this->auditService->log('auth.sso.azure.success', (string) $user->id, $sessionId, [
            'email' => $socialUser->getEmail(),
            'group_count' => count($groupIds),
        ]);

        $payload = [
            'access_token' => $accessToken,
            'refresh_token' => $refresh['token'],
            'session_id' => $sessionId,
            'mfa_required' => false,
            'user' => app(\App\Modules\Identity\Services\TenantAuthUserPayloadBuilder::class)->build($user),
        ];

        if ($request->boolean('redirect', true)) {
            $encodedPayload = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

            return response()->redirectTo(
                $this->ssoConfig->resolveSsoCallbackFrontendUrl().'?payload='.$encodedPayload,
                Response::HTTP_FOUND
            );
        }

        return $this->ok($payload);
    }
}
