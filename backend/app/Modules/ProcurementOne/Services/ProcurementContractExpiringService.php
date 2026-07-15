<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementContractExpiringService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $withinDays = 90): array
    {
        $until = Carbon::now()->addDays(max(1, $withinDays))->toDateString();

        return ProcurementContract::query()
            ->with(['vendor:id,vendor_code,company_name', 'site:id,site_code,name', 'primaryDocument:id,title,expires_at'])
            ->where('status', ProcurementContractStatus::ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $until)
            ->orderBy('end_date')
            ->limit(200)
            ->get()
            ->map(static fn (ProcurementContract $contract) => [
                'id' => (string) $contract->id,
                'document_no' => $contract->document_no,
                'title' => $contract->title,
                'end_date' => $contract->end_date?->format('Y-m-d'),
                'spend_ceiling' => $contract->spend_ceiling !== null ? (float) $contract->spend_ceiling : null,
                'committed_po_amount' => (float) $contract->committed_po_amount,
                'currency_code' => $contract->currency_code,
                'vendor' => $contract->vendor ? [
                    'id' => (string) $contract->vendor->id,
                    'vendor_code' => $contract->vendor->vendor_code,
                    'company_name' => $contract->vendor->company_name,
                ] : null,
                'site' => $contract->site ? [
                    'id' => (string) $contract->site->id,
                    'site_code' => $contract->site->site_code,
                    'name' => $contract->site->name,
                ] : null,
                'primary_document' => $contract->primaryDocument ? [
                    'id' => (string) $contract->primaryDocument->id,
                    'title' => $contract->primaryDocument->title,
                    'expires_at' => $contract->primaryDocument->expires_at?->toIso8601String(),
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{within_30: int, within_60: int, within_90: int}
     */
    public function summaryCounts(): array
    {
        $now = Carbon::now();

        return [
            'within_30' => $this->countUntil($now->copy()->addDays(30)),
            'within_60' => $this->countUntil($now->copy()->addDays(60)),
            'within_90' => $this->countUntil($now->copy()->addDays(90)),
        ];
    }

    public function paginateExpiring(int $page, int $perPage, int $withinDays = 90): LengthAwarePaginator
    {
        $until = Carbon::now()->addDays(max(1, $withinDays))->toDateString();

        return ProcurementContract::query()
            ->with(['vendor:id,vendor_code,company_name', 'site:id,site_code,name'])
            ->where('status', ProcurementContractStatus::ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $until)
            ->orderBy('end_date')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function countUntil(Carbon $until): int
    {
        return (int) ProcurementContract::query()
            ->where('status', ProcurementContractStatus::ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $until->toDateString())
            ->count();
    }
}
