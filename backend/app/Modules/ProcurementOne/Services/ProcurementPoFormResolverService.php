<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\ProcurementOne\Models\ProcurementPo;

final class ProcurementPoFormResolverService
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

                return ($metadata['form_family'] ?? null) === 'purchase_order';
            });
    }

    public function resolvePublishedFormOrFail(): EApprovalForm
    {
        $form = $this->resolvePublishedForm();
        abort_if($form === null, 422, __('No published purchase order form is configured. Install the finance & procurement template pack in E-Approval.'));

        return $form;
    }

    public function primaryParentSubmissionId(ProcurementPo $po): ?string
    {
        $po->loadMissing('prLinks.purchaseRequisition');
        $primary = $po->prLinks->sortBy('created_at')->first()?->purchaseRequisition;

        return $primary?->e_approval_submission_id;
    }

    public function primaryPrDocumentNo(ProcurementPo $po): ?string
    {
        $po->loadMissing('prLinks.purchaseRequisition');
        $primary = $po->prLinks->sortBy('created_at')->first()?->purchaseRequisition;

        return $primary?->document_no;
    }
}
