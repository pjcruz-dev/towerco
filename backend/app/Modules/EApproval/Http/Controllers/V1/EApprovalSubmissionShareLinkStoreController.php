<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionShareLinkStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionShareLinkService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);
        $service->assertCanManageShares($submission, $request->user());

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'ttl_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $created = $service->create(
            $submission,
            $request->user(),
            $data['label'] ?? null,
            isset($data['ttl_days']) ? (int) $data['ttl_days'] : null,
        );

        return $this->ok([
            'link' => $created['link'],
            'url' => $created['url'],
        ], 201);
    }
}
