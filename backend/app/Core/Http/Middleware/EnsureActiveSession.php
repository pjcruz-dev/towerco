<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if (! $token) {
            return $next($request);
        }

        $sessionAbility = collect($token->abilities)
            ->first(fn (string $ability) => str_starts_with($ability, 'session:'));

        if (! is_string($sessionAbility)) {
            return response()->json(['message' => __('Session context missing.')], 401);
        }

        $sessionId = substr($sessionAbility, strlen('session:'));
        $session = DB::connection('tenant')->table('auth_sessions')->where('id', $sessionId)->first();
        if (! $session || $session->state !== 'active') {
            return response()->json(['message' => __('Session is no longer active.')], 401);
        }

        DB::connection('tenant')->table('auth_sessions')->where('id', $sessionId)->update([
            'last_seen_at' => now(),
            'updated_at' => now(),
        ]);

        $request->attributes->set('auth_session_id', $sessionId);

        return $next($request);
    }
}

