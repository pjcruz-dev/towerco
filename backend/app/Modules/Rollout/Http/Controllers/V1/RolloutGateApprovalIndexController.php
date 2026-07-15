<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Services\RolloutGateApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGateApprovalIndexController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGateApprovalService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:in_review,approved,rejected,cancelled,all'],
            'mine' => ['sometimes', 'boolean'],
            'awaiting_me' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'max:64'],
        ]);

        $result = $service->index(
            $request->user(),
            $data['status'] ?? 'in_review',
            $request->boolean('mine'),
            $request->boolean('awaiting_me'),
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
            isset($data['sort']) ? (string) $data['sort'] : null,
        );

        return $this->ok($result);
    }
}
