<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantAuthUserPayloadBuilder;
use App\Modules\Identity\Support\TenantImpersonationContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final class TenantUserImpersonationService
{
    public function __construct(
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthAuditService $auditService,
        private readonly TenantAuthUserPayloadBuilder $userPayloadBuilder,
        private readonly TenantImpersonationContextResolver $impersonationResolver,
    ) {}

    /**
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     session_id: string,
     *     mfa_required: bool,
     *     user: array<string, mixed>
     * }
     */
    public function start(TenantUser $actor, TenantUser $target, string $reason): array
    {
        $this->assertFeatureEnabled();
        $this->assertActorMayImpersonate($actor);
        $this->assertTargetImpersonatable($actor, $target);

        $sessionId = $this->sessionService->startImpersonation((string) $target->id);
        $this->sessionService->markMfaVerified($sessionId);

        $ttlMinutes = (int) config('toweros.tenant_impersonation.token_ttl_minutes', 30);
        $accessToken = $target->createToken(
            name: 'impersonation',
            abilities: [
                '*',
                'session:'.$sessionId,
                'impersonator:'.(string) $actor->id,
            ],
            expiresAt: now()->addMinutes(max(5, $ttlMinutes)),
        )->plainTextToken;

        $refresh = $this->refreshTokenService->issue((string) $target->id, $sessionId);

        $this->auditService->log('auth.impersonation.started', (string) $actor->id, $sessionId, [
            'target_user_id' => (string) $target->id,
            'target_email' => $target->email,
            'reason' => $reason,
        ], 'high');

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refresh['token'],
            'session_id' => $sessionId,
            'mfa_required' => false,
            'user' => $this->userPayloadBuilder->build($target, $actor),
        ];
    }

    /**
     * @return array{message: string}
     */
    public function stop(Request $request): array
    {
        $context = $this->impersonationResolver->fromRequest($request);
        if ($context === null) {
            throw ValidationException::withMessages([
                'session' => [__('Not in an impersonation session.')],
            ]);
        }

        /** @var TenantUser $effectiveUser */
        $effectiveUser = $request->user();
        assert($effectiveUser instanceof TenantUser);

        $sessionId = $context->sessionId;
        $this->sessionService->revoke($sessionId);

        $familyIds = DB::connection('tenant')
            ->table('refresh_token_families')
            ->where('session_id', $sessionId)
            ->pluck('id');
        foreach ($familyIds as $familyId) {
            $this->refreshTokenService->revokeFamily((string) $familyId);
        }

        $token = $effectiveUser->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $actorId = $context->impersonatorUserId();
        if ($actorId !== null) {
            $this->auditService->log('auth.impersonation.stopped', $actorId, $sessionId, [
                'target_user_id' => (string) $effectiveUser->id,
                'source' => $context->platformImpersonator !== null ? 'platform' : 'tenant',
            ], 'high');
        }

        return [
            'message' => __('Impersonation ended. Restore your administrator session in the client.'),
        ];
    }

    public function canImpersonateTarget(TenantUser $actor, TenantUser $target): bool
    {
        try {
            $this->assertFeatureEnabled();
            $this->assertActorMayImpersonate($actor);
            $this->assertTargetImpersonatable($actor, $target);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function assertFeatureEnabled(): void
    {
        if (! (bool) config('toweros.tenant_impersonation.enabled', true)) {
            throw ValidationException::withMessages([
                'impersonation' => [__('User impersonation is disabled for this tenant.')],
            ]);
        }
    }

    private function assertActorMayImpersonate(TenantUser $actor): void
    {
        if (! $actor->isActive()) {
            throw ValidationException::withMessages([
                'user' => [__('Your account is not active.')],
            ]);
        }

        if (! $actor->can('user:impersonate')) {
            throw ValidationException::withMessages([
                'user' => [__('You do not have permission to impersonate users.')],
            ]);
        }
    }

    private function assertTargetImpersonatable(TenantUser $actor, TenantUser $target): void
    {
        if ((string) $actor->id === (string) $target->id) {
            throw ValidationException::withMessages([
                'user' => [__('You cannot impersonate yourself.')],
            ]);
        }

        if (! $target->isActive()) {
            throw ValidationException::withMessages([
                'user' => [__('Cannot impersonate a deactivated user.')],
            ]);
        }

        if ($target->hasRole('tenant_admin')) {
            throw ValidationException::withMessages([
                'user' => [__('Cannot impersonate another tenant administrator.')],
            ]);
        }
    }
}
