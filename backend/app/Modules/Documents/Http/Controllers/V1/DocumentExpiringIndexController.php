<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentExpiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentExpiringIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DocumentExpiringService $expiring): JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        $days = (int) $request->query('days', 90);
        $days = max(1, min(365, $days));

        return $this->ok([
            'summary' => $expiring->summaryCounts(),
            'items' => $expiring->list($days),
        ]);
    }
}
