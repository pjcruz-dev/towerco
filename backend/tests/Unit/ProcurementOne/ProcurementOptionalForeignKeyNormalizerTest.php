<?php

declare(strict_types=1);

namespace Tests\Unit\ProcurementOne;

use App\Modules\ProcurementOne\Support\ProcurementOptionalForeignKeyNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProcurementOptionalForeignKeyNormalizerTest extends TestCase
{
    public function test_empty_value_returns_null(): void
    {
        $normalizer = new ProcurementOptionalForeignKeyNormalizer;

        $this->assertNull($normalizer->resolve(null, 'site_id', 'Site', 'sites'));
        $this->assertNull($normalizer->resolve('', 'site_id', 'Site', 'sites'));
        $this->assertNull($normalizer->resolve('   ', 'site_id', 'Site', 'sites'));
    }

    public function test_non_uuid_value_throws_validation_exception(): void
    {
        $normalizer = new ProcurementOptionalForeignKeyNormalizer;

        try {
            $normalizer->resolve('asdasd', 'site_id', 'Site', 'sites');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.site_id', $exception->errors());
        }
    }

    public function test_unknown_uuid_throws_validation_exception(): void
    {
        $normalizer = new ProcurementOptionalForeignKeyNormalizer;

        DB::shouldReceive('connection')
            ->once()
            ->with('tenant')
            ->andReturnSelf();

        DB::shouldReceive('table')
            ->once()
            ->with('sites')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->once()
            ->with('id', $uuid = (string) Str::uuid())
            ->andReturnSelf();

        DB::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        try {
            $normalizer->resolve($uuid, 'site_id', 'Site', 'sites');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.site_id', $exception->errors());
        }
    }
}
