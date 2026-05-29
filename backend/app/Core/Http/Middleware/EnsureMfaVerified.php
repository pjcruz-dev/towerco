<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\Identity\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = (string) $request->attributes->get('auth_session_id', '');
        if ($sessionId === '') {
            return $next($request);
        }

        if ($request->is('api/*/auth/mfa/*')) {
            return $next($request);
        }

        $session = DB::connection('tenant')->table('auth_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            return response()->json(['message' => __('Session not found.')], 401);
        }

        if ($this->mfaService->isTenantMfaPolicyActive() && ! $session->mfa_verified_at) {
            return response()->json(['message' => __('MFA verification is required.')], 403);
        }

        return $next($request);
    }
}

