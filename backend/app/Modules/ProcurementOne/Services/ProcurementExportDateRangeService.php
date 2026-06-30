<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use Carbon\Carbon;

final class ProcurementExportDateRangeService
{
    /**
     * @param  array{from?: string|null, to?: string|null, period?: string|null}  $input
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function resolve(array $input): array
    {
        $period = strtolower(trim((string) ($input['period'] ?? 'current_month')));

        if ($period === 'previous_month') {
            $from = Carbon::now()->subMonthNoOverflow()->startOfMonth();
            $to = Carbon::now()->subMonthNoOverflow()->endOfMonth();

            return [
                'from' => $from,
                'to' => $to,
                'label' => $from->format('F Y'),
            ];
        }

        if (! empty($input['from']) && ! empty($input['to'])) {
            $from = Carbon::parse((string) $input['from'])->startOfDay();
            $to = Carbon::parse((string) $input['to'])->endOfDay();

            return [
                'from' => $from,
                'to' => $to,
                'label' => $from->format('Y-m-d').' — '.$to->format('Y-m-d'),
            ];
        }

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        return [
            'from' => $from,
            'to' => $to,
            'label' => $from->format('F Y'),
        ];
    }
}
