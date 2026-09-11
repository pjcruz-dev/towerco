<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Support\ModuleListExportQuery;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ModuleListExportQueryTest extends TestCase
{
    public function test_coerces_single_string_ids_and_columns_to_arrays(): void
    {
        $request = Request::create('/export', 'GET', [
            'format' => 'csv',
            'ids' => '11111111-1111-1111-1111-111111111111',
            'columns' => 'status',
        ]);

        ModuleListExportQuery::coerceArrayParams($request);

        $this->assertSame(['11111111-1111-1111-1111-111111111111'], $request->input('ids'));
        $this->assertSame(['status'], $request->input('columns'));
        $this->assertSame('csv', $request->input('format'));
    }

    public function test_leaves_existing_arrays_untouched(): void
    {
        $request = Request::create('/export', 'GET', [
            'ids' => ['11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222'],
            'columns' => ['status', 'created_at'],
        ]);

        ModuleListExportQuery::coerceArrayParams($request);

        $this->assertSame([
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
        ], $request->input('ids'));
        $this->assertSame(['status', 'created_at'], $request->input('columns'));
    }

    public function test_coerces_empty_string_to_empty_array(): void
    {
        $request = Request::create('/export', 'GET', [
            'columns' => '',
        ]);

        ModuleListExportQuery::coerceArrayParams($request);

        $this->assertSame([], $request->input('columns'));
    }
}
