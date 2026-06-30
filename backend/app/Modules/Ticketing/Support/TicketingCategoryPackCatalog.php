<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TicketingCategoryPackCatalog
{
    public const PACK_PROCUREMENT_ONE = 'procurement_one';

    /**
     * @return list<string>
     */
    public static function procurementOneCategories(): array
    {
        return [
            'procurement_delivery_delay',
            'procurement_vendor_issue',
            'procurement_invoice_dispute',
            'procurement_grn_mismatch',
            'procurement_approval_delay',
            'procurement_contract',
            'procurement_general',
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, categories: list<string>}>
     */
    public function all(): array
    {
        return [
            [
                'id' => self::PACK_PROCUREMENT_ONE,
                'label' => 'Procurement-One',
                'description' => 'Delivery delays, vendor issues, GRN mismatches, invoice disputes, and contract follow-ups.',
                'categories' => self::procurementOneCategories(),
            ],
        ];
    }

    public function isValid(string $packId): bool
    {
        return in_array($packId, array_column($this->all(), 'id'), true);
    }

    /**
     * @return list<string>
     */
    public function categoriesFor(string $packId): array
    {
        return match ($packId) {
            self::PACK_PROCUREMENT_ONE => self::procurementOneCategories(),
            default => [],
        };
    }
}
