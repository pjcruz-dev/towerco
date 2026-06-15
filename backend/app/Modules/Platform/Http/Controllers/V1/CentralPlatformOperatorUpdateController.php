<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformOperatorAdminService;
use App\Modules\Platform\Support\PlatformRoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CentralPlatformOperatorUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        User $user,
        PlatformOperatorAdminService $operators,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'platform_role' => ['sometimes', 'string', Rule::in(app(PlatformRoleCatalog::class)->roles())],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        return $this->ok($operators->payload($operators->update($user, $data, $actor)));
    }
}
