<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

final class BankReconciliationReportService
{
    /**
     * @param  array{bank_account_id?: string|null, period?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $accounts = DynReportSupport::recordsForSlug('bank_accounts');
        $accountOptions = $accounts->map(function ($account): array {
            $values = $account->values_json ?? [];

            return [
                'id' => $account->id,
                'name' => (string) ($values['account_name'] ?? $account->title ?? 'Account'),
                'bank_name' => (string) ($values['bank_name'] ?? ''),
                'account_number' => (string) ($values['account_number'] ?? ''),
                'current_balance' => DynReportSupport::money($values['current_balance'] ?? 0),
            ];
        })->sortBy('name')->values()->all();

        $bankAccountId = $this->nullableString($filters['bank_account_id'] ?? null);
        if ($bankAccountId === null) {
            return [
                'message' => null,
                'selected' => false,
                'account' => null,
                'transactions' => [],
                'summary' => [
                    'statement_balance' => 0,
                    'book_balance' => 0,
                    'uncleared_count' => 0,
                    'uncleared_amount' => 0,
                ],
                'filter_options' => [
                    'bank_accounts' => $accountOptions,
                    'periods' => $this->periodOptions(),
                ],
            ];
        }

        $account = collect($accountOptions)->firstWhere('id', $bankAccountId);
        $period = $this->nullableString($filters['period'] ?? null);
        $periodStart = null;
        $periodEnd = null;
        if ($period !== null && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $periodStart = DynReportSupport::parseDate($m[1].'-'.$m[2].'-01');
            $periodEnd = $periodStart?->copy()->endOfMonth();
        }

        $txs = [];
        $uncleared = 0.0;
        $unclearedCount = 0;
        foreach (DynReportSupport::recordsForSlug('bank_transactions') as $tx) {
            $values = $tx->values_json ?? [];
            if ((string) ($values['bank_account_id'] ?? '') !== $bankAccountId) {
                continue;
            }
            $date = DynReportSupport::parseDate($values['transaction_date'] ?? null);
            if ($periodStart !== null && ($date === null || $date->lt($periodStart))) {
                continue;
            }
            if ($periodEnd !== null && ($date === null || $date->gt($periodEnd))) {
                continue;
            }
            $amount = DynReportSupport::money($values['amount'] ?? 0);
            $reconciled = ! empty($values['reconciliation_id']);
            if (! $reconciled) {
                $uncleared += $amount;
                $unclearedCount++;
            }
            $txs[] = [
                'id' => $tx->id,
                'date' => (string) ($values['transaction_date'] ?? ''),
                'reference' => (string) ($values['bank_reference'] ?? ''),
                'type' => (string) ($values['transaction_type'] ?? ''),
                'description' => (string) ($values['description'] ?? ''),
                'amount' => $amount,
                'status' => (string) ($tx->status ?? $values['status'] ?? ''),
                'reconciled' => $reconciled,
            ];
        }

        usort($txs, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return [
            'message' => null,
            'selected' => true,
            'account' => $account,
            'transactions' => $txs,
            'summary' => [
                'statement_balance' => $account['current_balance'] ?? 0,
                'book_balance' => $account['current_balance'] ?? 0,
                'uncleared_count' => $unclearedCount,
                'uncleared_amount' => round($uncleared, 2),
            ],
            'filter_options' => [
                'bank_accounts' => $accountOptions,
                'periods' => $this->periodOptions(),
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function periodOptions(): array
    {
        $periods = DynReportSupport::recordsForSlug('fiscal_periods')
            ->map(function ($period): array {
                $values = $period->values_json ?? [];
                $start = (string) ($values['start_date'] ?? '');
                $label = (string) ($values['period_name'] ?? $start);

                return [
                    'id' => $start !== '' ? substr($start, 0, 7) : $period->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();

        if ($periods !== []) {
            return $periods;
        }

        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $d = now()->subMonths($i);
            $out[] = ['id' => $d->format('Y-m'), 'label' => $d->format('F Y')];
        }

        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return (string) $value;
    }
}
