<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionValuesValidator;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;

final class ProcurementComposeSubmitValidator
{
    public function __construct(
        private readonly ProcurementPrFormResolverService $prFormResolver,
        private readonly ProcurementPoFormResolverService $poFormResolver,
        private readonly ProcurementApInvoiceFormResolverService $apFormResolver,
        private readonly EApprovalSubmissionValuesValidator $valuesValidator,
        private readonly ProcurementPrValueMapper $prMapper,
        private readonly ProcurementPoValueMapper $poMapper,
        private readonly ProcurementApInvoiceValueMapper $apMapper,
        private readonly ProcurementFormValuesEApprovalMerger $valueMerger,
    ) {}

    public function assertPurchaseRequisitionSubmittable(ProcurementPr $pr): void
    {
        $pr->loadMissing(['lines', 'attachments']);

        $form = $this->prFormResolver->resolvePublishedFormOrFail();
        $lines = $pr->lines->map(static fn ($line) => ProcurementLineGridColumns::prLineArray($line))->all();

        $metadata = is_array($pr->metadata_json) ? $pr->metadata_json : [];
        $compose = is_array($metadata[ProcurementComposeMetadata::COMPOSE_FORM_VALUES_KEY] ?? null)
            ? $metadata[ProcurementComposeMetadata::COMPOSE_FORM_VALUES_KEY]
            : [];

        $values = $this->valueMerger->mergePurchaseRequisition(
            $this->prMapper->toEApprovalValues($pr, $lines),
            $compose,
        );

        $this->valuesValidator->validate(
            $form,
            $values,
            requireRequired: true,
            attachmentCountsByFieldName: $this->attachmentCounts($pr->attachments),
        );
    }

    public function assertPurchaseOrderSubmittable(ProcurementPo $po): void
    {
        $form = $this->poFormResolver->resolvePublishedFormOrFail();
        $values = $this->valueMerger->mergePurchaseOrder(
            $this->poMapper->toEApprovalValues($po, $this->poFormResolver->primaryPrDocumentNo($po)),
            ProcurementComposeMetadata::composeFormValues(is_array($po->metadata_json) ? $po->metadata_json : null),
        );

        $this->valuesValidator->validate(
            $form,
            $values,
            requireRequired: true,
            attachmentCountsByFieldName: $this->eApprovalAttachmentCounts($po->e_approval_submission_id),
        );
    }

    public function assertApInvoiceSubmittable(ProcurementApInvoice $invoice): void
    {
        $invoice->loadMissing(['purchaseOrder']);

        $form = $this->apFormResolver->resolvePublishedFormOrFail();
        $values = $this->valueMerger->mergeApInvoice(
            $this->apMapper->toEApprovalValues($invoice, $invoice->purchaseOrder?->document_no),
            ProcurementComposeMetadata::composeFormValues(is_array($invoice->metadata_json) ? $invoice->metadata_json : null),
        );

        $this->valuesValidator->validate(
            $form,
            $values,
            requireRequired: true,
            attachmentCountsByFieldName: $this->eApprovalAttachmentCounts($invoice->e_approval_submission_id),
        );
    }

    /**
     * @param  iterable<object{field_name?: string|null}>  $attachments
     * @return array<string, int>
     */
    private function attachmentCounts(iterable $attachments): array
    {
        $counts = [];
        foreach ($attachments as $attachment) {
            $fieldName = trim((string) ($attachment->field_name ?? ''));
            if ($fieldName === '') {
                continue;
            }

            $counts[$fieldName] = ($counts[$fieldName] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function eApprovalAttachmentCounts(?string $submissionId): array
    {
        if ($submissionId === null || trim($submissionId) === '') {
            return [];
        }

        /** @var EApprovalSubmission|null $submission */
        $submission = EApprovalSubmission::query()->with('attachments')->find($submissionId);
        if ($submission === null) {
            return [];
        }

        return $this->attachmentCounts($submission->attachments);
    }
}
