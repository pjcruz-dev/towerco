<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalUserAttachment;
use App\Modules\EApproval\Services\EApprovalUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalMeAttachmentDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalUserAttachment $attachment, EApprovalUserProfileService $profiles): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $profiles->deleteAttachment($attachment, $request->user());

        return $this->ok(['ok' => true]);
    }
}
