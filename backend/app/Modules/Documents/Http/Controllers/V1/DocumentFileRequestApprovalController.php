<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFileRequestApprovalController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Document $document,
        DocumentApprovalService $approvals,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $data = $request->validate([
            'form_id' => ['required', 'uuid'],
            'values' => ['sometimes', 'array'],
        ]);

        $payload = $approvals->requestApproval(
            $document,
            $data['form_id'],
            $request->user(),
            $data['values'] ?? [],
        );

        return $this->created($payload);
    }
}
