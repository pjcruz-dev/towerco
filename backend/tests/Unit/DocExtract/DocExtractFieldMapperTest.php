<?php

declare(strict_types=1);

namespace Tests\Unit\DocExtract;

use App\Modules\DocExtract\Services\DocExtractFieldMapper;
use PHPUnit\Framework\TestCase;

final class DocExtractFieldMapperTest extends TestCase
{
    public function test_maps_label_colon_values_from_ocr_text(): void
    {
        $mapper = new DocExtractFieldMapper;
        $values = $mapper->map(
            [
                ['key' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text', 'hint' => 'Vendor'],
                ['key' => 'total', 'label' => 'Total Amount', 'type' => 'currency', 'hint' => null],
            ],
            "Supplier Name: Acme Corp\nInvoice Date: 2024-01-02\nTotal Amount: 1,250.00\n",
        );

        $this->assertSame('Acme Corp', $values['supplier_name']);
        $this->assertSame('1,250.00', $values['total']);
    }

    public function test_returns_null_when_label_missing(): void
    {
        $mapper = new DocExtractFieldMapper;
        $values = $mapper->map(
            [
                ['key' => 'po_number', 'label' => 'PO Number', 'type' => 'text', 'hint' => null],
            ],
            "Unrelated OCR noise without the field",
        );

        $this->assertNull($values['po_number']);
    }
}
