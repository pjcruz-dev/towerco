<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Support\RecordActionHistoryCopy;
use PHPUnit\Framework\TestCase;

class RecordActionHistoryCopyTest extends TestCase
{
    public function test_titles_match_the_history_panel(): void
    {
        $this->assertSame(
            'Created a Procurement Requests record',
            RecordActionHistoryCopy::title('created', 'Procurement Requests', 1, null),
        );
        $this->assertSame(
            'Edited Status on one Purchase Transactions record',
            RecordActionHistoryCopy::title('updated', 'Purchase Transactions', 1, 'Status'),
        );
        $this->assertSame(
            'Deleted 2 Purchase Transactions record(s)',
            RecordActionHistoryCopy::title('deleted', 'Purchase Transactions', 2, null),
        );
        $this->assertSame('1 updated · SAQ Trackers', RecordActionHistoryCopy::detail('updated', 1, 'SAQ Trackers'));
    }

    public function test_revert_rules(): void
    {
        $this->assertFalse(RecordActionHistoryCopy::reversible('assets', 'created', null, ['id' => 'a'], 1));
        $this->assertTrue(RecordActionHistoryCopy::reversible('dynamic_entities', 'created', null, ['id' => 'a'], 1));
        $this->assertTrue(RecordActionHistoryCopy::reversible('assets', 'updated', ['id' => 'a'], ['id' => 'a'], 1));
        $this->assertFalse(RecordActionHistoryCopy::reversible(
            'assets',
            'imported',
            ['created_ids' => ['a'], 'updates' => []],
            null,
            1,
        ));
        $this->assertTrue(RecordActionHistoryCopy::reversible(
            'dynamic_entities',
            'imported',
            ['created_ids' => ['a'], 'updates' => []],
            null,
            1,
        ));
        $this->assertFalse(RecordActionHistoryCopy::reversible(
            'dynamic_entities',
            'deleted',
            ['records' => [['id' => 'a']]],
            null,
            2,
        ));
        $this->assertSame(
            'Creating an asset cannot be undone from history.',
            RecordActionHistoryCopy::blockedReason('assets', 'created', false),
        );
    }

    public function test_single_value_change_names_the_field(): void
    {
        $this->assertSame('Serial number', RecordActionHistoryCopy::changedFieldLabel(
            ['values' => ['serial_number' => 'A', 'status' => 'NEW']],
            ['values' => ['serial_number' => 'B', 'status' => 'NEW']],
        ));
        $this->assertNull(RecordActionHistoryCopy::changedFieldLabel(
            ['values' => ['serial_number' => 'A', 'status' => 'NEW']],
            ['values' => ['serial_number' => 'B', 'status' => 'USED']],
        ));
    }
}
