<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

use InvalidArgumentException;

final class RolloutPlaybookDefinitionRegistry
{
    /**
     * @return array<string, mixed>
     */
    public static function payloadForVersion(string $version): array
    {
        return match ($version) {
            RolloutPlaybookV1Definition::VERSION => RolloutPlaybookV1Definition::payload(),
            RolloutPlaybookV2Definition::VERSION => RolloutPlaybookV2Definition::payload(),
            default => throw new InvalidArgumentException("Unknown playbook version [{$version}]."),
        };
    }

    /**
     * @return list<string>
     */
    public static function supportedVersions(): array
    {
        return [
            RolloutPlaybookV1Definition::VERSION,
            RolloutPlaybookV2Definition::VERSION,
        ];
    }
}
