<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalExportHistoryIndexController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalReportService $reports): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $limit = (int) ($request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ])['limit'] ?? 50);

        $items = $reports->historyForUser($request->user(), $limit)
            ->map(fn ($row) => $reports->presentHistory($row))
            ->values()
            ->all();

        return $this->ok($items);
    }
}
