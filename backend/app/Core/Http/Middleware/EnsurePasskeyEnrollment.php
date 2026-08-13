<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\TenantPasskeysPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 4: when org policy is "require", block app APIs until the user enrolls a passkey.
 * Break-glass password-exempt users are skipped. Enrollment / auth housekeeping routes stay open.
 */
final class EnsurePasskeyEnrollment
{
    public function __construct(
        private readonly TenantPasskeysPolicyService $passkeysPolicy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof TenantUser) {
            return $next($request);
        }

        if (! $this->passkeysPolicy->isEnrollmentRequired($user)) {
            return $next($request);
        }

        if ($this->isExemptPath($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('Your organization requires a passkey. Enroll one under My security → Passkeys.'),
            'code' => 'passkey_enrollment_required',
        ], 403);
    }

    private function isExemptPath(Request $request): bool
    {
        return $request->is(
            'api/*/me',
            'api/*/auth/logout',
            'api/*/auth/logout-all',
            'api/*/auth/sessions',
            'api/*/auth/sessions/*',
            'api/*/auth/mfa/*',
            'api/*/auth/webauthn/*',
            'api/*/auth/impersonation/*',
            'api/*/workspace/environments',
            'api/*/workspace/environments/*',
        );
    }
}
