<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\UserUiPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserUiPreferenceSharedUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $key, UserUiPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'value' => ['required', 'array'],
        ]);

        $value = $validated['value'];

        if ($preferences->isDashboardLayoutKey($key)) {
            if (! isset($value['layout']) || ! is_array($value['layout'])) {
                throw ValidationException::withMessages([
                    'value.layout' => 'Shared dashboard layout payload must include a layout object.',
                ]);
            }
        } else {
            $request->validate([
                'value.layouts' => ['required', 'array', 'max:12'],
            ]);
        }

        $shared = $preferences->putShared($user, $key, $value);

        return $this->ok([
            'key' => $key,
            'shared' => $shared,
        ]);
    }
}
