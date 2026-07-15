<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;
use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use Carbon\Carbon;

final class ProcurementExcelPackExportService
{
    public function __construct(
        private readonly ProcurementExportQueryService $query,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null, period?: string|null}  $input
     */
    public function buildBinary(array $input, ProcurementExportDateRangeService $dateRange): string
    {
        $range = $dateRange->resolve($input);
        $writer = new ProcurementExcelWorkbookWriter;

        foreach (ProcurementExportEntity::all() as $entity) {
            $writer->addSheet(
                ProcurementExportEntity::sheetName($entity),
                $this->query->sheetRows($entity, $range['from'], $range['to']),
            );
        }

        return $writer->toBinaryString();
    }

    /**
     * @param  array{from?: string|null, to?: string|null, period?: string|null}  $input
     */
    public function filename(array $input, ProcurementExportDateRangeService $dateRange): string
    {
        $range = $dateRange->resolve($input);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($range['label'])) ?: 'export';

        return 'procurement-pack-'.trim($slug, '-').'.xlsx';
    }

    /**
     * @param  array{from?: string|null, to?: string|null, period?: string|null}  $input
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function resolveRange(array $input, ProcurementExportDateRangeService $dateRange): array
    {
        return $dateRange->resolve($input);
    }
}
