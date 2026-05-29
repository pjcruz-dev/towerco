<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isPlatformAdmin()) {
            abort(Response::HTTP_FORBIDDEN, __('Platform administrator access required.'));
        }

        return $next($request);
    }
}
