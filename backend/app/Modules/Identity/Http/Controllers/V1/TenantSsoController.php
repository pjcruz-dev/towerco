<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\AzureGraphService;
use App\Modules\Identity\Services\AzureGroupRoleMapper;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantUserProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class TenantSsoController extends AbstractApiController
{
    public function __construct(
        private readonly TenantUserProvisioningService $provisioning,
        private readonly AzureGroupRoleMapper $roleMapper,
        private readonly AzureGraphService $graphService,
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthAuditService $auditService,
    ) {}

    public function redirect(): RedirectResponse|JsonResponse
    {
        $config = DB::table('tenant_sso_configs')
            ->where('tenant_id', (string) tenant('id'))
            ->where('provider', 'azure')
            ->where('enabled', true)
            ->first();

        if (! $config) {
            return response()->json(['message' => __('Azure SSO is not configured.')], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        config([
            'services.azure.client_id' => $config->client_id,
            'services.azure.client_secret' => decrypt((string) $config->client_secret_encrypted),
            'services.azure.tenant' => $config->tenant_identifier,
            'services.azure.redirect' => route('api.tenant.v1.auth.sso.azure.callback'),
        ]);

        return Socialite::driver('azure')->stateless()->redirect();
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $socialUser = Socialite::driver('azure')->stateless()->user();

        $user = $this->provisioning->findOrProvision(
            email: (string) $socialUser->getEmail(),
            name: $socialUser->getName()
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => (string) tenant('id'),
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                'tenant_accesses' => [[
                    'tenant_id' => (string) tenant('id'),
                    'tenant_name' => (string) tenant('id'),
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ]],
            ],
        ];

        if ($request->boolean('redirect', true)) {
            $frontendAppUrl = rtrim((string) env('FRONTEND_APP_URL', 'http://localhost:3001'), '/');
            $encodedPayload = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

            return response()->redirectTo(
                $frontendAppUrl.'/login/sso-callback?payload='.$encodedPayload,
                Response::HTTP_FOUND
            );
        }

        return $this->ok($payload);
    }
}

