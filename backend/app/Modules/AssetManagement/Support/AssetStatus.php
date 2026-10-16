<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Support;

use Illuminate\Validation\ValidationException;

enum AssetStatus: string
{
    case New = 'NEW';
    case Available = 'AVAILABLE';
    case Assigned = 'ASSIGNED';
    case UnderRepair = 'UNDER_REPAIR';
    case ReplacementPending = 'REPLACEMENT_PENDING';
    case Lost = 'LOST';
    case Stolen = 'STOLEN';
    case Retired = 'RETIRED';
    case EWaste = 'E_WASTE';
    case Disposed = 'DISPOSED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public static function parse(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw ValidationException::withMessages([
                'status' => [__('Invalid asset status.')],
            ]);
        }

        return $status;
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        $allowed = match ($this) {
            self::New => [self::Available],
            self::Available => [self::Assigned, self::UnderRepair, self::Retired, self::EWaste, self::Lost],
            self::Assigned => [self::Available, self::UnderRepair, self::ReplacementPending, self::Lost, self::Stolen, self::Retired],
            self::UnderRepair => [self::Available, self::Assigned, self::ReplacementPending, self::Retired, self::EWaste],
            self::ReplacementPending => [self::Assigned, self::Available, self::Retired, self::UnderRepair],
            self::Lost, self::Stolen => [self::Assigned, self::Retired, self::Disposed],
            self::Retired => [self::EWaste, self::Disposed],
            self::EWaste => [self::Disposed],
            self::Disposed => [],
        };

        return in_array($next, $allowed, true);
    }

    public function assertCanTransitionTo(self $next, string $assetTag): void
    {
        if ($this->canTransitionTo($next)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [__(
                'Cannot change :tag from :from to :to. Complete the current step before changing the asset status.',
                ['tag' => $assetTag, 'from' => $this->value, 'to' => $next->value],
            )],
        ]);
    }
}
