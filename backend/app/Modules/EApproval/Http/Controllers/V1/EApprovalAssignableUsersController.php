<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAssignableUsersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalAssignableUsersController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalAssignableUsersService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('e_approval:submissions:create')
            || $request->user()?->can('e_approval:forms:manage')
            || $request->user()?->can('e_approval:approve'),
            403,
        );

        return $this->ok($service->listForPickers());
    }
}
