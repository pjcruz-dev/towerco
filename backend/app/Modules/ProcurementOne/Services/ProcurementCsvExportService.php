<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use Carbon\Carbon;
use Generator;

final class ProcurementCsvExportService
{
    public function __construct(
        private readonly ProcurementExportQueryService $query,
        private readonly ProcurementExportColumnMapService $columnMaps,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(string $entity): array
    {
        abort_unless(ProcurementExportEntity::isValid($entity), 422, __('Invalid export entity.'));

        return $this->columnMaps->enabledHeaders($entity);
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(string $entity, Carbon $from, Carbon $to): Generator
    {
        abort_unless(ProcurementExportEntity::isValid($entity), 422, __('Invalid export entity.'));

        return $this->query->rowGenerator($entity, $from, $to);
    }

    public function filename(string $entity, Carbon $from, Carbon $to): string
    {
        return sprintf(
            'procurement-%s-%s-%s.csv',
            $entity,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );
    }
}
