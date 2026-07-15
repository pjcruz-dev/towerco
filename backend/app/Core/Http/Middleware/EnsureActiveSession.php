<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\Identity\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        if (! $user instanceof TenantUser) {
            return $next($request);
        }

        $sessionId = $this->resolveSessionId($request, $user);
        if ($sessionId === null) {
            return response()->json(['message' => __('Session context missing.')], 401);
        }

        $session = DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', (string) $user->id)
            ->first();

        if (! $session || $session->state !== 'active' || $session->revoked_at !== null) {
            return response()->json(['message' => __('Session is no longer active.')], 401);
        }

        $now = now();

        DB::connection('tenant')->table('auth_sessions')->where('id', $sessionId)->update([
            'last_seen_at' => $now,
            'updated_at' => $now,
        ]);

        if ($session->device_id !== null) {
            DB::connection('tenant')->table('auth_devices')
                ->where('id', (string) $session->device_id)
                ->update([
                    'last_seen_at' => $now,
                    'last_ip_address' => $request->ip(),
                    'updated_at' => $now,
                ]);
        }

        $request->attributes->set('auth_session_id', $sessionId);

        return $next($request);
    }

    private function resolveSessionId(Request $request, TenantUser $user): ?string
    {
        $token = $user->currentAccessToken();
        if ($token !== null) {
            $sessionAbility = collect($token->abilities)
                ->first(fn (string $ability) => str_starts_with($ability, 'session:'));

            if (is_string($sessionAbility)) {
                return substr($sessionAbility, strlen('session:'));
            }
        }

        $headerSessionId = trim((string) $request->header('X-Session-Id', ''));
        if ($headerSessionId === '' || ! Str::isUuid($headerSessionId)) {
            return null;
        }

        $existsForUser = DB::connection('tenant')->table('auth_sessions')
            ->where('id', $headerSessionId)
            ->where('user_id', (string) $user->id)
            ->exists();

        return $existsForUser ? $headerSessionId : null;
    }
}
