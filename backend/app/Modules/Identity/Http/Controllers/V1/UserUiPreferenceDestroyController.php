<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\UserUiPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserUiPreferenceDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $key, UserUiPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $preferences->clear($user, $key);

        return $this->ok([
            'key' => $key,
            'value' => null,
        ]);
    }
}
