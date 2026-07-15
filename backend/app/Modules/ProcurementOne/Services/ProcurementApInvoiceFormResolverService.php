<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPo;

final class ProcurementApInvoiceFormResolverService
{
    public function resolvePublishedForm(): ?EApprovalForm
    {
        return EApprovalForm::query()
            ->with('fields')
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get()
            ->first(static function (EApprovalForm $form): bool {
                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

                return ($metadata['form_family'] ?? null) === 'ap_invoice';
            });
    }

    public function resolvePublishedFormOrFail(): EApprovalForm
    {
        $form = $this->resolvePublishedForm();
        abort_if($form === null, 422, __('No published AP invoice form is configured. Install the finance & procurement template pack in E-Approval.'));

        return $form;
    }

    public function parentSubmissionId(ProcurementApInvoice $invoice): ?string
    {
        $invoice->loadMissing('purchaseOrder');
        $po = $invoice->purchaseOrder;

        return $po?->e_approval_submission_id;
    }

    public function poDocumentNo(ProcurementPo $po): ?string
    {
        return $po->document_no;
    }
}
