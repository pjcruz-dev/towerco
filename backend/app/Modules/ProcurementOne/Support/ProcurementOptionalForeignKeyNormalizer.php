<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProcurementOptionalForeignKeyNormalizer
{
    public function resolve(
        mixed $value,
        string $valuesKey,
        string $label,
        ?string $table = null,
        string $connection = 'tenant',
    ): ?string {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        if (! Str::isUuid($string)) {
            throw ValidationException::withMessages([
                "values.{$valuesKey}" => [
                    __(':label must be a valid TowerOS record ID (UUID), or left blank.', ['label' => $label]),
                ],
            ]);
        }

        if ($table !== null && ! DB::connection($connection)->table($table)->where('id', $string)->exists()) {
            throw ValidationException::withMessages([
                "values.{$valuesKey}" => [
                    __(':label was not found. Select a valid record or leave this field blank.', ['label' => $label]),
                ],
            ]);
        }

        return $string;
    }
}
