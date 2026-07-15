<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserActivityService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantUserActivityIndexController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUser $user, TenantUserActivityService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        return $this->ok($service->listForUser($user, $limit));
    }
}
