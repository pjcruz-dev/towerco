<?php

declare(strict_types=1);

namespace Tests\Unit\ProcurementOne;

use App\Modules\ProcurementOne\Support\ProcurementGridValueParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProcurementGridValueParserTest extends TestCase
{
    private ProcurementGridValueParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProcurementGridValueParser;
    }

    #[DataProvider('indexedGridProvider')]
    public function test_it_maps_indexed_grid_rows_to_column_labels(mixed $raw, array $labels, array $expected): void
    {
        $this->assertSame($expected, $this->parser->labeledRows($raw, $labels));
    }

    /**
     * @return array<string, array{0: mixed, 1: list<string>, 2: list<array<string, string>>}>
     */
    public static function indexedGridProvider(): array
    {
        return [
            'json rows payload' => [
                '{"rows":[{"0":"Cable tray","1":"4","2":"1200"},{"0":"Labor","1":"1","2":"25000"}]}',
                ['Description', 'Qty', 'Unit price'],
                [
                    ['Description' => 'Cable tray', 'Qty' => '4', 'Unit price' => '1200'],
                    ['Description' => 'Labor', 'Qty' => '1', 'Unit price' => '25000'],
                ],
            ],
            'labeled list payload' => [
                [
                    ['Description' => 'Battery', 'Qty' => '2', 'Unit price' => '150000'],
                ],
                ['Description', 'Qty', 'Unit price'],
                [
                    ['Description' => 'Battery', 'Qty' => '2', 'Unit price' => '150000'],
                ],
            ],
        ];
    }
}
