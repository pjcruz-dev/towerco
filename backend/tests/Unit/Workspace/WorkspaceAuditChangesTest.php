<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use PHPUnit\Framework\TestCase;

final class WorkspaceAuditChangesTest extends TestCase
{
    public function test_of_keeps_changed_scalar_fields(): void
    {
        $changes = WorkspaceAuditChanges::of([
            'status' => ['from' => 'pending', 'to' => 'approved'],
            'same' => ['from' => 'a', 'to' => 'a'],
        ]);

        $this->assertSame([
            'status' => ['from' => 'pending', 'to' => 'approved'],
        ], $changes);
    }

    public function test_of_strips_secret_fields(): void
    {
        $changes = WorkspaceAuditChanges::of([
            'password' => ['from' => 'old', 'to' => 'new'],
            'api_token' => ['from' => 'a', 'to' => 'b'],
            'status' => ['from' => 'draft', 'to' => 'published'],
        ]);

        $this->assertArrayHasKey('status', $changes);
        $this->assertArrayNotHasKey('password', $changes);
        $this->assertArrayNotHasKey('api_token', $changes);
    }

    public function test_diff_compares_allowlisted_fields(): void
    {
        $changes = WorkspaceAuditChanges::diff(
            ['name' => 'A', 'status' => 'draft', 'ignored' => 1],
            ['name' => 'B', 'status' => 'draft', 'ignored' => 2],
            ['name', 'status'],
        );

        $this->assertSame([
            'name' => ['from' => 'A', 'to' => 'B'],
        ], $changes);
    }

    public function test_extract_from_metadata(): void
    {
        $this->assertNull(WorkspaceAuditChanges::extractFromMetadata(null));
        $this->assertNull(WorkspaceAuditChanges::extractFromMetadata(['other' => 1]));

        $extracted = WorkspaceAuditChanges::extractFromMetadata([
            'changes' => [
                'status' => ['from' => 'pending', 'to' => 'cancelled'],
            ],
        ]);

        $this->assertSame([
            'status' => ['from' => 'pending', 'to' => 'cancelled'],
        ], $extracted);
    }

    public function test_truncates_long_strings(): void
    {
        $long = str_repeat('x', 300);
        $changes = WorkspaceAuditChanges::of([
            'summary' => ['from' => null, 'to' => $long],
        ]);

        $this->assertSame(240, mb_strlen((string) $changes['summary']['to']));
        $this->assertStringEndsWith('…', (string) $changes['summary']['to']);
    }
}
