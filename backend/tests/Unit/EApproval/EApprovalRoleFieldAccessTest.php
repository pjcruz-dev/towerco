<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalRoleFieldAccess;
use PHPUnit\Framework\TestCase;

final class EApprovalRoleFieldAccessTest extends TestCase
{
    public function test_hidden_list_field_is_cleared_and_unrelated_fields_stay(): void
    {
        $access = new EApprovalRoleFieldAccess;
        $row = $access->redactListRowIn([
            'fields' => [
                EApprovalRoleFieldAccess::ENTITY => [
                    'document_no' => 'hide',
                    'requestor' => 'record',
                ],
            ],
        ], [
            'id' => 'sub-1',
            'document_no' => 'EA-1',
            'form_name' => 'Cash advance',
            'status' => 'pending',
            'requestor' => ['name' => 'Ada'],
            'requestor_name' => 'Ada',
            'current_step' => 2,
        ], 'table');

        $this->assertSame('sub-1', $row['id']);
        $this->assertNull($row['document_no']);
        $this->assertSame('Cash advance', $row['form_name']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['requestor']);
        $this->assertNull($row['requestor_name']);
        $this->assertSame(2, $row['current_step']);
    }

    public function test_record_surface_keeps_record_only_fields(): void
    {
        $access = new EApprovalRoleFieldAccess;
        $matrix = [
            'fields' => [
                EApprovalRoleFieldAccess::ENTITY => [
                    'payee' => 'record',
                    'amount' => 'form',
                ],
            ],
        ];

        $this->assertTrue($access->visibleIn($matrix, 'payee', 'record'));
        $this->assertFalse($access->visibleIn($matrix, 'payee', 'table'));
        $this->assertTrue($access->visibleIn($matrix, 'amount', 'form'));
        $this->assertFalse($access->visibleIn($matrix, 'amount', 'record'));
        $this->assertTrue($access->visibleIn($matrix, 'notes', 'table'));
    }

    public function test_missing_entity_row_stays_allowed(): void
    {
        $access = new EApprovalRoleFieldAccess;
        $matrix = [
            'entities' => [
                'it_asset' => ['view' => true, 'create' => false],
            ],
        ];

        $this->assertTrue($access->allowsIn($matrix, 'view'));
        $this->assertTrue($access->allowsIn($matrix, 'create'));
    }

    public function test_e_forms_entity_row_is_enforced(): void
    {
        $access = new EApprovalRoleFieldAccess;
        $matrix = [
            'entities' => [
                EApprovalRoleFieldAccess::ENTITY => [
                    'view' => true,
                    'create' => false,
                    'edit' => true,
                    'delete' => false,
                    'export' => false,
                ],
            ],
        ];

        $this->assertTrue($access->allowsIn($matrix, 'view'));
        $this->assertFalse($access->allowsIn($matrix, 'create'));
        $this->assertFalse($access->allowsIn($matrix, 'export'));
    }

    public function test_hidden_submissions_page_blocks_the_list_and_a_record_but_not_a_new_request(): void
    {
        $access = new EApprovalRoleFieldAccess;
        $matrix = [
            'fields' => [
                EApprovalRoleFieldAccess::ENTITY => [
                    'nav:/e-approval/submissions' => 'hide',
                ],
            ],
        ];

        $this->assertTrue($access->navTargetHiddenIn($matrix, '/e-approval/submissions'));
        $this->assertTrue($access->navTargetHiddenIn($matrix, '/e-approval/submissions/abc?tab=decide'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/e-approval/submissions/new'));
        $this->assertFalse($access->navTargetHiddenIn($matrix, '/e-approval'));
    }
}
