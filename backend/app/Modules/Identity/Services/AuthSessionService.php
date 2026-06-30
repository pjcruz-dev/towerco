<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthSessionService
{
    public function start(string $userId, string $authMethod = 'local'): string
    {
        return $this->startWithAuthMethod($userId, $authMethod);
    }

    public function startImpersonation(string $targetUserId): string
    {
        return $this->startWithAuthMethod($targetUserId, 'impersonation');
    }

    private function startWithAuthMethod(string $userId, string $authMethod): string
    {
        return (string) DB::connection('tenant')->transaction(function () use ($userId, $authMethod): string {
            $sessionId = (string) Str::uuid();
            $fingerprint = hash('sha256', request()->userAgent() ?? '');
            $now = now();
            $devicePayload = [
                'device_name' => substr((string) request()->userAgent(), 0, 180),
                'last_ip_address' => request()->ip(),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ];

            $existing = DB::connection('tenant')->table('auth_devices')
                ->where('user_id', $userId)
                ->where('device_fingerprint_hash', $fingerprint)
                ->first();

            if ($existing !== null) {
                $deviceId = (string) $existing->id;
                DB::connection('tenant')->table('auth_devices')
                    ->where('id', $deviceId)
                    ->update($devicePayload);
            } else {
                $deviceId = (string) Str::uuid();
                try {
                    DB::connection('tenant')->table('auth_devices')->insert([
                        'id' => $deviceId,
                        'user_id' => $userId,
                        'device_fingerprint_hash' => $fingerprint,
                        'device_name' => $devicePayload['device_name'],
                        'trust_level' => 'unknown',
                        'last_ip_address' => $devicePayload['last_ip_address'],
                        'last_seen_at' => $devicePayload['last_seen_at'],
                        'created_at' => $now,
                        'updated_at' => $devicePayload['updated_at'],
                    ]);
                } catch (QueryException $e) {
                    // Concurrent logins can race on the unique (user_id, device_fingerprint_hash).
                    if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                        throw $e;
                    }
                    $existing = DB::connection('tenant')->table('auth_devices')
                        ->where('user_id', $userId)
                        ->where('device_fingerprint_hash', $fingerprint)
                        ->first();
                    if ($existing === null) {
                        throw $e;
                    }
                    $deviceId = (string) $existing->id;
                    DB::connection('tenant')->table('auth_devices')
                        ->where('id', $deviceId)
                        ->update($devicePayload);
                }
            }

            DB::connection('tenant')->table('auth_sessions')->insert([
                'id' => $sessionId,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'auth_method' => $authMethod,
                'state' => 'active',
                'ip_address' => request()->ip(),
                'user_agent_hash' => hash('sha256', request()->userAgent() ?? ''),
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $sessionId;
        });
    }

    public function markMfaVerified(string $sessionId): void
    {
        DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->update([
                'mfa_verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revoke(string $sessionId): void
    {
        DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->update([
                'state' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revokeAllForUser(string $userId): void
    {
        DB::connection('tenant')->table('auth_sessions')
            ->where('user_id', $userId)
            ->where('state', 'active')
            ->update([
                'state' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
