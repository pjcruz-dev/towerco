<?php

declare(strict_types=1);

namespace Tests\Unit\AdminOne;

use App\Modules\AdminOne\Support\RoleAccessMatrix;
use PHPUnit\Framework\TestCase;

class RoleAccessMatrixFilterTest extends TestCase
{
    public function test_field_level_visibility_matches_form_table_and_record(): void
    {
        $this->assertTrue(RoleAccessMatrix::visibleOn('full', 'form'));
        $this->assertTrue(RoleAccessMatrix::visibleOn('view', 'table'));
        $this->assertFalse(RoleAccessMatrix::visibleOn('table_record', 'form'));
        $this->assertTrue(RoleAccessMatrix::visibleOn('table_record', 'record'));
        $this->assertTrue(RoleAccessMatrix::visibleOn('record', 'record'));
        $this->assertFalse(RoleAccessMatrix::visibleOn('record', 'table'));
        $this->assertTrue(RoleAccessMatrix::visibleOn('form', 'form'));
        $this->assertFalse(RoleAccessMatrix::visibleOn('form', 'record'));
        $this->assertFalse(RoleAccessMatrix::visibleOn('hide', 'table'));
        $this->assertTrue(RoleAccessMatrix::editableOnForm('form'));
        $this->assertFalse(RoleAccessMatrix::editableOnForm('view'));
    }

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

    public function test_power_flags_follow_the_base_action_until_set(): void
    {
        $matrix = RoleAccessMatrix::normalize([
            'entities' => [
                'vendors' => ['view' => true, 'create' => true, 'edit' => false, 'delete' => true, 'export' => false],
            ],
        ]);

        $this->assertTrue(RoleAccessMatrix::entityAllows($matrix, 'vendors', 'import'));
        $this->assertFalse(RoleAccessMatrix::entityAllows($matrix, 'vendors', 'undo'));
        $this->assertTrue(RoleAccessMatrix::entityAllows($matrix, 'vendors', 'truncate'));

        $explicit = RoleAccessMatrix::normalize([
            'entities' => [
                'vendors' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => false, 'import' => false],
            ],
        ]);
        $this->assertFalse(RoleAccessMatrix::entityAllows($explicit, 'vendors', 'import'));
    }

    public function test_ai_preset_and_status_lists_normalize(): void
    {
        $matrix = RoleAccessMatrix::normalize([
            'ai' => [
                'preset' => 'ask',
                'instructions' => '  Keep answers short.  ',
            ],
            'statuses' => [
                'vendors' => ['Active', ''],
            ],
        ]);

        $this->assertSame('ask', $matrix['ai']['preset']);
        $this->assertSame('Keep answers short.', $matrix['ai']['instructions']);
        $this->assertSame(['Active'], $matrix['statuses']['vendors']);
        $this->assertSame(['ask_lookup' => true], array_filter(RoleAccessMatrix::capabilitiesForPreset('ask')));
    }

    public function test_unrestricted_role_drops_another_roles_ai_limit(): void
    {
        $limited = RoleAccessMatrix::normalize([
            'ai' => ['preset' => 'none'],
        ]);
        $open = RoleAccessMatrix::normalize([]);

        $merged = RoleAccessMatrix::merge([$limited, $open]);
        $this->assertArrayNotHasKey('ai', $merged);
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
