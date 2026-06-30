<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementPrLine;
use App\Modules\ProcurementOne\Support\ProcurementGridValueParser;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;

final class ProcurementPrValueMapper
{
    public function __construct(
        private readonly ProcurementGridValueParser $gridParser,
    ) {}

    /**
     * @param  list<array{description: string, quantity?: float|string, unit_price?: float|string, metadata_json?: array<string, mixed>|null}>  $lines
     * @return array<string, mixed>
     */
    public function toEApprovalValues(ProcurementPr $pr, array $lines): array
    {
        $gridRows = [];
        foreach ($lines as $line) {
            $gridRows[] = ProcurementLineGridColumns::prGridRow(
                (string) ($line['description'] ?? ''),
                (float) ($line['quantity'] ?? 1),
                (float) ($line['unit_price'] ?? 0),
                is_array($line['metadata_json'] ?? null) ? $line['metadata_json'] : null,
            );
        }

        if ($gridRows === [] && $pr->relationLoaded('lines')) {
            foreach ($pr->lines as $line) {
                $gridRows[] = ProcurementLineGridColumns::prGridRow(
                    (string) $line->description,
                    (float) $line->quantity,
                    (float) $line->unit_price,
                    is_array($line->metadata_json) ? $line->metadata_json : null,
                );
            }
        }

        return array_filter([
            'requisition_title' => $pr->title,
            'department' => $pr->department,
            'urgency' => $pr->urgency,
            'currency' => $pr->currency,
            'line_items' => $gridRows,
            'estimated_total' => (string) $pr->estimated_total,
            'justification' => $pr->justification,
            'project_id' => $pr->project_id,
            'rollout_id' => $pr->rollout_id,
            'site_id' => $pr->site_id,
            'boq_line_id' => $pr->boq_line_id,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<string>|null  $columnLabels
     * @return list<array{description: string, quantity: float, unit_price: float, amount: float, metadata_json?: array<string, string>}>
     */
    public function linesFromGridValue(mixed $raw, ?array $columnLabels = null): array
    {
        $labels = $columnLabels ?? ProcurementLineGridColumns::PR_LABELS;
        $rows = $this->gridParser->labeledRows($raw, $labels);

        $lines = [];
        foreach ($rows as $index => $row) {
            $description = trim((string) ($row['Description'] ?? $row['description'] ?? ''));
            $itemCode = trim((string) ($row['Item Code'] ?? $row['item_code'] ?? $row['Item'] ?? ''));
            if ($description === '' && $itemCode === '') {
                continue;
            }

            if ($description === '') {
                $description = $itemCode;
            }

            $quantity = (float) ($row['Qty'] ?? $row['quantity'] ?? 1);
            $unitPrice = (float) ($row['Unit price'] ?? $row['unit_price'] ?? 0);
            $metadata = ProcurementLineGridColumns::metadataFromLabeledRow($row);

            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
                'line_order' => $index,
                'metadata_json' => $metadata !== [] ? $metadata : null,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{description: string, quantity: float, unit_price: float, amount?: float, line_order?: int}>  $lines
     */
    public function recalculateTotal(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) ($line['unit_price'] ?? 0);
            $total += round($qty * $price, 2);
        }

        return round($total, 2);
    }

    /**
     * @param  list<array{description: string, quantity: float, unit_price: float, amount: float, line_order?: int, metadata_json?: array<string, string>|null}>  $lines
     */
    public function syncLines(ProcurementPr $pr, array $lines): void
    {
        $pr->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            ProcurementPrLine::query()->create([
                'pr_id' => $pr->id,
                'line_order' => (int) ($line['line_order'] ?? $index),
                'description' => (string) $line['description'],
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'amount' => (float) ($line['amount'] ?? round((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2)),
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'expense_type' => $line['expense_type'] ?? null,
                'budget_line_id' => $line['budget_line_id'] ?? null,
                'metadata_json' => is_array($line['metadata_json'] ?? null) ? $line['metadata_json'] : null,
            ]);
        }
    }
}
