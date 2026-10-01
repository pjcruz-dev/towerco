<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynReportSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Metacoresoft-style Invoice Aging & AR Adjustments workbench.
 */
final class InvoiceAgingWorkbenchService
{
    public function __construct(
        private readonly DynRecordService $records,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $asOf = DynReportSupport::parseDate($filters['as_of'] ?? null) ?? now()->startOfDay();
        $customerFilter = trim((string) ($filters['customer'] ?? ''));
        $bracketFilter = trim((string) ($filters['bracket'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $customers = DynReportSupport::titleMap('customers', ['customer_name', 'name', 'company_name']);
        $buckets = [
            'current' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '91_120' => 0.0,
            'over_120' => 0.0,
        ];
        $invoices = [];
        $customerOptions = [];

        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $row) {
            $values = is_array($row->values_json) ? $row->values_json : [];
            $status = strtolower((string) ($row->status ?? $values['status'] ?? ''));
            if (str_contains($status, 'paid') || str_contains($status, 'void') || str_contains($status, 'cancel')) {
                continue;
            }

            $original = DynReportSupport::money(
                $values['total_amount'] ?? $values['gross_amount'] ?? $values['amount'] ?? 0
            );
            $adjustments = DynReportSupport::money(
                $values['adjustments'] ?? $values['discount_amount'] ?? $values['ar_adjustments'] ?? 0
            );
            $balance = DynReportSupport::money(
                $values['balance_due'] ?? max(0, $original - $adjustments)
            );
            if ($balance <= 0 && $original <= 0) {
                continue;
            }
            if ($balance <= 0) {
                $balance = $original;
            }

            $invoiceDate = DynReportSupport::parseDate(
                $values['invoice_date'] ?? $values['transaction_date'] ?? $values['date'] ?? null
            ) ?? ($row->created_at?->copy()->startOfDay() ?? $asOf);
            $due = DynReportSupport::parseDate($values['due_date'] ?? null) ?? $invoiceDate;
            $days = (int) $due->diffInDays($asOf, false);
            if ($days < 0) {
                $days = 0;
            }
            $bracket = $this->bracket($days);

            $customerId = (string) ($values['customer_id'] ?? '');
            $customerName = (string) (
                $values['customer_name']
                ?? ($customerId !== '' ? ($customers[$customerId] ?? null) : null)
                ?? '—'
            );
            $invoiceNo = (string) (
                $values['transaction_number']
                ?? $values['invoice_number']
                ?? $row->title
                ?? $row->source_external_id
                ?? DynReportSupport::shortId((string) $row->id)
            );
            $docType = (string) ($values['document_type'] ?? $values['type'] ?? 'Sales Invoice');

            $customerOptions[$customerName] = true;

            if ($customerFilter !== '' && strcasecmp($customerName, $customerFilter) !== 0) {
                continue;
            }
            if ($bracketFilter !== '' && $bracket !== $bracketFilter) {
                continue;
            }
            if ($search !== '') {
                $hay = strtolower($invoiceNo.' '.$customerName);
                if (! str_contains($hay, strtolower($search))) {
                    continue;
                }
            }

            $buckets[$bracket] += $balance;
            $invoices[] = [
                'id' => (string) $row->id,
                'invoice_number' => $invoiceNo,
                'customer' => $customerName,
                'customer_id' => $customerId !== '' ? $customerId : null,
                'doc_type' => $docType,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $due->toDateString(),
                'original_amount' => round($original, 2),
                'adjustments' => round($adjustments, 2),
                'balance_due' => round($balance, 2),
                'days' => $days,
                'bracket' => $bracket,
                'bracket_label' => $this->bracketLabel($bracket),
                'status' => (string) ($row->status ?? $values['status'] ?? ''),
                'href' => '/dynamic-entities/records/'.$row->id,
                'notes' => (string) ($values['ar_adjustment_notes'] ?? $values['remarks'] ?? ''),
            ];
        }

        usort($invoices, static fn (array $a, array $b): int => ($b['days'] <=> $a['days']) ?: strcmp($a['invoice_number'], $b['invoice_number']));

        $total = array_sum($buckets);
        $customerList = array_keys($customerOptions);
        sort($customerList);

        return [
            'message' => $invoices === []
                ? 'No open sales invoices found. Import or create sales_transactions with a balance due.'
                : null,
            'generated_at' => now()->toIso8601String(),
            'as_of' => $asOf->toDateString(),
            'summary' => [
                'total' => round($total, 2),
                'invoice_count' => count($invoices),
                'current' => round($buckets['current'], 2),
                'd31_60' => round($buckets['31_60'], 2),
                'd61_90' => round($buckets['61_90'], 2),
                'd91_120' => round($buckets['91_120'], 2),
                'over_120' => round($buckets['over_120'], 2),
            ],
            'invoices' => $invoices,
            'filter_options' => [
                'customers' => $customerList,
                'brackets' => [
                    ['key' => 'current', 'label' => 'Current (0-30 Days)'],
                    ['key' => '31_60', 'label' => '31-60 Days'],
                    ['key' => '61_90', 'label' => '61-90 Days'],
                    ['key' => '91_120', 'label' => '91-120 Days'],
                    ['key' => 'over_120', 'label' => '> 120 Days Overdue'],
                ],
            ],
        ];
    }

    /**
     * @param  array{record_id: string, amount: float|int|string, note?: string|null}  $data
     * @return array<string, mixed>
     */
    public function postAdjustment(array $data, TenantUser $actor): array
    {
        $recordId = trim((string) ($data['record_id'] ?? ''));
        $amount = DynReportSupport::money($data['amount'] ?? 0);
        $note = trim((string) ($data['note'] ?? ''));

        if ($recordId === '') {
            throw ValidationException::withMessages(['record_id' => ['Select an invoice.']]);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Adjustment amount must be greater than zero.']]);
        }

        $record = DynRecord::query()->with('entity')->findOrFail($recordId);
        $entity = $record->entity;
        abort_unless($entity instanceof DynEntity && $entity->slug === 'sales_transactions', 404);

        $values = is_array($record->values_json) ? $record->values_json : [];
        $original = DynReportSupport::money($values['total_amount'] ?? $values['gross_amount'] ?? $values['amount'] ?? 0);
        $existingAdj = DynReportSupport::money($values['adjustments'] ?? $values['ar_adjustments'] ?? 0);
        $balance = DynReportSupport::money($values['balance_due'] ?? max(0, $original - $existingAdj));
        if ($balance <= 0) {
            $balance = $original;
        }

        $newAdj = round($existingAdj + $amount, 2);
        $newBalance = max(0, round($balance - $amount, 2));
        $stamp = now()->toDateTimeString();
        $line = trim(($note !== '' ? $note : 'AR adjustment').' · '.$amount.' · '.$stamp);
        $priorNotes = trim((string) ($values['ar_adjustment_notes'] ?? ''));
        $notes = $priorNotes === '' ? $line : ($priorNotes."\n".$line);

        $this->records->update($record, [
            'values' => [
                'adjustments' => $newAdj,
                'ar_adjustments' => $newAdj,
                'balance_due' => $newBalance,
                'ar_adjustment_notes' => $notes,
            ],
        ], $actor);

        return [
            'ok' => true,
            'record_id' => $recordId,
            'adjustments' => $newAdj,
            'balance_due' => $newBalance,
        ];
    }

    private function bracket(int $days): string
    {
        if ($days <= 30) {
            return 'current';
        }
        if ($days <= 60) {
            return '31_60';
        }
        if ($days <= 90) {
            return '61_90';
        }
        if ($days <= 120) {
            return '91_120';
        }

        return 'over_120';
    }

    private function bracketLabel(string $key): string
    {
        return match ($key) {
            'current' => '0-30 Days',
            '31_60' => '31-60 Days',
            '61_90' => '61-90 Days',
            '91_120' => '91-120 Days',
            default => '> 120 Days',
        };
    }
}
