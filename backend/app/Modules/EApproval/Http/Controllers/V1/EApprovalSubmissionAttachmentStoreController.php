<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalPlanFeaturesService;
use App\Modules\EApproval\Services\EApprovalSubmissionAttachmentValidator;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionAttachmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalFileStorageService $files,
        EApprovalSubmissionService $submissions,
        EApprovalPlanFeaturesService $planFeatures,
        EApprovalSubmissionAttachmentValidator $attachmentValidator,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $planFeatures->assertCanUploadAttachment();

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $maxKb = max(1, (int) config('toweros.tenant_files.max_size_kb', 25600));

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'field_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable'],
        ]);

        $fieldName = $data['field_name'] ?? null;
        $rawMetadata = $this->parseMetadataInput($request->input('metadata'));
        $metadata = $attachmentValidator->normalizeMetadata($rawMetadata);

        $existing = $files->findExistingByOriginalName($submission, $data['file']->getClientOriginalName(), $fieldName);
        if ($existing !== null) {
            if ($metadata !== null && empty($existing->metadata)) {
                $existing->metadata = $metadata;
                $existing->save();
            }

            return $this->ok([
                'id' => (string) $existing->id,
                'file_name' => $existing->file_name,
                'field_name' => $existing->field_name,
                'metadata' => $existing->metadata,
            ]);
        }

        $attachmentValidator->assertCanStore($submission, $data['file'], $fieldName, $metadata);

        $attachment = $files->store($submission, $data['file'], $fieldName, $metadata);

        return $this->created([
            'id' => (string) $attachment->id,
            'file_name' => $attachment->file_name,
            'field_name' => $attachment->field_name,
            'metadata' => $attachment->metadata,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseMetadataInput(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }
}
