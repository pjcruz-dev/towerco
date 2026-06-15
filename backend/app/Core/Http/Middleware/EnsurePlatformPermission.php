<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Support\PlatformRoleCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformPermission
{
    public function __construct(
        private readonly PlatformRoleCatalog $roles,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isPlatformAdmin()) {
            abort(Response::HTTP_FORBIDDEN, __('Platform administrator access required.'));
        }

        $role = $user->resolvePlatformRole();

        if ($role === PlatformRoleCatalog::ROLE_SUPERADMIN) {
            return $next($request);
        }

        if (! $this->roles->roleHasPermission($role, $permission)) {
            abort(Response::HTTP_FORBIDDEN, __('You do not have permission to perform this action.'));
        }

        return $next($request);
    }
}
