<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformOperatorAdminService;
use Illuminate\Http\JsonResponse;

final class CentralPlatformOperatorIndexController extends AbstractApiController
{
    public function __invoke(PlatformOperatorAdminService $operators): JsonResponse
    {
        $rows = User::query()
            ->where('is_platform_admin', true)
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user): array => $operators->payload($user))
            ->values()
            ->all();

        return $this->ok($rows);
    }
}
