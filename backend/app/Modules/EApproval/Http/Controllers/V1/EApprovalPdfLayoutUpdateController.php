<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalPdfLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPdfLayoutUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $formId, EApprovalPdfLayoutService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $payload = $request->validate([
            'layout' => ['sometimes', 'array'],
            'template' => ['sometimes', 'array'],
            'active_preset_id' => ['sometimes', 'string', 'max:80'],
            'presets' => ['sometimes', 'array'],
        ]);

        $service->save($formId, $payload, $request->user());

        return $this->ok(['ok' => true]);
    }
}
