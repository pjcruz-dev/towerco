<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalDocumentLinkService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDocumentLinkStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalDocumentLinkService $documentLinks,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $data = $request->validate([
            'target_submission_id' => ['required', 'uuid'],
            'link_type' => ['sometimes', 'string', 'max:80'],
        ]);

        /** @var EApprovalSubmission $target */
        $target = EApprovalSubmission::query()->findOrFail($data['target_submission_id']);
        $submissions->assertCanView($target, $request->user(), $canViewAll);

        $link = $documentLinks->create(
            $submission,
            $data['target_submission_id'],
            $data['link_type'] ?? 'related',
            $request->user(),
        );

        return $this->created([
            'link' => $documentLinks->toOutgoingRow($link),
            'document_links' => $documentLinks->listOutgoing($submission),
            'incoming_document_links' => $documentLinks->listIncoming($submission),
        ]);
    }
}
