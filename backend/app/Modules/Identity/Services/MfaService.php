<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\MfaSecretCipher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MfaService
{
    public function __construct(private readonly TotpService $totpService) {}

    /**
     * Global gate (config) plus per-tenant policy (central `tenants.mfa_required`).
     */
    public function isTenantMfaPolicyActive(): bool
    {
        if (! (bool) config('toweros.tenant_mfa.global_required', false)) {
            return false;
        }

        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            return false;
        }

        return $this->tenantRequiresMfa((string) $tenantKey);
    }

    public function forgetTenantPolicyCache(string $tenantId): void
    {
        Cache::forget($this->tenantPolicyCacheKey($tenantId));
    }

    private function tenantRequiresMfa(string $tenantId): bool
    {
        return (bool) Cache::remember(
            $this->tenantPolicyCacheKey($tenantId),
            300,
            static function () use ($tenantId): bool {
                /** @var Tenant|null $record */
                $record = Tenant::query()->find($tenantId);

                return (bool) ($record?->mfa_required ?? false);
            },
        );
    }

    private function tenantPolicyCacheKey(string $tenantId): string
    {
        return 'toweros:tenant:mfa_required:'.$tenantId;
    }

    public function userHasVerifiedFactor(TenantUser $user): bool
    {
        return DB::table('mfa_factors')
            ->where('user_id', $user->id)
            ->whereNull('disabled_at')
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * Challenge MFA when org policy requires it, or when this user already enrolled TOTP.
     * Personal enrollment must protect the account even if org-wide MFA is optional.
     */
    public function isMfaRequired(TenantUser $user): bool
    {
        if ($this->isTenantMfaPolicyActive()) {
            return true;
        }

        return $this->userHasVerifiedFactor($user);
    }

    /**
     * API middleware gate: session must complete MFA when policy or enrolled factor applies.
     */
    public function sessionRequiresMfaVerification(TenantUser $user): bool
    {
        return $this->isMfaRequired($user);
    }

    /**
     * How long a browser/device stays trusted after a successful MFA challenge.
     * 0 = challenge on every sign-in. Clamped to 0–90 days.
     */
    public function trustDays(): int
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            return (int) config('toweros.tenant_mfa.default_trust_days', 7);
        }

        return (int) Cache::remember(
            $this->tenantTrustDaysCacheKey((string) $tenantKey),
            300,
            static function () use ($tenantKey): int {
                /** @var Tenant|null $record */
                $record = Tenant::query()->find($tenantKey);
                $days = $record?->mfa_trust_days;
                if ($days === null) {
                    $days = (int) config('toweros.tenant_mfa.default_trust_days', 7);
                }

                return max(0, min(90, (int) $days));
            },
        );
    }

    public function forgetTenantTrustDaysCache(string $tenantId): void
    {
        Cache::forget($this->tenantTrustDaysCacheKey($tenantId));
    }

    private function tenantTrustDaysCacheKey(string $tenantId): string
    {
        return 'toweros:tenant:mfa_trust_days:'.$tenantId;
    }

    public function currentDeviceFingerprint(): string
    {
        return hash('sha256', (string) (request()->userAgent() ?? ''));
    }

    public function currentDeviceIsTrusted(string $userId): bool
    {
        if ($this->trustDays() <= 0) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('auth_devices', 'mfa_trusted_until')) {
            return false;
        }

        $device = DB::connection('tenant')->table('auth_devices')
            ->where('user_id', $userId)
            ->where('device_fingerprint_hash', $this->currentDeviceFingerprint())
            ->first();

        if ($device === null || empty($device->mfa_trusted_until)) {
            return false;
        }

        return now()->lessThanOrEqualTo($device->mfa_trusted_until);
    }

    public function trustCurrentDevice(string $userId): void
    {
        $days = $this->trustDays();
        if ($days <= 0) {
            return;
        }

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('auth_devices', 'mfa_trusted_until')) {
            return;
        }

        $until = now()->addDays($days);
        DB::connection('tenant')->table('auth_devices')
            ->where('user_id', $userId)
            ->where('device_fingerprint_hash', $this->currentDeviceFingerprint())
            ->update([
                'trust_level' => 'mfa',
                'mfa_trusted_until' => $until,
                'updated_at' => now(),
            ]);
    }

    /**
     * Decide whether login should challenge MFA, force enrollment, or skip via device trust.
     *
     * @return array{
     *     mfa_required: bool,
     *     mfa_enrollment_required: bool,
     *     mfa_challenge: array{id: string, expires_at: string}|null,
     *     mark_verified: bool
     * }
     */
    public function resolveLoginMfaState(TenantUser $user, string $sessionId): array
    {
        $policyOrEnrolled = $this->isMfaRequired($user);
        if (! $policyOrEnrolled) {
            return [
                'mfa_required' => false,
                'mfa_enrollment_required' => false,
                'mfa_challenge' => null,
                'mark_verified' => true,
            ];
        }

        $enrolled = $this->userHasVerifiedFactor($user);
        if (! $enrolled) {
            return [
                'mfa_required' => true,
                'mfa_enrollment_required' => true,
                'mfa_challenge' => $this->createChallenge($sessionId),
                'mark_verified' => false,
            ];
        }

        if ($this->currentDeviceIsTrusted((string) $user->id)) {
            return [
                'mfa_required' => false,
                'mfa_enrollment_required' => false,
                'mfa_challenge' => null,
                'mark_verified' => true,
            ];
        }

        return [
            'mfa_required' => true,
            'mfa_enrollment_required' => false,
            'mfa_challenge' => $this->createChallenge($sessionId),
            'mark_verified' => false,
        ];
    }

    /**
     * @return array{id: string, expires_at: string}
     */
    public function createChallenge(string $sessionId): array
    {
        $challengeId = (string) Str::uuid();
        $session = DB::table('auth_sessions')->where('id', $sessionId)->first();
        $factorId = null;
        if ($session) {
            $primaryFactor = DB::table('mfa_factors')
                ->where('user_id', $session->user_id)
                ->whereNull('disabled_at')
                ->whereNotNull('verified_at')
                ->orderByDesc('is_primary')
                ->first();
            $factorId = $primaryFactor?->id;
        }

        DB::table('mfa_challenges')->insert([
            'id' => $challengeId,
            'session_id' => $sessionId,
            'factor_id' => $factorId,
            'challenge_type' => 'totp',
            'code_hash' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $challengeId,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ];
    }

    public function verifyChallenge(string $challengeId, string $code): void
    {
        $challenge = DB::table('mfa_challenges')->where('id', $challengeId)->first();
        if (! $challenge) {
            throw ValidationException::withMessages(['challenge_id' => [__('Invalid MFA challenge.')]]);
        }

        if ($challenge->status !== 'pending') {
            throw ValidationException::withMessages(['challenge_id' => [__('MFA challenge is no longer active.')]]);
        }

        if (now()->greaterThan($challenge->expires_at)) {
            DB::table('mfa_challenges')->where('id', $challengeId)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
            throw ValidationException::withMessages(['challenge_id' => [__('MFA challenge expired.')]]);
        }

        $valid = false;
        if ($challenge->factor_id) {
            $factor = DB::table('mfa_factors')->where('id', $challenge->factor_id)->first();
            if (! $factor || $factor->disabled_at) {
                throw ValidationException::withMessages([
                    'code' => [__(
                        'No verified authenticator is available for this sign-in. Complete MFA enrollment or use a recovery code.'
                    )],
                ]);
            }

            $secret = MfaSecretCipher::decryptOrFail((string) $factor->secret_encrypted, 'tenant.mfa.verify');
            $valid = $this->totpService->verify($secret, $code);
        } elseif ($challenge->code_hash) {
            $valid = hash_equals((string) $challenge->code_hash, hash('sha256', $code));
        } else {
            throw ValidationException::withMessages([
                'code' => [__(
                    'No verified authenticator is available for this sign-in. Complete MFA enrollment or use a recovery code.'
                )],
            ]);
        }

        if (! $valid) {
            $attempts = (int) $challenge->attempts + 1;
            DB::table('mfa_challenges')->where('id', $challengeId)->update([
                'attempts' => $attempts,
                'status' => $attempts >= (int) $challenge->max_attempts ? 'failed' : 'pending',
                'updated_at' => now(),
            ]);
            throw ValidationException::withMessages(['code' => [__('Invalid MFA code.')]]);
        }

        DB::table('mfa_challenges')->where('id', $challengeId)->update([
            'status' => 'verified',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{secret: string, otpauth_uri: string}
     */
    public function startEnrollment(TenantUser $user, string $issuer = 'TowerOS'): array
    {
        $secret = $this->totpService->generateSecret();
        $factorId = (string) Str::uuid();

        DB::table('mfa_factors')->insert([
            'id' => $factorId,
            'user_id' => $user->id,
            'type' => 'totp',
            'secret_encrypted' => encrypt($secret),
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $label = rawurlencode(sprintf('%s:%s', $issuer, $user->email));
        $issuerParam = rawurlencode($issuer);
        $uri = sprintf('otpauth://totp/%s?secret=%s&issuer=%s', $label, $secret, $issuerParam);

        return ['secret' => $secret, 'otpauth_uri' => $uri];
    }

    /**
     * @return list<string> Plain recovery codes
     */
    public function completeEnrollment(TenantUser $user, string $code): array
    {
        $factor = DB::table('mfa_factors')
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->whereNull('disabled_at')
            ->latest('created_at')
            ->first();

        if (! $factor) {
            throw ValidationException::withMessages(['code' => [__('No pending MFA enrollment found.')]]);
        }

        $secret = MfaSecretCipher::decryptOrFail((string) $factor->secret_encrypted, 'tenant.mfa.enroll');
        if (! $this->totpService->verify($secret, $code)) {
            throw ValidationException::withMessages(['code' => [__('Invalid TOTP code.')]]);
        }

        DB::table('mfa_factors')
            ->where('user_id', $user->id)
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('mfa_factors')
            ->where('id', $factor->id)
            ->update([
                'is_primary' => true,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * @return list<string> Plain recovery codes
     */
    public function regenerateRecoveryCodes(TenantUser $user): array
    {
        DB::table('mfa_recovery_codes')
            ->where('user_id', $user->id)
            ->delete();

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(Str::random(4).'-'.Str::random(4));
            $codes[] = $plain;
            DB::table('mfa_recovery_codes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $plain),
                'expires_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $codes;
    }

    public function verifyRecoveryCode(TenantUser $user, string $code): bool
    {
        $hash = hash('sha256', strtoupper(trim($code)));
        $record = DB::table('mfa_recovery_codes')
            ->where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $record) {
            return false;
        }

        if ($record->expires_at && now()->greaterThan($record->expires_at)) {
            return false;
        }

        DB::table('mfa_recovery_codes')
            ->where('id', $record->id)
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

        return true;
    }
}

