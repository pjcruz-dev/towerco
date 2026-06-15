<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Services\PlatformMfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformMfaVerified
{
    public function __construct(
        private readonly PlatformMfaService $mfa,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->mfa->isPolicyRequired()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $token = $user->token();
        if ($token === null) {
            abort(Response::HTTP_FORBIDDEN, __('MFA verification is required.'));
        }

        $verifiedAt = DB::table('oauth_access_tokens')
            ->where('id', $token->id)
            ->value('mfa_verified_at');

        if ($verifiedAt === null) {
            abort(Response::HTTP_FORBIDDEN, __('MFA verification is required.'));
        }

        return $next($request);
    }
}
