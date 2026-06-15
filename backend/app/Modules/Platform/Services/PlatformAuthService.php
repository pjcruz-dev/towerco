<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\PersonalAccessTokenResult;

final class PlatformAuthService
{
    public function __construct(
        private readonly PlatformMfaService $mfa,
        private readonly PlatformAuthAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function beginAuthenticatedSession(User $user, string $tokenName = 'TowerOS Platform Console'): array
    {
        $loginSession = $this->mfa->createLoginSession($user);

        if (! $this->mfa->isPolicyRequired()) {
            return $this->issueTokenResponse($user, $loginSession['id'], $tokenName);
        }

        $enrolled = $this->mfa->userHasVerifiedFactor($user);
        if (! $enrolled) {
            $this->audit->log('platform.auth.mfa.enrollment_required', $user);

            return [
                'mfa_required' => true,
                'mfa_enrollment_required' => true,
                'login_session_id' => $loginSession['id'],
                'login_session_expires_at' => $loginSession['expires_at'],
            ];
        }

        $challenge = $this->mfa->createChallenge($loginSession['id']);
        $this->audit->log('platform.auth.mfa.challenge.issued', $user, [
            'login_session_id' => $loginSession['id'],
        ]);

        return [
            'mfa_required' => true,
            'mfa_enrollment_required' => false,
            'login_session_id' => $loginSession['id'],
            'login_session_expires_at' => $loginSession['expires_at'],
            'mfa_challenge' => $challenge,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function completeMfaAndIssueToken(
        User $user,
        string $loginSessionId,
        string $challengeId,
        string $code,
        string $tokenName = 'TowerOS Platform Console',
    ): array {
        $this->mfa->resolveLoginSession($loginSessionId, (string) $user->id);
        $this->mfa->verifyChallenge($challengeId, $code);
        $this->audit->log('platform.auth.mfa.challenge.verified', $user, [], 'medium');

        return $this->issueTokenResponse($user, $loginSessionId, $tokenName);
    }

    /**
     * @return array<string, mixed>
     */
    public function completeRecoveryAndIssueToken(
        User $user,
        string $loginSessionId,
        string $recoveryCode,
        string $tokenName = 'TowerOS Platform Console',
    ): array {
        $this->mfa->verifyRecoveryCode($user, $loginSessionId, $recoveryCode);
        $this->audit->log('platform.auth.mfa.recovery.verified', $user, [], 'high');

        return $this->issueTokenResponse($user, $loginSessionId, $tokenName);
    }

    /**
     * @return array<string, mixed>
     */
    public function completeEnrollmentAndIssueToken(
        User $user,
        string $loginSessionId,
        string $code,
        string $tokenName = 'TowerOS Platform Console',
    ): array {
        $this->mfa->resolveLoginSession($loginSessionId, (string) $user->id);
        $recoveryCodes = $this->mfa->completeEnrollment($user, $code);

        DB::table('platform_login_sessions')
            ->where('id', $loginSessionId)
            ->update([
                'status' => 'mfa_verified',
                'mfa_verified_at' => now(),
                'updated_at' => now(),
            ]);

        $this->audit->log('platform.auth.mfa.enrollment.completed', $user);

        $response = $this->issueTokenResponse($user, $loginSessionId, $tokenName);
        $response['recovery_codes'] = $recoveryCodes;

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function issueTokenResponse(User $user, string $loginSessionId, string $tokenName): array
    {
        $tokenResult = $user->createToken($tokenName);
        $this->markTokenMfaVerified($tokenResult, $this->mfa->isPolicyRequired());
        $this->mfa->markLoginSessionConsumed($loginSessionId);
        $this->audit->log('platform.auth.login.succeeded', $user);

        return [
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ];
    }

    private function markTokenMfaVerified(PersonalAccessTokenResult $tokenResult, bool $policyRequired): void
    {
        DB::table('oauth_access_tokens')
            ->where('id', $tokenResult->token->id)
            ->update([
                'mfa_verified_at' => $policyRequired ? now() : now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_platform_admin' => $user->is_platform_admin,
            'platform_role' => $user->resolvePlatformRole(),
            'platform_permissions' => $user->platformPermissions(),
            'platform_mfa_enrolled' => $this->mfa->userHasVerifiedFactor($user),
            'platform_mfa_required' => $this->mfa->isPolicyRequired(),
        ];
    }
}
