<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementPrLine;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use Carbon\Carbon;
use Generator;

final class ProcurementExportQueryService
{
    private const MAX_ROWS = 10000;

    public function __construct(
        private readonly ProcurementExportColumnMapService $columnMaps,
    ) {}

    /**
     * @return list<list<string|int|float|null>>
     */
    public function sheetRows(string $entity, Carbon $from, Carbon $to): array
    {
        $headers = $this->columnMaps->enabledHeaders($entity);
        $rows = [$headers];

        foreach ($this->rowGenerator($entity, $from, $to) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rowGenerator(string $entity, Carbon $from, Carbon $to): Generator
    {
        $keys = $this->columnMaps->enabledKeys($entity);

        return match ($entity) {
            ProcurementExportEntity::VENDORS => $this->vendorRows($keys, $from, $to),
            ProcurementExportEntity::PRS => $this->prRows($keys, $from, $to),
            ProcurementExportEntity::PR_LINES => $this->prLineRows($keys, $from, $to),
            ProcurementExportEntity::POS => $this->poRows($keys, $from, $to),
            ProcurementExportEntity::PO_LINES => $this->poLineRows($keys, $from, $to),
            default => (static function (): Generator {
                if (false) {
                    yield [];
                }
            })(),
        };
    }

    /**
     * @param  list<string>  $keys
     * @return Generator<int, list<string|int|float|null>>
     */
    private function vendorRows(array $keys, Carbon $from, Carbon $to): Generator
    {
        $vendors = ProcurementVendor::query()
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('updated_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderBy('vendor_code')
            ->limit(self::MAX_ROWS)
            ->get();

        foreach ($vendors as $vendor) {
            $contact = is_array($vendor->contact_json) ? $vendor->contact_json : [];
            $payload = [
                'vendor_code' => $vendor->vendor_code,
                'company_name' => $vendor->company_name,
                'tax_id' => $vendor->tax_id,
                'category' => $vendor->category,
                'accreditation_status' => $vendor->accreditation_status,
                'accreditation_expires_at' => $vendor->accreditation_expires_at?->format('Y-m-d'),
                'is_active' => $vendor->is_active ? 'yes' : 'no',
                'contact_email' => (string) ($contact['email'] ?? ''),
                'contact_phone' => (string) ($contact['phone'] ?? $contact['mobile'] ?? ''),
                'updated_at' => $vendor->updated_at?->toIso8601String(),
            ];

            yield $this->projectRow($keys, $payload);
        }
    }

    /**
     * @param  list<string>  $keys
     * @return Generator<int, list<string|int|float|null>>
     */
    private function prRows(array $keys, Carbon $from, Carbon $to): Generator
    {
        $prs = ProcurementPr::query()
            ->with(['requestor:id,name'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('approved_at', [$from, $to])
                    ->orWhereBetween('submitted_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ROWS)
            ->get();

        foreach ($prs as $pr) {
            $payload = [
                'document_no' => $pr->document_no,
                'status' => $pr->status,
                'title' => $pr->title,
                'department' => $pr->department,
                'requestor_name' => $pr->requestor?->name,
                'estimated_total' => (float) $pr->estimated_total,
                'currency' => $pr->currency,
                'committed_po_amount' => (float) $pr->committed_po_amount,
                'submitted_at' => $pr->submitted_at?->toIso8601String(),
                'approved_at' => $pr->approved_at?->toIso8601String(),
                'created_at' => $pr->created_at?->toIso8601String(),
            ];

            yield $this->projectRow($keys, $payload);
        }
    }

    /**
     * @param  list<string>  $keys
     * @return Generator<int, list<string|int|float|null>>
     */
    private function prLineRows(array $keys, Carbon $from, Carbon $to): Generator
    {
        $lines = ProcurementPrLine::query()
            ->with(['pr:id,document_no,approved_at,submitted_at,created_at'])
            ->whereHas('pr', function ($query) use ($from, $to): void {
                $query->where(function ($inner) use ($from, $to): void {
                    $inner->whereBetween('approved_at', [$from, $to])
                        ->orWhereBetween('submitted_at', [$from, $to])
                        ->orWhereBetween('created_at', [$from, $to]);
                });
            })
            ->orderBy('pr_id')
            ->orderBy('line_order')
            ->limit(self::MAX_ROWS)
            ->get();

        foreach ($lines as $line) {
            $payload = [
                'pr_document_no' => $line->pr?->document_no,
                'line_order' => (int) $line->line_order,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'amount' => (float) $line->amount,
                'expense_type' => $line->expense_type,
                'cost_center_id' => $line->cost_center_id,
            ];

            yield $this->projectRow($keys, $payload);
        }
    }

    /**
     * @param  list<string>  $keys
     * @return Generator<int, list<string|int|float|null>>
     */
    private function poRows(array $keys, Carbon $from, Carbon $to): Generator
    {
        $pos = ProcurementPo::query()
            ->with(['requestor:id,name', 'contract:id,document_no'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('approved_at', [$from, $to])
                    ->orWhereBetween('submitted_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ROWS)
            ->get();

        foreach ($pos as $po) {
            $payload = [
                'document_no' => $po->document_no,
                'status' => $po->status,
                'vendor_code' => $po->vendor_code,
                'vendor_name' => $po->vendor_name,
                'supplier' => $po->supplier,
                'requestor_name' => $po->requestor?->name,
                'grand_total' => (float) $po->grand_total,
                'currency_code' => $po->currency_code,
                'contract_document_no' => $po->contract?->document_no,
                'submitted_at' => $po->submitted_at?->toIso8601String(),
                'approved_at' => $po->approved_at?->toIso8601String(),
                'created_at' => $po->created_at?->toIso8601String(),
            ];

            yield $this->projectRow($keys, $payload);
        }
    }

    /**
     * @param  list<string>  $keys
     * @return Generator<int, list<string|int|float|null>>
     */
    private function poLineRows(array $keys, Carbon $from, Carbon $to): Generator
    {
        $lines = ProcurementPoLine::query()
            ->with(['po:id,document_no,approved_at,submitted_at,created_at', 'purchaseRequisition:id,document_no'])
            ->whereHas('po', function ($query) use ($from, $to): void {
                $query->where(function ($inner) use ($from, $to): void {
                    $inner->whereBetween('approved_at', [$from, $to])
                        ->orWhereBetween('submitted_at', [$from, $to])
                        ->orWhereBetween('created_at', [$from, $to]);
                });
            })
            ->orderBy('po_id')
            ->orderBy('line_order')
            ->limit(self::MAX_ROWS)
            ->get();

        foreach ($lines as $line) {
            $payload = [
                'po_document_no' => $line->po?->document_no,
                'line_order' => (int) $line->line_order,
                'item' => $line->item,
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount' => (float) $line->discount,
                'amount' => (float) $line->amount,
                'pr_document_no' => $line->purchaseRequisition?->document_no,
            ];

            yield $this->projectRow($keys, $payload);
        }
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $payload
     * @return list<string|int|float|null>
     */
    private function projectRow(array $keys, array $payload): array
    {
        $row = [];
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            $row[] = is_bool($value) ? ($value ? 'yes' : 'no') : $value;
        }

        return $row;
    }
}
