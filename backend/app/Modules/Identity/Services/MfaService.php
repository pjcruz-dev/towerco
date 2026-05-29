<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MfaService
{
    public function __construct(private readonly TotpService $totpService) {}

    /**
     * Global gate (env) plus per-tenant policy (central `tenants.mfa_required`).
     */
    public function isTenantMfaPolicyActive(): bool
    {
        if (! (bool) env('TENANT_MFA_REQUIRED', true)) {
            return false;
        }

        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            return true;
        }

        /** @var Tenant|null $record */
        $record = Tenant::query()->find((string) $tenantKey);

        return (bool) ($record?->mfa_required ?? true);
    }

    public function isMfaRequired(TenantUser $user): bool
    {
        return $this->isTenantMfaPolicyActive();
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
            if ($factor && ! $factor->disabled_at) {
                $secret = decrypt((string) $factor->secret_encrypted);
                $valid = $this->totpService->verify($secret, $code);
            }
        } elseif ($challenge->code_hash) {
            $valid = hash_equals((string) $challenge->code_hash, hash('sha256', $code));
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

        $secret = decrypt((string) $factor->secret_encrypted);
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

