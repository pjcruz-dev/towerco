<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthSessionService;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\RefreshTokenService;
use App\Modules\Identity\Services\TenantAuthUserPayloadBuilder;
use App\Modules\Identity\Services\TenantPasskeysPolicyService;
use App\Modules\Identity\Services\WebAuthnPasskeyService;
use App\Modules\Identity\Support\WebAuthnRelyingParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebAuthn passkey register / login / list / revoke APIs.
 */
final class TenantWebAuthnController extends AbstractApiController
{
    public function __construct(
        private readonly WebAuthnPasskeyService $passkeys,
        private readonly WebAuthnRelyingParty $relyingParty,
        private readonly TenantPasskeysPolicyService $policy,
        private readonly AuthSessionService $sessionService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly MfaService $mfaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var TenantUser $user */
        $user = $request->user();

        return $this->ok([
            'enabled' => $this->policy->isEnabled(),
            'policy' => $this->policy->policyMode(),
            'satisfies_mfa' => $this->policy->passkeySatisfiesMfa(),
            'credentials' => $this->passkeys->listForUser($user),
            'rp_id' => $this->relyingParty->rpId(),
            'enrollment_required' => $this->policy->isEnrollmentRequired($user),
        ]);
    }

    public function registerOptions(Request $request): JsonResponse
    {
        $this->policy->assertEnabled();

        /** @var TenantUser $user */
        $user = $request->user();
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->ok($this->passkeys->beginRegistration($user, $data['label'] ?? null));
    }

    public function registerVerify(Request $request): JsonResponse
    {
        $this->policy->assertEnabled();

        /** @var TenantUser $user */
        $user = $request->user();
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'credential' => ['required', 'array'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $row = $this->passkeys->completeRegistration(
            $user,
            $data['challenge_id'],
            $data['credential'],
            $data['label'] ?? null,
        );

        return $this->ok(['credential' => $row->toPublicRow()]);
    }

    public function destroy(Request $request, string $credentialId): JsonResponse
    {
        /** @var TenantUser $user */
        $user = $request->user();
        $this->passkeys->revoke($user, $credentialId);

        return $this->ok(['revoked' => true]);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $this->policy->assertEnabled();

        $data = $request->validate([
            'email' => ['nullable', 'email:rfc'],
        ]);

        return $this->ok($this->passkeys->beginLogin($data['email'] ?? null));
    }

    public function loginVerify(Request $request): JsonResponse
    {
        $this->policy->assertEnabled();

        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'credential' => ['required', 'array'],
        ]);

        $result = $this->passkeys->completeLogin($data['challenge_id'], $data['credential']);
        $user = $result['user'];

        $sessionId = $this->sessionService->start((string) $user->id, 'webauthn');
        $accessToken = $user->createToken(
            name: 'access',
            abilities: ['*', 'session:'.$sessionId],
            expiresAt: now()->addMinutes((int) env('TENANT_ACCESS_TOKEN_TTL_MINUTES', 60)),
        )->plainTextToken;
        $refresh = $this->refreshTokenService->issue((string) $user->id, $sessionId);

        // Phase 4: passkey may satisfy MFA when org policy allows.
        $mfaState = $this->mfaService->resolveLoginMfaState($user, $sessionId, 'webauthn');
        if ($mfaState['mark_verified']) {
            $this->sessionService->markMfaVerified($sessionId);
        }

        $passkeyFlags = $this->policy->loginFlags($user);

        return $this->ok([
            'access_token' => $accessToken,
            'refresh_token' => $refresh['token'],
            'session_id' => $sessionId,
            'mfa_required' => $mfaState['mfa_required'],
            'mfa_enrollment_required' => $mfaState['mfa_enrollment_required'],
            'mfa_challenge' => $mfaState['mfa_challenge'],
            'auth_method' => 'webauthn',
            'user' => app(TenantAuthUserPayloadBuilder::class)->build($user),
            ...$passkeyFlags,
        ]);
    }
}
