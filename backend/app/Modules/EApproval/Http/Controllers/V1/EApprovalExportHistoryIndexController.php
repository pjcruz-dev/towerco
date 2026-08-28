<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalExportHistoryIndexController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $limit = (int) ($request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ])['limit'] ?? 50);

        $items = $reports->historyForUser($user, $limit)
            ->map(fn ($row) => $reports->presentHistory($row))
            ->values()
            ->all();

        return $this->ok($items);
    }
}
