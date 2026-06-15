<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalInAppNotificationService;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EApprovalNotificationMarkAllReadController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalInAppNotificationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in([
                EApprovalNotificationCategory::ACTION,
                EApprovalNotificationCategory::UPDATE,
            ])],
        ]);

        $category = isset($validated['category']) ? (string) $validated['category'] : null;

        $service->markAllRead((string) $request->user()->id, $category);

        return $this->ok(['ok' => true]);
    }
}
