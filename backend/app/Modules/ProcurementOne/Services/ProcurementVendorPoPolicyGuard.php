<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use Illuminate\Validation\ValidationException;

final class ProcurementVendorPoPolicyGuard
{
    public function __construct(
        private readonly ProcurementVendorAccreditationPolicyService $policy,
        private readonly ProcurementVendorRegistryService $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return list<string> warnings when mode=warn
     */
    public function assertPurchaseOrderVendor(EApprovalForm $form, array $values): array
    {
        if (! $this->isPurchaseOrderForm($form)) {
            return [];
        }

        $vendorCode = trim((string) ($values['vendor'] ?? ''));
        if ($vendorCode === '' || $vendorCode === 'vendor_pending') {
            return [];
        }

        $vendor = $this->registry->findByVendorCode($vendorCode);
        if (! $vendor instanceof ProcurementVendor) {
            if ($this->policy->blocksNonAccredited()) {
                throw ValidationException::withMessages([
                    'values.vendor' => [__('Selected vendor is not in the procurement vendor registry.')],
                ]);
            }

            return [__('Selected vendor is not in the procurement vendor registry.')];
        }

        if (ProcurementVendorAccreditationStatus::isSelectableOnPo((string) $vendor->accreditation_status)) {
            return [];
        }

        $label = ProcurementVendorAccreditationStatus::label((string) $vendor->accreditation_status);
        $message = __('Vendor :name is :status and may not be used on purchase orders.', [
            'name' => $vendor->company_name,
            'status' => $label,
        ]);

        if ($this->policy->blocksNonAccredited()) {
            throw ValidationException::withMessages([
                'values.vendor' => [$message],
            ]);
        }

        if ($this->policy->isEnforced()) {
            return [$message];
        }

        return [];
    }

    private function isPurchaseOrderForm(EApprovalForm $form): bool
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'purchase_order';
    }
}
