<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementApMatchMode;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;

final class ProcurementApInvoiceMatchingService
{
    public function __construct(
        private readonly ProcurementApInvoiceMatchPolicyService $policy,
        private readonly ProcurementApInvoicePoBalanceService $invoiceBalances,
        private readonly ProcurementGrnPoBalanceService $grnBalances,
    ) {}

    /**
     * @return array{
     *     match_status: string,
     *     variance_amount: float,
     *     blocked: bool,
     *     warning: string|null,
     *     line_results: list<array<string, mixed>>
     * }
     */
    public function evaluate(ProcurementApInvoice $invoice): array
    {
        $invoice->loadMissing(['lines.poLine', 'purchaseOrder.lines', 'goodsReceipt.lines']);
        $po = $invoice->purchaseOrder;
        abort_if($po === null, 422, __('Purchase order not found for this AP invoice.'));

        $policy = $this->policy->policy();
        $matchMode = (string) ($invoice->match_mode ?: $policy['match_mode']);
        $tolerancePct = $policy['tolerance_percent'];
        $lineResults = [];
        $totalVariance = 0.0;
        $messages = [];

        if ($matchMode === ProcurementApMatchMode::THREE_WAY && $policy['require_grn_posted']) {
            $grn = $invoice->goodsReceipt;
            if ($grn === null || (string) $grn->status !== ProcurementGrnStatus::POSTED) {
                $message = __('3-way match requires a posted goods receipt.');
                if ($this->policy->blocksMismatch()) {
                    throw ValidationException::withMessages(['grn_id' => [$message]]);
                }

                return [
                    'match_status' => 'variance',
                    'variance_amount' => (float) $invoice->grand_total,
                    'blocked' => false,
                    'warning' => $message,
                    'line_results' => [],
                ];
            }
        }

        foreach ($invoice->lines as $line) {
            /** @var ProcurementPoLine|null $poLine */
            $poLine = $line->poLine ?? ProcurementPoLine::query()->find($line->po_line_id);
            if ($poLine === null) {
                continue;
            }

            $qtyInvoiced = (float) $line->quantity_invoiced;
            $poRemaining = $this->invoiceBalances->remainingQuantityForPoLine(
                (string) $poLine->id,
                (float) $poLine->quantity,
                (string) $invoice->id,
            );
            $allowedQty = $poRemaining;

            if ($matchMode === ProcurementApMatchMode::THREE_WAY) {
                $grnReceived = $this->grnBalances->receivedQuantityForPoLine((string) $poLine->id);
                if ($invoice->grn_id !== null) {
                    $grnReceived = 0.0;
                    $grn = $invoice->goodsReceipt;
                    if ($grn !== null) {
                        foreach ($grn->lines as $grnLine) {
                            if ((string) $grnLine->po_line_id === (string) $poLine->id) {
                                $grnReceived += (float) $grnLine->quantity_received;
                            }
                        }
                    }
                }
                $alreadyInvoiced = $this->invoiceBalances->invoicedQuantityForPoLine((string) $poLine->id, (string) $invoice->id);
                $allowedQty = max(0, min($poRemaining, round($grnReceived - $alreadyInvoiced, 4)));
            }

            $allowedMax = $allowedQty + ($allowedQty * ($tolerancePct / 100));
            $lineVariance = max(0, round($qtyInvoiced - $allowedMax, 4));
            $amountVariance = max(0, round($lineVariance * (float) $line->unit_price, 2));
            $totalVariance += $amountVariance;

            $lineOk = $qtyInvoiced <= $allowedMax + 0.0001;
            $lineResults[] = [
                'po_line_id' => (string) $poLine->id,
                'description' => $line->description,
                'quantity_invoiced' => $qtyInvoiced,
                'allowed_quantity' => round($allowedMax, 4),
                'matched' => $lineOk,
                'variance_amount' => $amountVariance,
            ];

            if (! $lineOk) {
                $messages[] = __(
                    'Line :description invoiced qty :qty exceeds allowed :max.',
                    [
                        'description' => $line->description,
                        'qty' => number_format($qtyInvoiced, 4),
                        'max' => number_format($allowedMax, 4),
                    ],
                );
            }
        }

        $poTotal = (float) $po->grand_total;
        $invoicedTotal = $this->invoiceBalances->invoicedAmountForPo((string) $po->id, (string) $invoice->id);
        $invoiceTotal = (float) $invoice->grand_total;
        $allowedAmount = $poTotal - $invoicedTotal;
        $allowedAmountMax = $allowedAmount + ($allowedAmount * ($tolerancePct / 100));
        if ($invoiceTotal > $allowedAmountMax + 0.01) {
            $amountOver = round($invoiceTotal - $allowedAmountMax, 2);
            $totalVariance = max($totalVariance, $amountOver);
            $messages[] = __('Invoice total exceeds open PO balance by :amount.', [
                'amount' => number_format($amountOver, 2),
            ]);
        }

        $matched = $messages === [];
        $warning = $matched ? null : implode(' ', $messages);

        if (! $matched && $this->policy->blocksMismatch()) {
            throw ValidationException::withMessages([
                'lines' => [$warning ?? __('AP invoice does not match PO/GRN within tolerance.')],
            ]);
        }

        return [
            'match_status' => $matched ? 'matched' : 'variance',
            'variance_amount' => round($totalVariance, 2),
            'blocked' => false,
            'warning' => $warning,
            'line_results' => $lineResults,
        ];
    }
}
