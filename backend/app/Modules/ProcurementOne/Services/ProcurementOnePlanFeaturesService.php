<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Validation\ValidationException;

final class ProcurementOnePlanFeaturesService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array{
     *   plan_tier: string,
     *   enabled: bool,
     *   goods_receipt: bool,
     *   advanced_numbering: bool,
     *   inventory: bool,
     *   ap_invoices: bool,
     *   payment_tracking: bool,
     *   rfq_sourcing: bool,
     *   vendor_contracts: bool,
     *   reporting_exports: bool
     * }
     */
    public function snapshot(?string $tenantId = null): array
    {
        return $this->entitlements->procurementOneFeatures($tenantId);
    }

    public function moduleEnabled(): bool
    {
        return $this->snapshot()['enabled'];
    }

    public function goodsReceiptEnabled(): bool
    {
        return $this->snapshot()['goods_receipt'];
    }

    public function inventoryEnabled(): bool
    {
        return $this->snapshot()['inventory'];
    }

    public function assertModuleEnabled(): void
    {
        if (! $this->moduleEnabled()) {
            throw ValidationException::withMessages([
                'procurement_one' => [__('Procurement-One is not included on your current plan.')],
            ]);
        }
    }

    public function assertGoodsReceiptEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->goodsReceiptEnabled()) {
            throw ValidationException::withMessages([
                'goods_receipt' => [__('Goods receipt is not included on your current plan.')],
            ]);
        }
    }

    public function assertInventoryEnabled(): void
    {
        $this->assertGoodsReceiptEnabled();

        if (! $this->inventoryEnabled()) {
            throw ValidationException::withMessages([
                'inventory' => [__('Simple inventory is not included on your current plan.')],
            ]);
        }
    }

    public function apInvoicesEnabled(): bool
    {
        return $this->snapshot()['ap_invoices'];
    }

    public function assertApInvoicesEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->apInvoicesEnabled()) {
            throw ValidationException::withMessages([
                'ap_invoices' => [__('Accounts payable invoices are not included on your current plan.')],
            ]);
        }
    }

    public function paymentTrackingEnabled(): bool
    {
        return $this->snapshot()['payment_tracking'];
    }

    public function assertPaymentTrackingEnabled(): void
    {
        $this->assertApInvoicesEnabled();

        if (! $this->paymentTrackingEnabled()) {
            throw ValidationException::withMessages([
                'payment_tracking' => [__('Payment tracking is not included on your current plan.')],
            ]);
        }
    }

    public function rfqSourcingEnabled(): bool
    {
        return $this->snapshot()['rfq_sourcing'];
    }

    public function assertRfqSourcingEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->rfqSourcingEnabled()) {
            throw ValidationException::withMessages([
                'rfq_sourcing' => [__('RFQ sourcing is not included on your current plan.')],
            ]);
        }
    }

    public function vendorContractsEnabled(): bool
    {
        return $this->snapshot()['vendor_contracts'];
    }

    public function assertVendorContractsEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->vendorContractsEnabled()) {
            throw ValidationException::withMessages([
                'vendor_contracts' => [__('Vendor contracts are not included on your current plan.')],
            ]);
        }
    }

    public function reportingExportsEnabled(): bool
    {
        return $this->snapshot()['reporting_exports'];
    }

    public function assertReportingExportsEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->reportingExportsEnabled()) {
            throw ValidationException::withMessages([
                'reporting_exports' => [__('Procurement reporting exports are not included on your current plan.')],
            ]);
        }
    }
}
