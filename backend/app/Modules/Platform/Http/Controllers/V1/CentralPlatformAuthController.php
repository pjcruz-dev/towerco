<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformAuthAuditService;
use App\Modules\Platform\Services\PlatformAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CentralPlatformAuthController extends AbstractApiController
{
    public function login(
        Request $request,
        PlatformAuthService $auth,
        PlatformAuthAuditService $audit,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $audit->log('platform.auth.login.failed', null, ['email' => $data['email']], 'medium');
            throw ValidationException::withMessages([
                'email' => [__('Invalid credentials.')],
            ]);
        }

        if (! $user->isPlatformAdmin()) {
            abort(403, __('Platform administrator access required.'));
        }

        return $this->ok($auth->beginAuthenticatedSession($user));
    }

    public function me(Request $request, PlatformAuthService $auth): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok($auth->userPayload($user));
    }
}
