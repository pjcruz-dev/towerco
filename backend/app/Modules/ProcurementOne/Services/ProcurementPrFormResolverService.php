<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;

final class ProcurementPrFormResolverService
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

                return ($metadata['form_family'] ?? null) === 'purchase_requisition';
            });
    }

    public function resolvePublishedFormOrFail(): EApprovalForm
    {
        $form = $this->resolvePublishedForm();
        abort_if($form === null, 422, __('No published purchase requisition form is configured. Install the finance & procurement template pack in E-Approval.'));

        return $form;
    }
}
