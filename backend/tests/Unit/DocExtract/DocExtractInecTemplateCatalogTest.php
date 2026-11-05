<?php

declare(strict_types=1);

namespace Tests\Unit\DocExtract;

use App\Modules\DocExtract\Support\DocExtractInecTemplateCatalog;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use PHPUnit\Framework\TestCase;

final class DocExtractInecTemplateCatalogTest extends TestCase
{
    public function test_published_table_matches_the_inec_sheet_columns(): void
    {
        $definition = DocExtractInecTemplateCatalog::definition();

        $this->assertSame('INEC electric bill', $definition['name']);
        $this->assertSame(DocExtractTemplateStatus::PUBLISHED, $definition['status']);
        $this->assertCount(1, $definition['fields']);

        $field = $definition['fields'][0];
        $this->assertSame('inec_bills', $field['key']);
        $this->assertSame('table', $field['type']);
        $this->assertSame(
            [
                'electric_coop',
                'bill_month',
                'account_number',
                'name',
                'address',
                'meter_number',
                'previous_reading_date',
                'previous_reading',
                'current_reading_date',
                'current_reading',
                'amount',
                'wtax',
                'consumption',
                'rate_php_per_kwh',
                'total_amount',
            ],
            array_column($field['columns'], 'key'),
        );
    }
}
