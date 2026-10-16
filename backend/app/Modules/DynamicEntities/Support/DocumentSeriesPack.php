<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Document Series — numbering setup from the legacy series list.
 * Subsidiary is a relationship. Prefix, document type, and number format drive the series.
 */
final class DocumentSeriesPack
{
    public const SUBSIDIARIES = 'subsidiaries';

    public const SERIES = 'document_series';

    public const TICKET_KEY = 'TKT';

    /**
     * @return list<array<string, mixed>>
     */
    public static function subsidiaryFields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'type' => DynFieldType::TEXT, 'is_required' => true, 'is_key' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 10],
            ['name' => 'name', 'label' => 'Name', 'type' => DynFieldType::TEXT, 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 20],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seriesFields(): array
    {
        return [
            ['name' => 'last_number', 'label' => 'Last number used', 'type' => DynFieldType::NUMBER, 'show_in_table' => true, 'field_order' => 10],
            self::chrome('id', 'ID', DynFieldType::NUMBER, 20),
            self::chrome('actions', 'Actions', DynFieldType::TEXT, 30),
            self::chrome('workflows', 'Workflows', DynFieldType::TEXT, 40),
            self::chrome('print', 'Print', DynFieldType::TEXT, 50),
            ['name' => 'accn_control_no', 'label' => 'ACCN', 'type' => DynFieldType::TEXT, 'field_order' => 60],
            [
                'name' => 'status',
                'label' => 'Status',
                'type' => DynFieldType::SELECT,
                'show_in_table' => true,
                'is_filterable' => true,
                'field_order' => 70,
                'options_json' => [
                    'choices' => [
                        ['value' => 'Active', 'label' => 'Active', 'badge' => 'success'],
                        ['value' => 'Inactive', 'label' => 'Inactive', 'badge' => 'none'],
                    ],
                    'default' => 'Active',
                ],
            ],
            [
                'name' => 'subsidiary_id',
                'label' => 'Subsidiary',
                'type' => DynFieldType::RELATIONSHIP,
                'target_slug' => self::SUBSIDIARIES,
                'show_in_table' => true,
                'is_filterable' => true,
                'field_order' => 80,
            ],
            ['name' => 'series_prefix', 'label' => 'Prefix', 'type' => DynFieldType::TEXT, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 90],
            ['name' => 'number_format', 'label' => 'Number Format', 'type' => DynFieldType::TEXT, 'show_in_table' => true, 'field_order' => 100],
            ['name' => 'series_from', 'label' => 'Approved Series From', 'type' => DynFieldType::TEXT, 'field_order' => 110],
            ['name' => 'series_to', 'label' => 'Approved Series To', 'type' => DynFieldType::TEXT, 'field_order' => 120],
            [
                'name' => 'document_type',
                'label' => 'Document Type',
                'type' => DynFieldType::SELECT,
                'show_in_table' => true,
                'is_filterable' => true,
                'field_order' => 130,
                'options_json' => [
                    'choices' => [
                        ['value' => 'PO', 'label' => 'PO — Purchase Order', 'badge' => 'none'],
                        ['value' => 'DM', 'label' => 'DM — Debit Memo', 'badge' => 'none'],
                        ['value' => 'ER', 'label' => 'ER — Expense Report', 'badge' => 'none'],
                        ['value' => 'TRN', 'label' => 'TRN — Inter-bank Transfer', 'badge' => 'none'],
                        ['value' => 'IR', 'label' => 'IR — Item Receipt', 'badge' => 'none'],
                        ['value' => 'JE', 'label' => 'JE — Journal Entry', 'badge' => 'none'],
                        ['value' => 'CDV', 'label' => 'CDV — Payment Voucher', 'badge' => 'none'],
                        ['value' => 'APV', 'label' => 'APV — Accounts Payable Voucher', 'badge' => 'none'],
                        ['value' => 'TKT', 'label' => 'TKT — Ticket', 'badge' => 'none'],
                        ['value' => 'LAP', 'label' => 'LAP — Laptop', 'badge' => 'none'],
                        ['value' => 'DSK', 'label' => 'DSK — Desktop', 'badge' => 'none'],
                        ['value' => 'MON', 'label' => 'MON — Monitor', 'badge' => 'none'],
                        ['value' => 'DOC', 'label' => 'DOC — Dock', 'badge' => 'none'],
                        ['value' => 'PHN', 'label' => 'PHN — Phone', 'badge' => 'none'],
                        ['value' => 'PER', 'label' => 'PER — Peripheral', 'badge' => 'none'],
                    ],
                ],
            ],
            ['name' => 'accn_issue_date', 'label' => 'ACCN Issue Date', 'type' => DynFieldType::DATE, 'field_order' => 140],
        ];
    }

    /**
     * Rows visible on the legacy Document Series list.
     *
     * @return list<array{external_id: string, subsidiary: string, document_type: string, prefix: string, number_format: string}>
     */
    public static function seriesRows(): array
    {
        $long = 'YYYY-MM-##########';
        $short = 'YYYY-MM-####';

        return [
            ['external_id' => '22', 'subsidiary' => 'ADC', 'document_type' => 'PO', 'prefix' => 'PO', 'number_format' => $long],
            ['external_id' => '21', 'subsidiary' => 'ATC', 'document_type' => 'PO', 'prefix' => 'PO', 'number_format' => $long],
            ['external_id' => '20', 'subsidiary' => 'ADC', 'document_type' => 'DM', 'prefix' => 'DM', 'number_format' => $long],
            ['external_id' => '19', 'subsidiary' => 'ATC', 'document_type' => 'DM', 'prefix' => 'DM', 'number_format' => $long],
            ['external_id' => '18', 'subsidiary' => 'ADC', 'document_type' => 'ER', 'prefix' => 'ER', 'number_format' => $long],
            ['external_id' => '17', 'subsidiary' => 'ADC', 'document_type' => 'TRN', 'prefix' => 'TRN', 'number_format' => $long],
            ['external_id' => '16', 'subsidiary' => 'ADC', 'document_type' => 'IR', 'prefix' => 'IR', 'number_format' => $long],
            ['external_id' => '15', 'subsidiary' => 'ADC', 'document_type' => 'JE', 'prefix' => 'JE', 'number_format' => $short],
            ['external_id' => '14', 'subsidiary' => 'ADC', 'document_type' => 'CDV', 'prefix' => 'CDV', 'number_format' => $short],
            ['external_id' => '13', 'subsidiary' => 'ADC', 'document_type' => 'APV', 'prefix' => 'APV', 'number_format' => $long],
        ];
    }

    /**
     * Ticket and asset counters that share this series list.
     *
     * @return list<array{key: string, document_type: string, title: string, format: string, prefix: string|null, asset: string|null}>
     */
    public static function operationalRows(): array
    {
        return [
            ['key' => self::TICKET_KEY, 'document_type' => 'TKT', 'title' => 'TKT — Ticket', 'format' => '#####', 'prefix' => 'TKT', 'asset' => null],
            ['key' => 'LAP', 'document_type' => 'LAP', 'title' => 'LAP — Laptop', 'format' => '######', 'prefix' => null, 'asset' => 'laptop'],
            ['key' => 'DSK', 'document_type' => 'DSK', 'title' => 'DSK — Desktop', 'format' => '######', 'prefix' => null, 'asset' => 'desktop'],
            ['key' => 'MON', 'document_type' => 'MON', 'title' => 'MON — Monitor', 'format' => '######', 'prefix' => null, 'asset' => 'monitor'],
            ['key' => 'DOC', 'document_type' => 'DOC', 'title' => 'DOC — Dock', 'format' => '######', 'prefix' => null, 'asset' => 'dock'],
            ['key' => 'PHN', 'document_type' => 'PHN', 'title' => 'PHN — Phone', 'format' => '######', 'prefix' => null, 'asset' => 'phone'],
            ['key' => 'PER', 'document_type' => 'PER', 'title' => 'PER — Peripheral', 'format' => '######', 'prefix' => null, 'asset' => 'peripheral'],
        ];
    }

    /**
     * @return array{name: string, label: string, type: string, is_system_field: true, field_order: int}
     */
    private static function chrome(string $name, string $label, string $type, int $order): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'is_system_field' => true,
            'field_order' => $order,
        ];
    }
}
