<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

final class RolloutFieldCreateResult
{
    /**
     * @template T of object
     *
     * @param  T  $record
     * @return array{record: T, created: bool}
     */
    public static function of(object $record, bool $created): array
    {
        return ['record' => $record, 'created' => $created];
    }
}
