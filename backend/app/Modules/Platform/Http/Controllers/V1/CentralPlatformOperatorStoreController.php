<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\PlatformOperatorAdminService;
use App\Modules\Platform\Support\PlatformRoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CentralPlatformOperatorStoreController extends AbstractApiController
{
    public function __invoke(Request $request, PlatformOperatorAdminService $operators): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'platform_role' => ['sometimes', 'string', Rule::in(app(PlatformRoleCatalog::class)->roles())],
        ]);

        $user = $operators->create($data);

        return $this->ok($operators->payload($user), 201);
    }
}
