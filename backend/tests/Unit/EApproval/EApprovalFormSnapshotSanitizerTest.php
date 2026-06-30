<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalFormSnapshotSanitizer;
use Tests\TestCase;

final class EApprovalFormSnapshotSanitizerTest extends TestCase
{
    public function test_strip_nested_history_removes_revision_blobs_from_snapshot(): void
    {
        $payload = [
            'name' => 'Reimbursement',
            'published_snapshot' => '{"huge":true}',
            'revisions' => [['revision' => 1]],
            'metadata_json' => [
                'form_family' => 'reimbursement',
                'revisions' => [['revision' => 1, 'snapshot' => ['name' => 'nested']]],
            ],
        ];

        $clean = EApprovalFormSnapshotSanitizer::stripNestedHistory($payload);

        $this->assertArrayNotHasKey('published_snapshot', $clean);
        $this->assertArrayNotHasKey('revisions', $clean);
        $this->assertSame('reimbursement', $clean['metadata_json']['form_family'] ?? null);
        $this->assertArrayNotHasKey('revisions', $clean['metadata_json']);
    }
}
