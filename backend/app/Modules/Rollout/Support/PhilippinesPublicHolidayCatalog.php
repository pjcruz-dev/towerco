<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

/**
 * Fixed national holidays for SLA working-day math (extend per year).
 *
 * @return list<array{date: string, name: string}>
 */
final class PhilippinesPublicHolidayCatalog
{
    /**
     * @return list<array{date: string, name: string}>
     */
    public static function forYear(int $year): array
    {
        return match ($year) {
            2026 => self::year2026(),
            2025 => self::year2025(),
            default => self::year2026(),
        };
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private static function year2026(): array
    {
        return [
            ['date' => '2026-01-01', 'name' => "New Year's Day"],
            ['date' => '2026-02-17', 'name' => 'Chinese New Year'],
            ['date' => '2026-02-25', 'name' => 'EDSA People Power Revolution'],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday'],
            ['date' => '2026-04-03', 'name' => 'Good Friday'],
            ['date' => '2026-04-04', 'name' => 'Black Saturday'],
            ['date' => '2026-04-09', 'name' => 'Araw ng Kagitingan'],
            ['date' => '2026-05-01', 'name' => 'Labor Day'],
            ['date' => '2026-06-12', 'name' => 'Independence Day'],
            ['date' => '2026-08-21', 'name' => 'Ninoy Aquino Day'],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day'],
            ['date' => '2026-11-01', 'name' => "All Saints' Day"],
            ['date' => '2026-11-30', 'name' => 'Bonifacio Day'],
            ['date' => '2026-12-08', 'name' => 'Feast of the Immaculate Conception'],
            ['date' => '2026-12-25', 'name' => 'Christmas Day'],
            ['date' => '2026-12-30', 'name' => 'Rizal Day'],
        ];
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private static function year2025(): array
    {
        return [
            ['date' => '2025-01-01', 'name' => "New Year's Day"],
            ['date' => '2025-04-17', 'name' => 'Maundy Thursday'],
            ['date' => '2025-04-18', 'name' => 'Good Friday'],
            ['date' => '2025-04-19', 'name' => 'Black Saturday'],
            ['date' => '2025-12-25', 'name' => 'Christmas Day'],
        ];
    }
}
