<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Models\User;
use App\Modules\Platform\Services\PlatformAuthService;
use App\Modules\Platform\Services\PlatformEntraRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

final class CentralPlatformMicrosoftAuthController
{
    public function redirect(): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (! $this->azureConfigured()) {
            return response()->json([
                'message' => __('Microsoft Entra ID is not configured.'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return Socialite::driver('azure')->stateless()->redirect();
    }

    public function callback(PlatformEntraRoleResolver $entraRoles, PlatformAuthService $auth): RedirectResponse
    {
        $frontendBase = rtrim((string) config('toweros.platform_auth.microsoft_callback_frontend', env('FRONTEND_APP_URL', 'http://localhost')), '/');
        $errorRedirect = $frontendBase.'/platform/login';

        if (! $this->azureConfigured()) {
            return redirect($errorRedirect.'?sso_error='.urlencode(__('Microsoft Entra ID is not configured.')));
        }

        try {
            $socialUser = Socialite::driver('azure')->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('platform.microsoft.callback_failed', ['message' => $e->getMessage()]);

            return redirect($errorRedirect.'?sso_error='.urlencode(__('Microsoft sign-in failed.')));
        }

        $email = strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            return redirect($errorRedirect.'?sso_error='.urlencode(__('Microsoft account has no email.')));
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        $raw = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];
        $groupIds = $entraRoles->extractGroupIds(is_array($raw) ? ($raw['groups'] ?? []) : []);
        $mappedRole = $entraRoles->resolveRoleFromGroups($groupIds);

        if ($user === null) {
            if ($mappedRole === null || ! (bool) config('toweros.platform_auth.entra_auto_provision', false)) {
                return redirect($errorRedirect.'?sso_error='.urlencode(__('No platform operator account is linked to this Microsoft identity.')));
            }

            $user = User::query()->create([
                'name' => (string) ($socialUser->getName() ?: $email),
                'email' => $email,
                'password' => bcrypt(str()->random(32)),
                'is_platform_admin' => true,
                'platform_role' => $mappedRole,
            ]);
        }

        if (! $user->isPlatformAdmin()) {
            return redirect($errorRedirect.'?sso_error='.urlencode(__('No platform operator account is linked to this Microsoft identity.')));
        }

        if ($mappedRole !== null) {
            $user->platform_role = $mappedRole;
            $user->save();
        }

        $session = $auth->beginAuthenticatedSession($user, 'TowerOS Platform Console (Microsoft)');

        $handoff = base64_encode(json_encode($session, JSON_THROW_ON_ERROR));

        return redirect($frontendBase.'/platform/login/microsoft-callback#'.$handoff);
    }

    private function azureConfigured(): bool
    {
        return (bool) config('services.azure.client_id')
            && (bool) config('services.azure.client_secret');
    }
}
