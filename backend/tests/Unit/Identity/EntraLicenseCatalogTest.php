<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Support\EntraLicenseCatalog;
use Tests\TestCase;

final class EntraLicenseCatalogTest extends TestCase
{
    public function test_unlicensed_when_no_skus(): void
    {
        $summary = EntraLicenseCatalog::summarize([]);

        $this->assertFalse($summary['licensed']);
        $this->assertNull($summary['label']);
        $this->assertSame([], $summary['names']);
    }

    public function test_picks_primary_e3_over_add_ons(): void
    {
        $summary = EntraLicenseCatalog::summarize([
            '4b9405b0-7788-4568-add1-55668158885a',
            '05e9a617-0261-4cee-bb44-138d3ef5d965',
        ]);

        $this->assertTrue($summary['licensed']);
        $this->assertSame('E3', $summary['label']);
        $this->assertSame(['Microsoft 365 E3', 'Exchange Online Plan 1'], $summary['names']);
    }

    public function test_uses_tenant_subscribed_sku_map(): void
    {
        $summary = EntraLicenseCatalog::summarize(
            ['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            ['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' => 'SPE_E5'],
        );

        $this->assertSame('E5', $summary['label']);
        $this->assertSame(['Microsoft 365 E5'], $summary['names']);
    }

    public function test_extracts_sku_ids_from_graph_payload(): void
    {
        $ids = EntraLicenseCatalog::skuIdsFromGraph([
            'assignedLicenses' => [
                ['skuId' => '05e9a617-0261-4cee-bb44-138d3ef5d965'],
                ['skuId' => ''],
            ],
        ]);

        $this->assertSame(['05e9a617-0261-4cee-bb44-138d3ef5d965'], $ids);
    }
}
