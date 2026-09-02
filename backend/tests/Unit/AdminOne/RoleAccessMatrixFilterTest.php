<?php

declare(strict_types=1);

namespace Tests\Unit\AdminOne;

use App\Modules\AdminOne\Support\RoleAccessMatrix;
use PHPUnit\Framework\TestCase;

class RoleAccessMatrixFilterTest extends TestCase
{
    public function test_normalize_filters_keeps_valid_rules(): void
    {
        $matrix = RoleAccessMatrix::normalize([
            'filters' => [
                'audit_logs' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'Open'],
                        ['field' => '', 'operator' => 'equals', 'value' => 'x'],
                        ['field' => 'title', 'operator' => 'bogus', 'value' => 'x'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('and', $matrix['filters']['audit_logs']['logic']);
        $this->assertCount(1, $matrix['filters']['audit_logs']['rules']);
        $this->assertSame('status', $matrix['filters']['audit_logs']['rules'][0]['field']);
    }

    public function test_merge_drops_filters_when_another_role_is_unrestricted(): void
    {
        $restricted = RoleAccessMatrix::normalize([
            'entities' => [
                'vendors' => ['view' => true, 'view_own' => false, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ],
            'filters' => [
                'vendors' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'Active'],
                    ],
                ],
            ],
        ]);
        $open = RoleAccessMatrix::normalize([
            'entities' => [
                'vendors' => ['view' => true, 'view_own' => false, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ],
        ]);

        $merged = RoleAccessMatrix::merge([$restricted, $open]);
        $this->assertArrayNotHasKey('filters', $merged);
        $this->assertTrue($merged['entities']['vendors']['view']);
    }

    public function test_view_own_only(): void
    {
        $matrix = RoleAccessMatrix::normalize([
            'entities' => [
                'vendors' => ['view' => false, 'view_own' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ],
        ]);
        $this->assertTrue(RoleAccessMatrix::viewOwnOnly($matrix, 'vendors'));
        $this->assertFalse(RoleAccessMatrix::viewOwnOnly($matrix, 'missing'));
    }
}
