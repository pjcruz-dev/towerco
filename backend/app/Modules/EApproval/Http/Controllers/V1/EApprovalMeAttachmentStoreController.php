<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMeAttachmentStoreController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalUserProfileService $profiles): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $data = $request->validate([
            'file' => ['required', 'file'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $attachment = $profiles->storeAttachment($request->user(), $data['file'], $data['metadata'] ?? null);

        return $this->ok(['id' => (string) $attachment->id], 201);
    }
}
