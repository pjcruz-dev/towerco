<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserIndexFilters;
use App\Modules\AdminOne\Services\TenantUserIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserIdsController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, TenantUserIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $query = $this->validatedTenantListQuery($request);
        $filters = TenantUserIndexFilters::fromRequest($request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive,all'],
            'last_active' => ['sometimes', 'string', 'in:all,7d,30d,90d,never'],
            'mfa' => ['sometimes', 'string', 'in:all,enrolled,not_enrolled'],
            'role' => ['sometimes', 'string', 'max:64'],
        ]));

        return $this->ok($service->ids($query['search'], $filters, $query['sort']));
    }
}
