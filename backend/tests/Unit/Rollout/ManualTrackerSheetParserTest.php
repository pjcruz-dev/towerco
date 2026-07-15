<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Support\ManualTrackerSheetParser;
use Tests\TestCase;

final class ManualTrackerSheetParserTest extends TestCase
{
    public function test_detects_transposed_pmo_layout_and_builds_site_payload(): void
    {
        $rows = [
            ['EXCEL', 'EXCEL'],
            [null, 'TCO SITE ID', 'ATC-VIS-0444'],
            [null, 'MNO Anchor Site ID', 'NS-NTG21-D35'],
            [null, 'MNO Anchor', 'GLOBE'],
            [null, 'Project Type', 'MACRO'],
            [null, 'Region', 'VIS'],
            [null, 'Area', 'CEBU'],
            [null, 'Territory', 'T6'],
            [null, 'Search Ring Name', 'Ring-A'],
            [null, 'TSSR Approved', '45474'],
        ];

        $parser = new ManualTrackerSheetParser;

        $this->assertTrue($parser->isTransposedLayout($rows));

        $payloads = $parser->payloadsFromSheetRows($rows);

        $this->assertCount(1, $payloads);
        $this->assertSame('ATC-VIS-0444', $payloads[0]['tco_site_id']);
        $this->assertSame('globe', $payloads[0]['mno']);
        $this->assertSame('bts', $payloads[0]['project_type']);
        $this->assertSame('VIS', $payloads[0]['region']);
        $this->assertSame('2024-07-01', $payloads[0]['tssr_approved_date']);
    }

    public function test_row_oriented_layout_still_works(): void
    {
        $rows = [
            ['TCO SITE ID', 'MNO Anchor', 'Project Type', 'Region'],
            ['ATC-NCR-0001', 'globe', 'bts', 'NCR'],
        ];

        $parser = new ManualTrackerSheetParser;

        $this->assertFalse($parser->isTransposedLayout($rows));

        $payloads = $parser->payloadsFromSheetRows($rows);

        $this->assertCount(1, $payloads);
        $this->assertSame('ATC-NCR-0001', $payloads[0]['tco_site_id']);
        $this->assertSame('globe', $payloads[0]['mno']);
        $this->assertSame('bts', $payloads[0]['project_type']);
    }

    public function test_row_oriented_pmo_export_layout_from_screenshot(): void
    {
        $rows = [
            ['TCO SITE ID', 'MNO Anchor Site ID', 'MNO Anchor', 'Globe Project Batch Tagging', 'Project Type', 'Region', 'Area', 'Territory', 'Search Ring Name', 'Solution'],
            ['ATC-VIS-0444', 'NS-NTG21-D35', 'GLOBE', 'BTS', 'MACRO', 'VIS', 'CEBU', 'T6', 'SMHypermart_PuebloVerde', 'RTT'],
            ['ATC-MIN-0230', 'NS-BIZ20-G35', 'GLOBE', 'BTS', 'MACRO', 'MIN', 'DAVAO DEL SUR', 'T7', 'Torre Lorenzo', 'GBT'],
            ['ATC-NCR-0816', 'NS-NTG21-D35', 'GLOBE', 'Golden City', 'SMALL CELL', 'NCR', 'TAGUIG CITY', 'T1', 'BGC_9thAve', 'SC'],
        ];

        $parser = new ManualTrackerSheetParser;

        $this->assertFalse($parser->isTransposedLayout($rows));
        $payloads = $parser->payloadsFromSheetRows($rows);

        $this->assertCount(3, $payloads);
        $this->assertSame('ATC-VIS-0444', $payloads[0]['tco_site_id']);
        $this->assertSame('globe', $payloads[0]['mno']);
        $this->assertSame('bts', $payloads[2]['project_type']);
        $this->assertSame('CEBU', $payloads[0]['area']);
    }

    public function test_normalizes_coordinates_with_degree_symbol(): void
    {
        $rows = [
            ['TCO SITE ID', 'Latitude (Actual)', 'Longitude (Actual)', 'MNO Anchor', 'Project Type'],
            ['ATC-TEST-001', '14.541466°', '121.047108°', 'GLOBE', 'MACRO'],
        ];

        $parser = new ManualTrackerSheetParser;
        $payloads = $parser->payloadsFromSheetRows($rows);

        $this->assertSame('14.541466', $payloads[0]['latitude']);
        $this->assertSame('121.047108', $payloads[0]['longitude']);
    }

    public function test_transposed_labels_in_column_a_without_values_returns_no_payloads(): void
    {
        $rows = [
            ['TCO SITE ID'],
            ['MNO Anchor Site ID'],
            ['MNO Anchor'],
            ['Project Type'],
            ['Region'],
            ['Area'],
            ['Territory'],
            ['Search Ring Name'],
            ['TSSR Approved'],
        ];

        $parser = new ManualTrackerSheetParser;

        $this->assertTrue($parser->isTransposedLayout($rows));
        $this->assertSame([], $parser->payloadsFromSheetRows($rows));
    }
}
