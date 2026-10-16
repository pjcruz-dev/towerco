<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentSavedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentSavedReportIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentSavedReportService $reports,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessRegistry($user), 403);

        return $this->ok(
            $reports->listForUser($user)
                ->map(static fn ($report): array => $reports->present($report))
                ->values()
                ->all(),
        );
    }
}
