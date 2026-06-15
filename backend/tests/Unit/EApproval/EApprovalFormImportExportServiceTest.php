<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Services\EApprovalFormValidator;
use PHPUnit\Framework\TestCase;

final class EApprovalFormImportExportServiceTest extends TestCase
{
    public function test_legacy_document_approval_export_shape_passes_validation(): void
    {
        $inner = json_decode(<<<'JSON'
{
  "name": "Document Approval",
  "status": "published",
  "owner_code": "DA",
  "doc_type_code": "F",
  "fields": [
    {"type": "text", "name": "title", "label": "Title"},
    {"type": "approver", "name": "appprover_1", "label": "Appprover 1"}
  ],
  "steps": [
    {"type": "field", "approverId": "appprover_1", "condition": {}}
  ]
}
JSON,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $warnings = (new EApprovalFormValidator())->validate($inner, true);

        $this->assertIsArray($warnings);
        $this->assertSame('Document Approval', $inner['name']);
    }
}
