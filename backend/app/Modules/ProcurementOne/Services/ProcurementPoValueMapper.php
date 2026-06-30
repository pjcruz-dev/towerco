<?php



declare(strict_types=1);



namespace App\Modules\ProcurementOne\Services;



use App\Modules\ProcurementOne\Models\ProcurementPo;

use App\Modules\ProcurementOne\Models\ProcurementPoLine;

use App\Modules\ProcurementOne\Support\ProcurementGridValueParser;

use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;



final class ProcurementPoValueMapper

{

    public function __construct(

        private readonly ProcurementGridValueParser $gridParser,

    ) {}



    /**

     * @return array<string, mixed>

     */

    public function toEApprovalValues(ProcurementPo $po, ?string $prDocumentNo = null): array

    {

        $po->loadMissing('lines');



        $gridRows = [];

        foreach ($po->lines as $line) {

            $gridRows[] = ProcurementLineGridColumns::poGridRow($line);

        }



        return array_filter([

            'purchase_requisition_document_no' => $prDocumentNo,

            'vendor' => $po->vendor_code,

            'supplier' => $po->supplier ?? $po->vendor_name,

            'ship_to' => $po->ship_to,

            'delivery_date' => $po->delivery_date?->format('Y-m-d'),

            'required_delivery_date' => $po->delivery_date?->format('Y-m-d'),

            'delivery_location' => $po->delivery_location,

            'payment_terms' => $po->payment_terms,

            'currency_code' => $po->currency_code,

            'exchange_rate' => (string) $po->exchange_rate,

            'line_items' => $gridRows,

            'vatable_amount' => (string) $po->vatable_amount,

            'vat_exempt_amount' => (string) $po->vat_exempt_amount,

            'zero_rated_amount' => (string) $po->zero_rated_amount,

            'vat_rate' => (string) $po->vat_rate,

            'vat_amount' => (string) $po->vat_amount,

            'total_vat_inclusive' => (string) $po->total_vat_inclusive,

            'less_discount' => (string) $po->less_discount,

            'grand_total' => (string) $po->grand_total,

            'total_amount' => (string) $po->vatable_amount,

        ], static fn ($value) => $value !== null && $value !== '');

    }



    /**

     * @param  list<array{item?: string, description?: string, uom?: string, quantity?: float|string, unit_price?: float|string, discount?: float|string, pr_id?: string, pr_line_id?: string, metadata_json?: array<string, string>|null}>  $lines

     */

    public function syncLines(ProcurementPo $po, array $lines): void

    {

        $po->lines()->delete();



        foreach (array_values($lines) as $index => $line) {

            $metadata = is_array($line['metadata_json'] ?? null) ? $line['metadata_json'] : [];

            $itemCode = trim((string) ($metadata['item_code'] ?? $line['item'] ?? ''));



            ProcurementPoLine::query()->create([

                'po_id' => $po->id,

                'pr_id' => $line['pr_id'] ?? null,

                'pr_line_id' => $line['pr_line_id'] ?? null,

                'line_order' => (int) ($line['line_order'] ?? $index),

                'item' => $itemCode !== '' ? $itemCode : null,

                'description' => (string) ($line['description'] ?? ''),

                'uom' => $line['uom'] ?? ProcurementLineGridColumns::resolveUom($metadata),

                'quantity' => (float) ($line['quantity'] ?? 1),

                'unit_price' => (float) ($line['unit_price'] ?? 0),

                'discount' => (float) ($line['discount'] ?? 0),

                'amount' => (float) ($line['amount'] ?? 0),

                'cost_center_id' => $line['cost_center_id'] ?? null,

                'expense_type' => $line['expense_type'] ?? null,

                'budget_line_id' => $line['budget_line_id'] ?? null,

                'metadata_json' => $metadata !== [] ? $metadata : null,

            ]);

        }

    }



    /**

     * @param  list<string>|null  $columnLabels

     * @return list<array{item: ?string, description: string, uom: string, quantity: float, unit_price: float, discount: float, amount: float, line_order: int, metadata_json?: array<string, string>|null}>

     */

    public function linesFromGridValue(mixed $raw, ?array $columnLabels = null): array

    {

        $labels = $columnLabels ?? ProcurementLineGridColumns::PO_LABELS;

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



            $metadata = ProcurementLineGridColumns::metadataFromLabeledRow($row);

            if ($itemCode !== '' && ! isset($metadata['item_code'])) {

                $metadata['item_code'] = $itemCode;

            }



            $qty = (float) ($row['Qty'] ?? $row['quantity'] ?? 1);

            $unitPrice = (float) ($row['Unit price'] ?? $row['unit_price'] ?? 0);

            $discount = (float) ($row['Discount'] ?? $row['discount'] ?? 0);



            $lines[] = [

                'item' => $itemCode !== '' ? $itemCode : null,

                'description' => $description,

                'uom' => ProcurementLineGridColumns::resolveUom($metadata),

                'quantity' => $qty,

                'unit_price' => $unitPrice,

                'discount' => $discount,

                'amount' => max(0, round(($qty * $unitPrice) - $discount, 2)),

                'line_order' => $index,

                'metadata_json' => $metadata !== [] ? $metadata : null,

            ];

        }



        return $lines;

    }

}

