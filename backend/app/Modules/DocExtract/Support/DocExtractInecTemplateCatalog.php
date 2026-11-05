<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

/**
 * Ilocos Norte Electric Cooperative statement rows.
 * One table row per Statement of Account. WTAX is computed, not printed.
 */
final class DocExtractInecTemplateCatalog
{
    public const NAME = 'INEC electric bill';

    public const DESCRIPTION = 'Ilocos Norte Electric Cooperative statements. One row per account. Previous and current readings are the meter dial times the multiplier. Rate is total amount due divided by kWh. WTAX is 2% of Distribution System Demand, Supply System Retail Cust, Metering System Retail Cust, Lifeline Subsidy, Senior Citizen Subsidy, and R.F.S. CAPEX. Amount is total amount due minus WTAX.';

    /**
     * @return array{
     *   name: string,
     *   description: string,
     *   status: string,
     *   fields: list<array<string, mixed>>
     * }
     */
    public static function definition(): array
    {
        return [
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
            'status' => DocExtractTemplateStatus::PUBLISHED,
            'fields' => self::fields(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fields(): array
    {
        return [
            [
                'key' => 'inec_bills',
                'label' => 'INEC Bills',
                'type' => 'table',
                'description' => 'One row per INEC statement of account.',
                'hint' => 'STATEMENT OF ACCOUNT',
                'columns' => [
                    self::column('electric_coop', 'Electric Coop', 'text'),
                    self::column('bill_month', 'Bill Month', 'text'),
                    self::column('account_number', 'Account Number', 'text'),
                    self::column('name', 'Name', 'text'),
                    self::column('address', 'Address', 'text'),
                    self::column('meter_number', 'Meter Number', 'text'),
                    self::column('previous_reading_date', 'Previous Reading Date', 'text'),
                    self::column('previous_reading', 'Previous Reading', 'number'),
                    self::column('current_reading_date', 'Current Reading Date', 'text'),
                    self::column('current_reading', 'Current Reading', 'number'),
                    self::column('amount', 'Amount', 'currency'),
                    self::column('wtax', 'WTAX', 'number'),
                    self::column('consumption', 'Consumption', 'number'),
                    self::column('rate_php_per_kwh', 'Rate (Php / KwH)', 'number'),
                    self::column('total_amount', 'Total Amount', 'currency'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, type: string, description: null}
     */
    private static function column(string $key, string $label, string $type): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'description' => null,
        ];
    }
}
