<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantAuthUserPayloadBuilder;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use Illuminate\Validation\ValidationException;

final class PlatformTenantImpersonationService
{
    public function __construct(
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly TenantAuthUserPayloadBuilder $userPayloadBuilder,
        private readonly PlatformTenantAuditLogger $platformAudit,
    ) {}

    /**
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     session_id: string,
     *     mfa_required: bool,
     *     user: array<string, mixed>,
     *     tenant_domain: string|null
     * }
     */
    public function start(Tenant $tenant, User $actor, string $targetUserId, string $reason): array
    {
        $this->assertFeatureEnabled();

        return $tenant->run(function () use ($tenant, $actor, $targetUserId, $reason): array {
            /** @var TenantUser|null $target */
            $target = TenantUser::query()->find($targetUserId);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'user_id' => [__('The selected user was not found in this tenant.')],
                ]);
            }

            $this->assertTargetImpersonatable($target);

            $sessionId = $this->sessionService->startImpersonation((string) $target->id);
            $this->sessionService->markMfaVerified($sessionId);

            $ttlMinutes = (int) config('toweros.platform_impersonation.token_ttl_minutes', 30);
            $accessToken = $target->createToken(
                name: 'platform-impersonation',
                abilities: [
                    '*',
                    'session:'.$sessionId,
                    'platform_impersonator:'.(string) $actor->id,
                ],
                expiresAt: now()->addMinutes(max(5, $ttlMinutes)),
            )->plainTextToken;

            $refresh = $this->refreshTokenService->issue((string) $target->id, $sessionId);

            $platformImpersonator = [
                'id' => (string) $actor->id,
                'name' => (string) $actor->name,
                'email' => (string) $actor->email,
                'source' => 'platform',
            ];

            $this->platformAudit->log(
                PlatformTenantAuditEventType::TENANT_IMPERSONATION_STARTED,
                $tenant,
                $actor,
                null,
                [
                    'target_user_id' => (string) $target->id,
                    'target_email' => $target->email,
                    'reason' => $reason,
                ],
            );

            $tenantDomain = $tenant->domains()->orderBy('id')->value('domain');

            return [
                'access_token' => $accessToken,
                'refresh_token' => $refresh['token'],
                'session_id' => $sessionId,
                'mfa_required' => false,
                'tenant_domain' => is_string($tenantDomain) && $tenantDomain !== '' ? strtolower($tenantDomain) : null,
                'user' => $this->userPayloadBuilder->build($target, null, $platformImpersonator),
            ];
        });
    }

    private function assertFeatureEnabled(): void
    {
        if (! (bool) config('toweros.platform_impersonation.enabled', true)) {
            throw ValidationException::withMessages([
                'impersonation' => [__('Platform impersonation is disabled.')],
            ]);
        }
    }

    private function assertTargetImpersonatable(TenantUser $target): void
    {
        if (! $target->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => [__('Cannot impersonate a deactivated user.')],
            ]);
        }
    }
}
