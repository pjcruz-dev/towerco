<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformOperatorAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralPlatformOperatorDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        User $user,
        PlatformOperatorAdminService $operators,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $operators->delete($user, $actor);

        return $this->ok(['message' => __('Operator removed.')]);
    }
}
