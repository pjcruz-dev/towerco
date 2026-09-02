<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class AtcFinancePack
{
    /**
     * Ordered finance entity slugs for Phase 3 data import.
     *
     * @return list<string>
     */
    public static function phase3EntitySlugs(): array
    {
        return [
            'chart_of_accounts',
            'fiscal_periods',
            'customers',
            'bank_accounts',
            'capex_budgets',
            'general_ledger',
            'site_operating_costs',
            'fixed_assets',
            'payments_and_receipts',
            'sales_transactions',
            'sales_transaction_items',
            'bank_transactions',
            'bank_reconciliations',
            'bir_form_2307_certificates',
        ];
    }

    /**
     * @return list<array{entity_slug: string, foreign_field: string, label: string}>
     */
    public static function forSalesTransactions(): array
    {
        return [
            [
                'entity_slug' => 'sales_transaction_items',
                'foreign_field' => 'sales_transaction_id',
                'label' => 'Line Items',
            ],
        ];
    }

    /**
     * @return list<array{entity_slug: string, foreign_field: string, label: string}>
     */
    public static function forBankAccounts(): array
    {
        return [
            [
                'entity_slug' => 'bank_transactions',
                'foreign_field' => 'bank_account_id',
                'label' => 'Bank Transactions',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allThroughPhase3(): array
    {
        return array_values(array_unique([
            ...AtcPmRelatedTabs::phase1EntitySlugs(),
            ...AtcProcurementPack::phase2EntitySlugs(),
            ...self::phase3EntitySlugs(),
        ]));
    }
}
