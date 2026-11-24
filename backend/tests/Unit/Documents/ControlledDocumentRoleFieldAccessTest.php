<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use PHPUnit\Framework\TestCase;

final class ControlledDocumentRoleFieldAccessTest extends TestCase
{
    public function test_hidden_register_field_is_cleared(): void
    {
        $access = new ControlledDocumentRoleFieldAccess;
        $row = $access->redactIn([
            'fields' => [
                ControlledDocumentRoleFieldAccess::ENTITY => [
                    'document_code' => 'hide',
                    'title' => 'record',
                ],
            ],
        ], [
            'id' => 'doc-1',
            'document_code' => 'ATC-001',
            'title' => 'Procedure',
            'status' => 'published',
        ], 'table');

        $this->assertSame('doc-1', $row['id']);
        $this->assertNull($row['document_code']);
        $this->assertNull($row['title']);
        $this->assertSame('published', $row['status']);
    }

    public function test_missing_entity_row_stays_allowed(): void
    {
        $access = new ControlledDocumentRoleFieldAccess;

        $this->assertTrue($access->allowsIn([
            'entities' => [
                'e_approval_submission' => ['view' => true, 'export' => false],
            ],
        ], 'export'));
    }

    public function test_register_row_denies_export(): void
    {
        $access = new ControlledDocumentRoleFieldAccess;

        $this->assertFalse($access->allowsIn([
            'entities' => [
                'controlled_document' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => false,
                    'export' => false,
                ],
            ],
        ], 'export'));
    }

    public function test_hidden_register_page_does_not_hide_overview(): void
    {
        $access = new ControlledDocumentRoleFieldAccess;
        $matrix = [
            'fields' => [
                ControlledDocumentRoleFieldAccess::ENTITY => [
                    'nav:/documents/controlled/register' => 'hide',
                ],
            ],
        ];

        $this->assertTrue($access->navTargetHiddenIn($matrix, '/documents/controlled/register'));
        $this->assertTrue($access->navTargetHiddenIn($matrix, '/documents/controlled/published/doc-1'));
        $this->assertTrue($access->navTargetHiddenIn($matrix, '/documents/controlled/new'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/documents/controlled'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/documents/controlled/reports'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/documents/controlled/approvals'));
    }

    public function test_hidden_approvals_page_hides_the_request_and_leaves_the_register(): void
    {
        $access = new ControlledDocumentRoleFieldAccess;
        $matrix = [
            'fields' => [
                ControlledDocumentRoleFieldAccess::ENTITY => [
                    'nav:/documents/controlled/approvals' => 'hide',
                ],
            ],
        ];

        $this->assertTrue($access->navTargetHiddenIn($matrix, '/documents/controlled/approvals'));
        $this->assertTrue($access->navTargetHiddenIn($matrix, '/documents/controlled/requests/sub-1?tab=decide'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/documents/controlled/register'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/documents/controlled/new'));
    }
}
