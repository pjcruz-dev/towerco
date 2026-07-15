<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use Illuminate\Validation\ValidationException;

final class ProcurementStatusCatalog
{
    /**
     * @return array<string, list<array{key: string, label: string, terminal?: bool}>>
     */
    public function resolveAll(): array
    {
        $settings = app(ProcurementOneSettingsService::class);
        $raw = $settings->getJson(ProcurementOneSettingsService::STATUS_CATALOGS);
        $resolved = [];

        foreach (ProcurementDocumentType::all() as $documentType) {
            $resolved[$documentType] = $this->resolveForType($documentType, $raw[$documentType] ?? null);
        }

        return $resolved;
    }

    /**
     * @return list<array{key: string, label: string, terminal?: bool}>
     */
    public function resolveForType(string $documentType, mixed $override = null): array
    {
        if (! ProcurementDocumentType::isValid($documentType)) {
            return [];
        }

        if (is_array($override)) {
            $normalized = $this->normalizeStatuses($override);
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return $this->defaultsForType($documentType);
    }

    /**
     * @param  list<array{key: string, label: string, terminal?: bool}>  $statuses
     * @return list<array{key: string, label: string, terminal?: bool}>
     */
    public function normalizeStatuses(array $statuses): array
    {
        $normalized = [];
        foreach ($statuses as $status) {
            if (! is_array($status)) {
                continue;
            }

            $key = strtolower(trim((string) ($status['key'] ?? '')));
            if ($key === '' || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }

            $label = trim((string) ($status['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'terminal' => (bool) ($status['terminal'] ?? false),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, list<array{key: string, label: string, terminal?: bool}>>  $payload
     */
    public function validateAndNormalizePayload(array $payload): array
    {
        $normalized = [];
        foreach (ProcurementDocumentType::all() as $documentType) {
            if (! array_key_exists($documentType, $payload)) {
                continue;
            }

            if (! is_array($payload[$documentType])) {
                throw ValidationException::withMessages([
                    "status_catalogs.{$documentType}" => [__('Status catalog must be an array.')],
                ]);
            }

            $statuses = $this->normalizeStatuses($payload[$documentType]);
            if ($statuses === []) {
                throw ValidationException::withMessages([
                    "status_catalogs.{$documentType}" => [__('At least one status is required.')],
                ]);
            }

            $normalized[$documentType] = $statuses;
        }

        return $normalized;
    }

    /**
     * @return list<array{key: string, label: string, terminal?: bool}>
     */
    private function defaultsForType(string $documentType): array
    {
        return match ($documentType) {
            ProcurementDocumentType::PURCHASE_REQUISITION => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'submitted', 'label' => 'Submitted'],
                ['key' => 'pending_approval', 'label' => 'Pending approval'],
                ['key' => 'approved', 'label' => 'Approved', 'terminal' => false],
                ['key' => 'rejected', 'label' => 'Rejected', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
                ['key' => 'voided', 'label' => 'Voided', 'terminal' => true],
                ['key' => 'converted', 'label' => 'Converted to PO', 'terminal' => true],
            ],
            ProcurementDocumentType::PURCHASE_ORDER => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'pending_approval', 'label' => 'Pending approval'],
                ['key' => 'approved', 'label' => 'Approved'],
                ['key' => 'sent', 'label' => 'Sent to vendor'],
                ['key' => 'partially_received', 'label' => 'Partially received'],
                ['key' => 'received', 'label' => 'Received', 'terminal' => true],
                ['key' => 'closed', 'label' => 'Closed', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
                ['key' => 'voided', 'label' => 'Voided', 'terminal' => true],
            ],
            ProcurementDocumentType::GOODS_RECEIPT => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'pending_approval', 'label' => 'Pending approval'],
                ['key' => 'approved', 'label' => 'Approved'],
                ['key' => 'posted', 'label' => 'Posted', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
                ['key' => 'voided', 'label' => 'Voided', 'terminal' => true],
            ],
            ProcurementDocumentType::AP_INVOICE => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'pending_approval', 'label' => 'Pending approval'],
                ['key' => 'approved', 'label' => 'Approved for payment', 'terminal' => false],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
                ['key' => 'voided', 'label' => 'Voided', 'terminal' => true],
            ],
            ProcurementDocumentType::CREDIT_NOTE => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'approved', 'label' => 'Approved', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
            ],
            ProcurementDocumentType::PAYMENT_REQUEST => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'pending_approval', 'label' => 'Pending approval'],
                ['key' => 'approved', 'label' => 'Approved'],
                ['key' => 'scheduled', 'label' => 'Scheduled'],
                ['key' => 'paid', 'label' => 'Paid'],
                ['key' => 'reconciled', 'label' => 'Reconciled', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
            ],
            ProcurementDocumentType::PAYMENT_BATCH => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'scheduled', 'label' => 'Scheduled'],
                ['key' => 'exported', 'label' => 'Exported'],
                ['key' => 'reconciled', 'label' => 'Reconciled', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
            ],
            ProcurementDocumentType::REQUEST_FOR_QUOTATION => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'open', 'label' => 'Open for quotes'],
                ['key' => 'closed', 'label' => 'Closed'],
                ['key' => 'awarded', 'label' => 'Awarded'],
                ['key' => 'converted', 'label' => 'Converted to PO', 'terminal' => true],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'terminal' => true],
            ],
            ProcurementDocumentType::VENDOR_CONTRACT => [
                ['key' => 'draft', 'label' => 'Draft'],
                ['key' => 'active', 'label' => 'Active'],
                ['key' => 'expired', 'label' => 'Expired', 'terminal' => true],
                ['key' => 'terminated', 'label' => 'Terminated', 'terminal' => true],
            ],
            default => [],
        };
    }
}
