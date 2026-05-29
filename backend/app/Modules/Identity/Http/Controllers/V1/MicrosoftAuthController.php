<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class MicrosoftAuthController extends AbstractApiController
{
    public function redirect(): RedirectResponse|JsonResponse
    {
        if (! config('services.azure.client_id') || ! config('services.azure.client_secret')) {
            return response()->json([
                'message' => __('Microsoft Entra ID is not configured.'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return Socialite::driver('azure')->stateless()->redirect();
    }

    public function callback(): JsonResponse
    {
        if (! config('services.azure.client_id') || ! config('services.azure.client_secret')) {
            return response()->json([
                'message' => __('Microsoft Entra ID is not configured.'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $socialUser = Socialite::driver('azure')->stateless()->user();

        return $this->ok([
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
        ]);
    }
}
