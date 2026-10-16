<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ControlledDocumentReportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        $data = $request->validate([
            'period' => ['sometimes', 'nullable', 'integer', Rule::in([30, 90, 365])],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete'])],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'review' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'due_30', 'due_90', 'none'])],
            'file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
            'published_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'published_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:published_from'],
        ]);

        return $this->ok($registry->report($user, $data));
    }
}
