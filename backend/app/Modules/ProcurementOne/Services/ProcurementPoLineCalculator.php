<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

final class ProcurementPoLineCalculator
{
    /**
     * @param  list<array{item?: string, description?: string, uom?: string, quantity?: float|string, unit_price?: float|string, discount?: float|string}>  $lines
     * @return array{
     *     lines: list<array{item: string, description: string, uom: string, quantity: float, unit_price: float, discount: float, amount: float, line_order: int}>,
     *     vatable_amount: float,
     *     vat_amount: float,
     *     total_vat_inclusive: float,
     *     grand_total: float,
     *     total_amount: float
     * }
     */
    public function calculate(
        array $lines,
        float $vatExemptAmount = 0,
        float $zeroRatedAmount = 0,
        float $vatRate = 12,
        float $lessDiscount = 0,
    ): array {
        $normalized = [];
        $vatable = 0.0;

        foreach (array_values($lines) as $index => $line) {
            $qty = max(0, (float) ($line['quantity'] ?? 1));
            $unitPrice = max(0, (float) ($line['unit_price'] ?? 0));
            $discount = max(0, (float) ($line['discount'] ?? 0));
            $amount = max(0, round(($qty * $unitPrice) - $discount, 2));
            $vatable += $amount;

            $normalized[] = [
                'item' => trim((string) ($line['item'] ?? '')),
                'description' => trim((string) ($line['description'] ?? '')),
                'uom' => trim((string) ($line['uom'] ?? 'EA')) ?: 'EA',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'amount' => $amount,
                'line_order' => $index,
            ];
        }

        $vatable = round($vatable, 2);
        $vatExemptAmount = round(max(0, $vatExemptAmount), 2);
        $zeroRatedAmount = round(max(0, $zeroRatedAmount), 2);
        $lessDiscount = round(max(0, $lessDiscount), 2);
        $vatAmount = round($vatable * max(0, $vatRate) / 100, 2);
        $totalVatInclusive = round($vatable + $vatExemptAmount + $zeroRatedAmount + $vatAmount, 2);
        $grandTotal = round(max(0, $totalVatInclusive - $lessDiscount), 2);

        return [
            'lines' => $normalized,
            'vatable_amount' => $vatable,
            'vat_amount' => $vatAmount,
            'total_vat_inclusive' => $totalVatInclusive,
            'grand_total' => $grandTotal,
            'total_amount' => $grandTotal,
        ];
    }
}
