<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;

final class ProcurementDocumentTypeCatalog
{
    /**
     * @return list<array{id: string, label: string, code: string}>
     */
    public function resolve(): array
    {
        $settings = app(ProcurementOneSettingsService::class);
        $raw = $settings->getJson(ProcurementOneSettingsService::DOCUMENT_TYPES);
        if ($raw === []) {
            return $this->defaults();
        }

        $types = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '' || ! ProcurementDocumentType::isValid($id)) {
                continue;
            }

            $types[] = [
                'id' => $id,
                'label' => trim((string) ($item['label'] ?? ProcurementDocumentType::label($id))),
                'code' => strtoupper(trim((string) ($item['code'] ?? $this->defaultCode($id)))),
            ];
        }

        return $types !== [] ? $types : $this->defaults();
    }

    /**
     * @return list<array{id: string, label: string, code: string}>
     */
    public function defaults(): array
    {
        return [
            [
                'id' => ProcurementDocumentType::PURCHASE_REQUISITION,
                'label' => ProcurementDocumentType::label(ProcurementDocumentType::PURCHASE_REQUISITION),
                'code' => 'PR',
            ],
            [
                'id' => ProcurementDocumentType::PURCHASE_ORDER,
                'label' => ProcurementDocumentType::label(ProcurementDocumentType::PURCHASE_ORDER),
                'code' => 'PO',
            ],
            [
                'id' => ProcurementDocumentType::GOODS_RECEIPT,
                'label' => ProcurementDocumentType::label(ProcurementDocumentType::GOODS_RECEIPT),
                'code' => 'GRN',
            ],
        ];
    }

    private function defaultCode(string $id): string
    {
        return match ($id) {
            ProcurementDocumentType::PURCHASE_REQUISITION => 'PR',
            ProcurementDocumentType::PURCHASE_ORDER => 'PO',
            ProcurementDocumentType::GOODS_RECEIPT => 'GRN',
            default => strtoupper(substr($id, 0, 3)),
        };
    }
}
