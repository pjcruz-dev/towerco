<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubmissionAttachmentValidator;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementPrAttachment;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Http\UploadedFile;

final class ProcurementPrAttachmentService
{
    public function __construct(
        private readonly ProcurementPrSubmissionBridgeService $bridge,
        private readonly EApprovalFileStorageService $storage,
        private readonly EApprovalSubmissionAttachmentValidator $validator,
    ) {}

    public function store(
        ProcurementPr $pr,
        UploadedFile $file,
        string $fieldName,
        TenantUser $actor,
    ): ProcurementPrAttachment {
        abort_unless(ProcurementPrStatus::isEditable((string) $pr->status), 422, __('Attachments can only be added to draft purchase requisitions.'));

        $pr = $this->bridge->ensureDraftSubmission($pr, $actor);
        $submission = EApprovalSubmission::query()->with('form.fields')->findOrFail($pr->e_approval_submission_id);
        $this->validator->assertCanStore($submission, $file, $fieldName);

        $stored = $this->storage->store($submission, $file, $fieldName);

        return ProcurementPrAttachment::query()->create([
            'pr_id' => $pr->id,
            'e_approval_attachment_id' => (string) $stored->id,
            'field_name' => $fieldName,
            'file_name' => $stored->file_name,
            'mime_type' => $stored->mime_type,
            'size_bytes' => $stored->size_bytes,
        ]);
    }
}
