<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;

final class ProcurementVendorFormResolverService
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

                return ($metadata['form_family'] ?? null) === 'vendor_registration';
            });
    }

    public function resolvePublishedFormOrFail(): EApprovalForm
    {
        $form = $this->resolvePublishedForm();
        abort_if(
            $form === null,
            422,
            __('No published vendor registration form is configured. Publish the vendor registration form in E-Approval.'),
        );

        return $form;
    }
}
