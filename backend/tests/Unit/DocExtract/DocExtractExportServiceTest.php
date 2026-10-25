<?php

declare(strict_types=1);

namespace Tests\Unit\DocExtract;

use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Services\DocExtractExportService;
use Tests\TestCase;
use ZipArchive;

final class DocExtractExportServiceTest extends TestCase
{
    public function test_xlsx_export_builds_a_workbook_for_scalar_and_table_fields(): void
    {
        $batch = new DocExtractBatch([
            'field_schema' => [
                ['key' => 'vendor', 'label' => 'Vendor', 'type' => 'text'],
                [
                    'key' => 'lines',
                    'label' => 'Lines',
                    'type' => 'table',
                    'columns' => [
                        ['key' => 'item', 'label' => 'Item'],
                        ['key' => 'amount', 'label' => 'Amount'],
                    ],
                ],
            ],
        ]);
        $batch->id = '01a0855f-b9ca-7161-a55d-bc078b1f3943';

        $document = new DocExtractDocument([
            'original_filename' => 'invoice.pdf',
            'field_values' => [
                'vendor' => 'Acme',
                'lines' => json_encode([
                    'rows' => [
                        ['item' => 'Cable', 'amount' => '12.00'],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $batch->setRelation('documents', collect([$document]));
        $batch->setRelation('template', null);

        $response = app(DocExtractExportService::class)->exportXlsx($batch);

        $this->assertSame(
            'attachment; filename=extraction-results-01a0855f-b9ca-7161-a55d-bc078b1f3943.xlsx',
            $response->headers->get('Content-Disposition'),
        );

        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();

        $this->assertIsString($binary);
        $this->assertStringStartsWith('PK', $binary);

        $path = tempnam(sys_get_temp_dir(), 'docextract-xlsx-');
        $this->assertNotFalse($path);
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($workbook);
        $this->assertStringContainsString('Extraction Results', $workbook);
        $this->assertStringContainsString('All table details', $workbook);
    }
}
