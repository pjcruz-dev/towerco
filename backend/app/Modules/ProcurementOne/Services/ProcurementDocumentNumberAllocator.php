<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementNumberingSeriesCatalog;
use Illuminate\Support\Facades\DB;

final class ProcurementDocumentNumberAllocator
{
    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
        private readonly ProcurementNumberingSeriesCatalog $catalog,
    ) {}

    public function allocate(string $documentType): string
    {
        return DB::connection('tenant')->transaction(function () use ($documentType): string {
            $all = $this->catalog->resolveAll();
            $series = $all[$documentType] ?? $this->catalog->resolveForType($documentType);
            $sequence = (int) ($series['next_sequence'] ?? 1);
            $prefix = str_replace('{YEAR}', now()->format('Y'), (string) $series['prefix']);
            $padding = max(1, (int) ($series['padding'] ?? 5));
            $documentNo = $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

            $stored = $this->settings->getJson(ProcurementOneSettingsService::NUMBERING_SERIES);
            $stored[$documentType] = array_merge($series, ['next_sequence' => $sequence + 1]);
            $this->settings->setJson(ProcurementOneSettingsService::NUMBERING_SERIES, $stored);

            return $documentNo;
        });
    }

    public function allocateGoodsReceipt(): string
    {
        return $this->allocate(ProcurementDocumentType::GOODS_RECEIPT);
    }
}
