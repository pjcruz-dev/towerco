<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalAnalyticsShowController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalAnalyticsService $analytics): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'form_id' => ['sometimes', 'nullable', 'uuid'],
            'subsidiary' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $formId = trim((string) ($validated['form_id'] ?? ''));
        $subsidiary = trim((string) ($validated['subsidiary'] ?? ''));
        $department = trim((string) ($validated['department'] ?? ''));

        return $this->ok($analytics->build(
            isset($validated['from']) ? (string) $validated['from'] : null,
            isset($validated['to']) ? (string) $validated['to'] : null,
            $formId !== '' ? $formId : null,
            $subsidiary !== '' ? $subsidiary : null,
            $department !== '' ? $department : null,
        ));
    }
}
