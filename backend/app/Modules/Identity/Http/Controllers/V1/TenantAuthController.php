<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthAuditService;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantAuthPolicyService;
use App\Modules\Identity\Services\TenantAuthUserPayloadBuilder;
use App\Modules\Identity\Support\TenantImpersonationContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthController extends AbstractApiController
{
    public function __construct(
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly MfaService $mfaService,
        private readonly AuthAuditService $auditService,
        private readonly TenantAuthPolicyService $authPolicy,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            // RFC only: tenant emails often use dev TLDs (e.g. .localhost) that fail DNS validation.
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $tenantId = (string) tenant('id');
        $email = TenantUser::normalizeEmail($credentials['email']);
        $this->authPolicy->assertEmailDomainAllowed($tenantId, $email);

        $user = TenantUser::findByEmail($email);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->auditService->log('auth.login.failed', $user?->id, null, [
                'email' => $credentials['email'],
            ], 'medium');
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        if (! $user->isActive()) {
            $this->auditService->log('auth.login.failed', (string) $user->id, null, [
                'email' => $credentials['email'],
                'reason' => 'inactive',
            ], 'medium');
            throw ValidationException::withMessages([
                'email' => [__('This account has been deactivated. Contact your administrator.')],
            ]);
        }

        $this->authPolicy->assertPasswordLoginAllowed($user, $tenantId);

        $sessionId = $this->sessionService->start((string) $user->id, 'local');
        $accessToken = $this->issueAccessToken($user, $sessionId);
        $refresh = $this->refreshTokenService->issue((string) $user->id, $sessionId);

        $mfaRequired = $this->mfaService->isMfaRequired($user);
        $challenge = null;
        $mfaEnrolled = false;
        if ($mfaRequired) {
            $mfaEnrolled = DB::table('mfa_factors')
                ->where('user_id', $user->id)
                ->whereNull('disabled_at')
                ->whereNotNull('verified_at')
                ->exists();
            $challenge = $this->mfaService->createChallenge($sessionId);
        } else {
            $this->sessionService->markMfaVerified($sessionId);
        }

        $this->auditService->log('auth.login.success', (string) $user->id, $sessionId, [
            'auth_method' => 'local',
            'mfa_required' => $mfaRequired,
        ]);

        return $this->ok([
            'access_token' => $accessToken,
            'refresh_token' => $refresh['token'],
            'session_id' => $sessionId,
            'mfa_required' => $mfaRequired,
            'mfa_enrollment_required' => $mfaRequired && ! $mfaEnrolled,
            'mfa_challenge' => $challenge,
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $rotated = $this->refreshTokenService->rotate($data['refresh_token']);
        $accessToken = $this->issueAccessToken($rotated['user'], $rotated['session_id']);

        $this->auditService->log('auth.refresh.success', (string) $rotated['user']->id, $rotated['session_id']);

        return $this->ok([
            'access_token' => $accessToken,
            'refresh_token' => $rotated['token'],
            'session_id' => $rotated['session_id'],
            'mfa_required' => false,
            'user' => $this->authUserPayload($rotated['user']),
        ]);
    }

    public function me(Request $request, TenantImpersonationContextResolver $impersonationResolver): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $impersonation = $impersonationResolver->fromRequest($request);

        return $this->ok($this->authUserPayload(
            $user,
            $impersonation?->tenantImpersonator,
            $impersonation?->platformImpersonator,
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $sessionId = (string) $request->header('X-Session-Id', '');
        if ($sessionId !== '') {
            $this->sessionService->revoke($sessionId);
        }
        $request->user()?->currentAccessToken()?->delete();

        $this->auditService->log('auth.logout', (string) optional($request->user())->id, $sessionId ?: null);

        return $this->ok([
            'message' => __('Signed out successfully.'),
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $this->sessionService->revokeAllForUser((string) $user->id);
        $this->refreshTokenService->revokeAllForUser((string) $user->id);
        $user->tokens()->delete();

        $this->auditService->log('auth.logout_all', (string) $user->id, null);

        return $this->ok(['message' => __('All sessions revoked.')]);
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $sessions = DB::table('auth_sessions')
            ->leftJoin('auth_devices', 'auth_sessions.device_id', '=', 'auth_devices.id')
            ->where('auth_sessions.user_id', $user->id)
            ->orderByDesc('auth_sessions.created_at')
            ->get([
                'auth_sessions.id',
                'auth_sessions.auth_method',
                'auth_sessions.state',
                'auth_sessions.ip_address',
                'auth_sessions.last_seen_at',
                'auth_sessions.created_at',
                'auth_sessions.mfa_verified_at',
                'auth_devices.device_name',
                'auth_devices.trust_level',
            ]);

        return $this->ok($sessions->map(fn ($row) => (array) $row)->values()->all());
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $exists = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['session_id' => [__('Session not found.')]]);
        }

        $this->sessionService->revoke($sessionId);
        $familyIds = DB::table('refresh_token_families')->where('session_id', $sessionId)->pluck('id');
        foreach ($familyIds as $familyId) {
            $this->refreshTokenService->revokeFamily((string) $familyId);
        }

        $this->auditService->log('auth.session.revoked', (string) $user->id, $sessionId);

        return $this->ok(['message' => __('Session revoked.')]);
    }

    public function mfaChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
        ]);

        $challenge = $this->mfaService->createChallenge($data['session_id']);
        $this->auditService->log('auth.mfa.challenge.issued', null, $data['session_id']);

        return $this->ok($challenge);
    }

    public function mfaVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'min:6', 'max:10'],
            'session_id' => ['required', 'uuid'],
        ]);

        $this->mfaService->verifyChallenge($data['challenge_id'], $data['code']);
        $challenge = DB::table('mfa_challenges')->where('id', $data['challenge_id'])->first();
        if (! $challenge || $challenge->session_id !== $data['session_id']) {
            throw ValidationException::withMessages([
                'challenge_id' => [__('Challenge does not match session.')],
            ]);
        }

        $this->sessionService->markMfaVerified($data['session_id']);
        $this->auditService->log('auth.mfa.challenge.verified', null, $data['session_id']);

        return $this->ok(['verified' => true]);
    }

    public function mfaRecovery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'recovery_code' => ['required', 'string'],
        ]);

        $session = DB::table('auth_sessions')->where('id', $data['session_id'])->first();
        if (! $session) {
            throw ValidationException::withMessages([
                'session_id' => [__('Session not found.')],
            ]);
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::query()->find((string) $session->user_id);
        if (! $user) {
            throw ValidationException::withMessages([
                'recovery_code' => [__('User not found for this session.')],
            ]);
        }

        if (! $this->mfaService->verifyRecoveryCode($user, $data['recovery_code'])) {
            throw ValidationException::withMessages([
                'recovery_code' => [__('Invalid or expired recovery code.')],
            ]);
        }

        $this->sessionService->markMfaVerified($data['session_id']);
        $this->auditService->log('auth.mfa.recovery.verified', (string) $user->id, $data['session_id'], [], 'high');

        return $this->ok(['verified' => true]);
    }

    public function mfaEnrollStart(Request $request): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $setup = $this->mfaService->startEnrollment($user, 'TowerOS');
        $this->auditService->log('auth.mfa.enrollment.started', (string) $user->id, (string) $request->attributes->get('auth_session_id', ''), []);

        return $this->ok($setup);
    }

    public function mfaEnrollComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:10'],
        ]);

        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $recoveryCodes = $this->mfaService->completeEnrollment($user, $data['code']);
        $sessionId = (string) $request->attributes->get('auth_session_id', '');
        if ($sessionId !== '') {
            $this->sessionService->markMfaVerified($sessionId);
        }
        $this->auditService->log('auth.mfa.enrollment.completed', (string) $user->id, $sessionId ?: null, []);

        return $this->ok(['recovery_codes' => $recoveryCodes]);
    }

    public function mfaRecoveryCodesRegenerate(Request $request): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        assert($user instanceof TenantUser);

        $codes = $this->mfaService->regenerateRecoveryCodes($user);
        $this->auditService->log('auth.mfa.recovery_codes.regenerated', (string) $user->id, (string) $request->attributes->get('auth_session_id', ''), [], 'high');

        return $this->ok(['recovery_codes' => $codes]);
    }

    /**
     * @return string Plain token
     */
    private function issueAccessToken(TenantUser $user, string $sessionId): string
    {
        $accessToken = $user->createToken(
            name: 'access',
            abilities: ['*', 'session:'.$sessionId],
            expiresAt: now()->addMinutes((int) env('TENANT_ACCESS_TOKEN_TTL_MINUTES', 60)),
        )->plainTextToken;

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{id: string, name: string, email: string, source?: string}|null  $platformImpersonator
     */
    private function authUserPayload(
        TenantUser $user,
        ?TenantUser $impersonator = null,
        ?array $platformImpersonator = null,
    ): array {
        return app(TenantAuthUserPayloadBuilder::class)->build($user, $impersonator, $platformImpersonator);
    }
}

