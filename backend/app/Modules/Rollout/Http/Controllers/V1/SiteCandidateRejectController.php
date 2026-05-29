<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Services\SiteCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCandidateRejectController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        SiteCandidate $candidate,
        SiteCandidateService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $data = $request->validate([
            'rejection_reason_code' => ['required', 'string', 'max:64'],
            'rejection_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        /** @var TenantUser $user */
        $user = $request->user();
        $updated = $service->reject(
            $candidate,
            $user,
            $data['rejection_reason_code'],
            $data['rejection_notes'] ?? null,
        );

        return $this->ok(['id' => $updated->id, 'status' => $updated->status]);
    }
}
