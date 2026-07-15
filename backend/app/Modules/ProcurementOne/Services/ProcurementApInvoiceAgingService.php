<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementCreditNote;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementCreditNoteStatus;
use Carbon\Carbon;

final class ProcurementApInvoiceAgingService
{
    public function __construct(
        private readonly ProcurementApInvoiceOpenBalanceService $balances,
    ) {}

    /**
     * @return array{
     *   buckets: list<array{key: string, label: string, count: int, amount: float}>,
     *   total_open: float,
     *   total_count: int
     * }
     */
    public function snapshot(): array
    {
        $today = Carbon::today();
        $buckets = [
            'current' => ['key' => 'current', 'label' => 'Current', 'count' => 0, 'amount' => 0.0],
            '1_30' => ['key' => '1_30', 'label' => '1–30 days', 'count' => 0, 'amount' => 0.0],
            '31_60' => ['key' => '31_60', 'label' => '31–60 days', 'count' => 0, 'amount' => 0.0],
            '61_90' => ['key' => '61_90', 'label' => '61–90 days', 'count' => 0, 'amount' => 0.0],
            'over_90' => ['key' => 'over_90', 'label' => '90+ days', 'count' => 0, 'amount' => 0.0],
        ];

        $invoices = ProcurementApInvoice::query()
            ->where('status', ProcurementApInvoiceStatus::APPROVED)
            ->get();

        $creditByInvoice = ProcurementCreditNote::query()
            ->where('status', ProcurementCreditNoteStatus::APPROVED)
            ->whereNotNull('ap_invoice_id')
            ->get()
            ->groupBy('ap_invoice_id')
            ->map(static fn ($notes) => (float) $notes->sum('amount'));

        $totalOpen = 0.0;
        $totalCount = 0;

        foreach ($invoices as $invoice) {
            $invoiceId = (string) $invoice->id;
            $open = max(0, round(
                (float) $invoice->grand_total
                - ($creditByInvoice[$invoiceId] ?? 0.0)
                - $this->balances->paidAmountForInvoice($invoiceId),
                2,
            ));
            if ($open <= 0.01) {
                continue;
            }

            $dueDate = $invoice->due_date ?? $invoice->invoice_date ?? $invoice->approved_at?->toDateString();
            $daysPastDue = $dueDate !== null ? $today->diffInDays(Carbon::parse($dueDate), false) : 0;
            $daysPastDue = -$daysPastDue;

            $bucketKey = match (true) {
                $daysPastDue <= 0 => 'current',
                $daysPastDue <= 30 => '1_30',
                $daysPastDue <= 60 => '31_60',
                $daysPastDue <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucketKey]['count']++;
            $buckets[$bucketKey]['amount'] = round($buckets[$bucketKey]['amount'] + $open, 2);
            $totalOpen += $open;
            $totalCount++;
        }

        return [
            'buckets' => array_values($buckets),
            'total_open' => round($totalOpen, 2),
            'total_count' => $totalCount,
        ];
    }
}
