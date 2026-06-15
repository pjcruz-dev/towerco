<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormService;
use App\Modules\EApproval\Services\EApprovalFormTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormTemplateStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalFormTemplateService $templates,
        EApprovalFormService $forms,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'template_id' => ['required', 'string', 'max:100'],
        ]);

        $result = $templates->createFromTemplate((string) $data['template_id'], $request->user(), $forms);

        return $this->created(['form' => $result['form']->toDetailPayload(), 'warnings' => $result['warnings']]);
    }
}
