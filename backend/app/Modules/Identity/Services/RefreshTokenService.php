<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefreshTokenService
{
    /**
     * @return array{token: string, family_id: string}
     */
    public function issue(string $userId, string $sessionId): array
    {
        $familyId = (string) Str::uuid();
        $token = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $token);

        DB::table('refresh_token_families')->insert([
            'id' => $familyId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('refresh_tokens')->insert([
            'id' => (string) Str::uuid(),
            'family_id' => $familyId,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays((int) env('TENANT_REFRESH_TOKEN_TTL_DAYS', 30)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $token, 'family_id' => $familyId];
    }

    /**
     * @return array{user: TenantUser, token: string, family_id: string, session_id: string}
     */
    public function rotate(string $rawToken): array
    {
        $tokenHash = hash('sha256', $rawToken);

        $refreshToken = DB::table('refresh_tokens')->where('token_hash', $tokenHash)->first();
        if (! $refreshToken) {
            throw ValidationException::withMessages(['refresh_token' => [__('Invalid refresh token.')]]);
        }

        if ($refreshToken->revoked_at !== null || $refreshToken->used_at !== null) {
            $this->revokeFamily((string) $refreshToken->family_id);
            throw ValidationException::withMessages(['refresh_token' => [__('Refresh token reuse detected.')]]);
        }

        if (now()->greaterThan($refreshToken->expires_at)) {
            $this->revokeFamily((string) $refreshToken->family_id);
            throw ValidationException::withMessages(['refresh_token' => [__('Refresh token has expired.')]]);
        }

        $family = DB::table('refresh_token_families')->where('id', $refreshToken->family_id)->first();
        if (! $family || $family->state !== 'active') {
            throw ValidationException::withMessages(['refresh_token' => [__('Refresh token family is inactive.')]]);
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::query()->find((string) $family->user_id);
        if (! $user) {
            $this->revokeFamily((string) $family->id);
            throw ValidationException::withMessages(['refresh_token' => [__('User no longer exists.')]]);
        }

        DB::table('refresh_tokens')
            ->where('id', $refreshToken->id)
            ->update([
                'used_at' => now(),
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $nextToken = bin2hex(random_bytes(48));
        $nextTokenHash = hash('sha256', $nextToken);
        DB::table('refresh_tokens')->insert([
            'id' => (string) Str::uuid(),
            'family_id' => (string) $family->id,
            'token_hash' => $nextTokenHash,
            'rotated_from_id' => (string) $refreshToken->id,
            'expires_at' => now()->addDays((int) env('TENANT_REFRESH_TOKEN_TTL_DAYS', 30)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'user' => $user,
            'token' => $nextToken,
            'family_id' => (string) $family->id,
            'session_id' => (string) $family->session_id,
        ];
    }

    public function revokeFamily(string $familyId): void
    {
        DB::table('refresh_token_families')
            ->where('id', $familyId)
            ->update([
                'state' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('refresh_tokens')
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $familyIds = DB::table('refresh_token_families')
            ->where('user_id', $userId)
            ->where('state', 'active')
            ->pluck('id');

        foreach ($familyIds as $familyId) {
            $this->revokeFamily((string) $familyId);
        }
    }
}

