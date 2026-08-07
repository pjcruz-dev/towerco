<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalPublicFormLinkService;
use App\Modules\EApproval\Services\EApprovalPublicSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicSubmissionStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $token,
        EApprovalPublicFormLinkService $links,
        EApprovalPublicSubmissionService $submissions,
    ): JsonResponse {
        $data = $request->validate([
            'access_password' => ['sometimes', 'nullable', 'string', 'max:128'],
            'submitter_name' => ['required', 'string', 'max:255'],
            'submitter_email' => ['required', 'email', 'max:255'],
            'values' => ['required', 'array'],
            // Client-selected files are uploaded after create using upload_token.
            'pending_attachment_counts' => ['sometimes', 'array'],
            'pending_attachment_counts.*' => ['integer', 'min:0', 'max:50'],
        ]);

        $link = $links->resolveActiveLink($token, $data['access_password'] ?? null);

        /** @var array<string, int> $pendingCounts */
        $pendingCounts = [];
        foreach ($data['pending_attachment_counts'] ?? [] as $fieldName => $count) {
            if (! is_string($fieldName) || $fieldName === '') {
                continue;
            }
            $pendingCounts[$fieldName] = max(0, (int) $count);
        }

        $result = $submissions->create(
            $link,
            $data['values'],
            trim($data['submitter_name']),
            strtolower(trim($data['submitter_email'])),
            $request->ip(),
            $request->userAgent(),
            $pendingCounts,
        );

        return $this->created($result);
    }
}
