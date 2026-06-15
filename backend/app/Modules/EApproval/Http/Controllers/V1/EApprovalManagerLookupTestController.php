<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalManagerLookupTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalManagerLookupTestController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalManagerLookupTestService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $validated = $request->validate([
            'email' => ['required', 'string', 'max:320'],
        ]);

        return $this->ok($service->preview((string) $validated['email']));
    }
}
