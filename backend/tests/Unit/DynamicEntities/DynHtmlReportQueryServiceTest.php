<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynHtmlReportQueryService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DynHtmlReportQueryServiceTest extends TestCase
{
    public function test_rejects_a_write(): void
    {
        $this->expectException(ValidationException::class);

        app(DynHtmlReportQueryService::class)->select('DELETE FROM users WHERE id = 1');
    }

    public function test_rejects_more_than_one_statement(): void
    {
        $this->expectException(ValidationException::class);

        app(DynHtmlReportQueryService::class)->select('SELECT 1; SELECT 2');
    }
}
