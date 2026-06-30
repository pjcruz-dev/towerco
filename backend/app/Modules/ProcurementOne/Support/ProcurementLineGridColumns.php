<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Models\ProcurementPrLine;

final class ProcurementLineGridColumns
{
    /** @var list<string> */
    public const PR_LABELS = ['Site ID', 'Description', 'Item Code', 'Department', 'UOM', 'Quote basis', 'Qty', 'Unit price'];

    /** @var list<string> */
    public const PO_LABELS = ['Site ID', 'Description', 'Item Code', 'Department', 'Qty', 'Unit price'];

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    public static function metadataFromLabeledRow(array $row): array
    {
        return array_filter([
            'site_id' => self::labeledCell($row, 'Site ID', 'site_id'),
            'item_code' => self::labeledCell($row, 'Item Code', 'item_code', 'Item'),
            'department' => self::labeledCell($row, 'Department', 'department'),
            'uom' => self::labeledCell($row, 'UOM', 'uom'),
            'quote_basis' => ProcurementQuoteBasis::normalize(
                self::labeledCell($row, 'Quote basis', 'quote_basis', 'Billing', 'Billing mode'),
            ),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, string>
     */
    public static function prGridRow(string $description, float $qty, float $unitPrice, ?array $metadata): array
    {
        $meta = is_array($metadata) ? $metadata : [];

        return [
            'Site ID' => (string) ($meta['site_id'] ?? ''),
            'Description' => $description,
            'Item Code' => (string) ($meta['item_code'] ?? ''),
            'Department' => (string) ($meta['department'] ?? ''),
            'UOM' => self::resolveUom($meta),
            'Quote basis' => ProcurementQuoteBasis::label(ProcurementQuoteBasis::fromMetadata($meta)),
            'Qty' => (string) $qty,
            'Unit price' => (string) $unitPrice,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function poGridRow(ProcurementPoLine $line): array
    {
        $meta = is_array($line->metadata_json) ? $line->metadata_json : [];

        return [
            'Site ID' => (string) ($meta['site_id'] ?? ''),
            'Description' => (string) $line->description,
            'Item Code' => (string) ($meta['item_code'] ?? $line->item ?? ''),
            'Department' => (string) ($meta['department'] ?? ''),
            'Qty' => (string) $line->quantity,
            'Unit price' => (string) $line->unit_price,
        ];
    }

    public static function printCellValue(ProcurementPrLine|ProcurementPoLine $line, string $column): string
    {
        $meta = is_array($line->metadata_json) ? $line->metadata_json : [];
        $columnLower = strtolower(trim($column));

        return match ($columnLower) {
            'site id' => (string) ($meta['site_id'] ?? ''),
            'item code', 'item' => (string) ($meta['item_code'] ?? ($line instanceof ProcurementPoLine ? ($line->item ?? '') : '')),
            'department' => (string) ($meta['department'] ?? ''),
            'quote basis' => ProcurementQuoteBasis::label(ProcurementQuoteBasis::fromMetadata($meta)),
            'description' => (string) $line->description,
            'uom' => $line instanceof ProcurementPoLine
                ? (trim((string) ($line->uom ?? '')) ?: self::resolveUom($meta))
                : self::resolveUom($meta),
            'qty', 'quantity' => (string) $line->quantity,
            'unit price' => number_format((float) $line->unit_price, 2, '.', ''),
            'discount' => $line instanceof ProcurementPoLine
                ? number_format((float) $line->discount, 2, '.', '')
                : '',
            'amount' => $line instanceof ProcurementPoLine
                ? number_format((float) $line->amount, 2, '.', '')
                : '',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    /**
     * @return array{description: string, quantity: float, unit_price: float, metadata_json: array<string, string>|null}
     */
    public static function prLineArray(ProcurementPrLine $line): array
    {
        return [
            'description' => (string) $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'metadata_json' => is_array($line->metadata_json) ? $line->metadata_json : null,
        ];
    }

    public static function resolveUom(?array $metadata): string
    {
        $meta = is_array($metadata) ? $metadata : [];

        return trim((string) ($meta['uom'] ?? $meta['unit'] ?? 'EA')) ?: 'EA';
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function labeledCell(array $row, string $label, string ...$aliases): string
    {
        foreach ([$label, ...$aliases] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
