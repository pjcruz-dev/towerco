<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class AtcProcurementPack
{
    /**
     * Ordered procurement entity slugs for Phase 2 data import.
     * Parents before children so FK remaps succeed.
     *
     * @return list<string>
     */
    public static function phase2EntitySlugs(): array
    {
        return [
            'suppliers',
            'vendors',
            'warehouses',
            'products',
            'purchase_transactions',
            'purchase_transaction_items',
            'procurement_requests',
            'stock_movements',
        ];
    }

    /**
     * Related tabs on Purchase Transactions detail.
     *
     * @return list<array{entity_slug: string, foreign_field: string, label: string}>
     */
    public static function forPurchaseTransactions(): array
    {
        return [
            [
                'entity_slug' => 'purchase_transaction_items',
                'foreign_field' => 'purchase_transaction_id',
                'label' => 'Line Items',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allWithPhase1(): array
    {
        return array_values(array_unique([
            ...AtcPmRelatedTabs::phase1EntitySlugs(),
            ...self::phase2EntitySlugs(),
        ]));
    }
}
