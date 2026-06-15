<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalPublicFormLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicFormLinkStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalPublicFormLinkService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sponsor_user_id' => ['required', 'uuid'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'max_submissions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:128'],
        ]);

        $result = $service->create($form, $data, $request->user());

        return $this->created($result);
    }
}
