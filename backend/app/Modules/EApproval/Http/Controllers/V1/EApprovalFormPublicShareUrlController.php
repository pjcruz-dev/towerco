<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalPublicFormLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormPublicShareUrlController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalPublicFormLinkService $service,
    ): JsonResponse {
        $user = $request->user();
        abort_unless(
            $user?->can('e_approval:submissions:create') || $user?->can('e_approval:forms:manage'),
            403,
        );

        if ($form->status !== 'published') {
            abort(404);
        }

        return $this->ok($service->primaryShareUrl($form));
    }
}
