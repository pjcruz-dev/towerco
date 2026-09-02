<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

final class DailySalesReportService
{
    /**
     * @param  array{date?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $date = DynReportSupport::parseDate($filters['date'] ?? now()->toDateString());
        $dateStr = $date?->toDateString() ?? now()->toDateString();
        $customers = DynReportSupport::titleMap('customers', ['customer_name', 'name']);

        $gross = 0.0;
        $vat = 0.0;
        $returns = 0.0;
        $invoiceCount = 0;
        $byType = [];
        $byCustomer = [];
        $rows = [];

        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $sale) {
            $values = $sale->values_json ?? [];
            $txDate = DynReportSupport::parseDate($values['transaction_date'] ?? null);
            if ($txDate === null || $txDate->toDateString() !== $dateStr) {
                continue;
            }

            $type = (string) ($values['transaction_type'] ?? 'Sale');
            $amount = DynReportSupport::money($values['total_amount'] ?? 0);
            $tax = DynReportSupport::money($values['tax_amount'] ?? 0);
            $customerId = (string) ($values['customer_id'] ?? '');
            $customerName = $customers[$customerId] ?? (DynReportSupport::isUuid($customerId) ? DynReportSupport::shortId($customerId) : '—');

            $invoiceCount++;
            if (str_contains(strtolower($type), 'return')) {
                $returns += $amount;
            } else {
                $gross += $amount;
                $vat += $tax;
            }

            $byType[$type] = ($byType[$type] ?? 0) + $amount;
            $byCustomer[$customerName] = ($byCustomer[$customerName] ?? 0) + $amount;

            $rows[] = [
                'id' => $sale->id,
                'transaction_no' => (string) ($values['transaction_number'] ?? $sale->title ?? ''),
                'customer' => $customerName,
                'type' => $type,
                'status' => (string) ($sale->status ?? $values['status'] ?? ''),
                'subtotal' => DynReportSupport::money($values['subtotal'] ?? 0),
                'tax' => $tax,
                'discount' => DynReportSupport::money($values['discount_amount'] ?? 0),
                'total_amount' => $amount,
            ];
        }

        return [
            'message' => null,
            'date' => $dateStr,
            'kpis' => [
                'total_gross_sales' => round($gross, 2),
                'sales_invoices' => round($gross, 2),
                'invoice_count' => $invoiceCount,
                'vat_collected' => round($vat, 2),
                'sales_returns' => round($returns, 2),
            ],
            'by_type' => collect($byType)
                ->map(fn (float $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => round($v, 2)])
                ->values()
                ->all(),
            'top_customers' => collect($byCustomer)
                ->map(fn (float $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => round($v, 2)])
                ->sortByDesc('value')
                ->values()
                ->take(10)
                ->all(),
            'transactions' => $rows,
        ];
    }
}
