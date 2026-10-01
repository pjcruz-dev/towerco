<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\MeRelatedRecordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeRelatedRecordsController extends AbstractApiController
{
    public function __invoke(Request $request, MeRelatedRecordsService $records): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        return $this->ok($records->forUser($user));
    }
}
