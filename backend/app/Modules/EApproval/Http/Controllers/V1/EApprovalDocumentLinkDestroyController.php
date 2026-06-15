<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalDocumentLink;
use App\Modules\EApproval\Services\EApprovalDocumentLinkService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDocumentLinkDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalDocumentLink $link,
        EApprovalDocumentLinkService $documentLinks,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $link->loadMissing('sourceSubmission');
        $source = $link->sourceSubmission;
        if ($source === null) {
            abort(404);
        }

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($source, $request->user(), $canViewAll);

        $documentLinks->delete($link, $request->user());

        return $this->ok([
            'ok' => true,
            'document_links' => $documentLinks->listOutgoing($source),
            'incoming_document_links' => $documentLinks->listIncoming($source),
        ]);
    }
}
