<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Identity\Services\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformMfaService
{
    public function __construct(private readonly TotpService $totpService) {}

    public function isPolicyRequired(): bool
    {
        return (bool) config('toweros.platform_mfa.required', false);
    }

    public function userHasVerifiedFactor(User $user): bool
    {
        return DB::table('platform_mfa_factors')
            ->where('user_id', $user->id)
            ->whereNull('disabled_at')
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * @return array{id: string, expires_at: string}
     */
    public function createLoginSession(User $user): array
    {
        $sessionId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10);

        DB::table('platform_login_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'status' => 'pending_mfa',
            'expires_at' => $expiresAt,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $sessionId,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function resolveLoginSession(string $loginSessionId, string $userId): object
    {
        $session = DB::table('platform_login_sessions')
            ->where('id', $loginSessionId)
            ->where('user_id', $userId)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'login_session_id' => [__('Invalid login session.')],
            ]);
        }

        if ($session->consumed_at !== null) {
            throw ValidationException::withMessages([
                'login_session_id' => [__('Login session has already been used.')],
            ]);
        }

        if (now()->greaterThan($session->expires_at)) {
            throw ValidationException::withMessages([
                'login_session_id' => [__('Login session expired.')],
            ]);
        }

        return $session;
    }

    /**
     * @return array{id: string, expires_at: string}
     */
    public function createChallenge(string $loginSessionId): array
    {
        $session = DB::table('platform_login_sessions')->where('id', $loginSessionId)->first();
        if (! $session) {
            throw ValidationException::withMessages([
                'login_session_id' => [__('Invalid login session.')],
            ]);
        }

        $factor = DB::table('platform_mfa_factors')
            ->where('user_id', $session->user_id)
            ->whereNull('disabled_at')
            ->whereNotNull('verified_at')
            ->orderByDesc('is_primary')
            ->first();

        $challengeId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(5);

        DB::table('platform_mfa_challenges')->insert([
            'id' => $challengeId,
            'login_session_id' => $loginSessionId,
            'factor_id' => $factor?->id,
            'challenge_type' => 'totp',
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $challengeId,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function verifyChallenge(string $challengeId, string $code): string
    {
        $challenge = DB::table('platform_mfa_challenges')->where('id', $challengeId)->first();
        if (! $challenge) {
            throw ValidationException::withMessages(['challenge_id' => [__('Invalid MFA challenge.')]]);
        }

        if ($challenge->status !== 'pending') {
            throw ValidationException::withMessages(['challenge_id' => [__('MFA challenge is no longer active.')]]);
        }

        if (now()->greaterThan($challenge->expires_at)) {
            DB::table('platform_mfa_challenges')->where('id', $challengeId)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
            throw ValidationException::withMessages(['challenge_id' => [__('MFA challenge expired.')]]);
        }

        $valid = false;
        if ($challenge->factor_id) {
            $factor = DB::table('platform_mfa_factors')->where('id', $challenge->factor_id)->first();
            if ($factor && ! $factor->disabled_at) {
                $secret = decrypt((string) $factor->secret_encrypted);
                $valid = $this->totpService->verify($secret, $code);
            }
        }

        if (! $valid) {
            $attempts = (int) $challenge->attempts + 1;
            DB::table('platform_mfa_challenges')->where('id', $challengeId)->update([
                'attempts' => $attempts,
                'status' => $attempts >= (int) $challenge->max_attempts ? 'failed' : 'pending',
                'updated_at' => now(),
            ]);
            throw ValidationException::withMessages(['code' => [__('Invalid MFA code.')]]);
        }

        DB::table('platform_mfa_challenges')->where('id', $challengeId)->update([
            'status' => 'verified',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('platform_login_sessions')
            ->where('id', $challenge->login_session_id)
            ->update([
                'status' => 'mfa_verified',
                'mfa_verified_at' => now(),
                'updated_at' => now(),
            ]);

        return (string) $challenge->login_session_id;
    }

    public function verifyRecoveryCode(User $user, string $loginSessionId, string $code): void
    {
        $this->resolveLoginSession($loginSessionId, (string) $user->id);

        $hash = hash('sha256', strtoupper(trim($code)));
        $record = DB::table('platform_mfa_recovery_codes')
            ->where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages(['recovery_code' => [__('Invalid recovery code.')]]);
        }

        if ($record->expires_at && now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages(['recovery_code' => [__('Recovery code expired.')]]);
        }

        DB::table('platform_mfa_recovery_codes')
            ->where('id', $record->id)
            ->update(['used_at' => now(), 'updated_at' => now()]);

        DB::table('platform_login_sessions')
            ->where('id', $loginSessionId)
            ->update([
                'status' => 'mfa_verified',
                'mfa_verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{secret: string, otpauth_uri: string}
     */
    public function startEnrollment(User $user, string $issuer = 'TowerOS Central'): array
    {
        $secret = $this->totpService->generateSecret();
        $factorId = (string) Str::uuid();

        DB::table('platform_mfa_factors')->insert([
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
     * @return list<string>
     */
    public function completeEnrollment(User $user, string $code): array
    {
        $factor = DB::table('platform_mfa_factors')
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->whereNull('disabled_at')
            ->latest('created_at')
            ->first();

        if (! $factor) {
            throw ValidationException::withMessages(['code' => [__('No pending MFA enrollment found.')]]);
        }

        $secret = decrypt((string) $factor->secret_encrypted);
        if (! $this->totpService->verify($secret, $code)) {
            throw ValidationException::withMessages(['code' => [__('Invalid TOTP code.')]]);
        }

        DB::table('platform_mfa_factors')
            ->where('user_id', $user->id)
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('platform_mfa_factors')
            ->where('id', $factor->id)
            ->update([
                'is_primary' => true,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        DB::table('platform_mfa_recovery_codes')->where('user_id', $user->id)->delete();

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(Str::random(4).'-'.Str::random(4));
            $codes[] = $plain;
            DB::table('platform_mfa_recovery_codes')->insert([
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

    public function markLoginSessionConsumed(string $loginSessionId): void
    {
        DB::table('platform_login_sessions')
            ->where('id', $loginSessionId)
            ->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
