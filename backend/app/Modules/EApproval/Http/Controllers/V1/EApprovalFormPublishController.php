<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\FormPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormPublishController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalForm $form, FormPublishService $publish): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $published = $publish->publish($form, $request->user());

        return $this->ok($published->toDetailPayload());
    }
}
