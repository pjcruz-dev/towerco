<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalPublicSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicRevisionAttachmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalPublicSubmissionService $submissions,
    ): JsonResponse {
        $maxKb = (int) config('toweros.tenant_files.max_size_kb', 25600);
        $data = $request->validate([
            'upload_token' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'max:'.$maxKb],
            'field_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable'],
        ]);

        $rawMetadata = $data['metadata'] ?? null;
        if (is_string($rawMetadata)) {
            $decoded = json_decode($rawMetadata, true);
            $rawMetadata = is_array($decoded) ? $decoded : null;
        } elseif (! is_array($rawMetadata)) {
            $rawMetadata = null;
        }

        $attachment = $submissions->storeAttachmentByUploadToken(
            $submission,
            $data['upload_token'],
            $data['file'],
            $data['field_name'] ?? null,
            $rawMetadata,
        );

        return $this->created([
            'id' => (string) $attachment->id,
            'file_name' => $attachment->file_name,
            'field_name' => $attachment->field_name,
            'metadata' => $attachment->metadata,
        ]);
    }
}
