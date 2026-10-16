<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\OfflineDataService;
use App\Modules\DynamicEntities\Support\AssetPrintCatalog;
use App\Modules\DynamicEntities\Support\AssetReturnPrintTemplate;
use App\Modules\DynamicEntities\Support\AssetTransferPrintTemplate;
use App\Modules\DynamicEntities\Support\ItAssetPrintTemplate;
use PHPUnit\Framework\TestCase;

final class OfflineDataSizeTest extends TestCase
{
    public function test_size_is_one_decimal_megabytes(): void
    {
        $this->assertSame('0.0MB', OfflineDataService::formatMegabytes(0));
        $this->assertSame('0.0MB', OfflineDataService::formatMegabytes(15 * 256));
        $this->assertSame('0.9MB', OfflineDataService::formatMegabytes(943_718));
        $this->assertSame('1.5MB', OfflineDataService::formatMegabytes(1_572_864));
    }

    public function test_asset_print_catalog_ids_are_unique(): void
    {
        $ids = array_map(fn (array $row): string => (string) $row['id'], AssetPrintCatalog::all());

        $this->assertSame([
            ItAssetPrintTemplate::TEMPLATE_ID,
            AssetTransferPrintTemplate::TEMPLATE_ID,
            AssetReturnPrintTemplate::TEMPLATE_ID,
        ], $ids);
        $this->assertCount(3, array_unique($ids));
        foreach (AssetPrintCatalog::all() as $row) {
            $this->assertNotSame('', trim((string) $row['template_html']));
            $this->assertNotSame('', trim((string) $row['name']));
        }
    }
}
