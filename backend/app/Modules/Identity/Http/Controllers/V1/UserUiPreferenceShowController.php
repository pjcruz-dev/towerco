<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\UserUiPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserUiPreferenceShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $key, UserUiPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $bundle = $preferences->getWithShared($user, $key);

        return $this->ok([
            'key' => $key,
            'value' => $bundle['value'],
            'shared' => $bundle['shared'],
        ]);
    }
}
