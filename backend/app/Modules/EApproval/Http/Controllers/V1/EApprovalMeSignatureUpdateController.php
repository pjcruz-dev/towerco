<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMeSignatureUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalUserProfileService $profiles): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $data = $request->validate([
            'signature' => ['nullable', 'string', 'max:500000'],
        ]);

        $profiles->updateSignature($request->user(), $data['signature'] ?? null, $request->user());

        return $this->ok(['ok' => true]);
    }
}
