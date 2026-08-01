<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

/**
 * Default PSA regions + telecom territory clusters for Project One geography lookups.
 */
final class PhilippinesRolloutGeographyCatalog
{
    /**
     * @return list<array{kind: string, code: string, label: string, sort_order: int}>
     */
    public static function defaults(): array
    {
        return array_merge(self::regions(), self::territories());
    }

    /**
     * @return list<array{kind: string, code: string, label: string, sort_order: int}>
     */
    public static function regions(): array
    {
        $rows = [
            ['01', 'Region 01 — Ilocos Region'],
            ['02', 'Region 02 — Cagayan Valley'],
            ['03', 'Region 03 — Central Luzon'],
            ['04', 'Region 04-A — CALABARZON'],
            ['17', 'Region 04-B — MIMAROPA'],
            ['05', 'Region 05 — Bicol Region'],
            ['06', 'Region 06 — Western Visayas'],
            ['07', 'Region 07 — Central Visayas'],
            ['08', 'Region 08 — Eastern Visayas'],
            ['09', 'Region 09 — Zamboanga Peninsula'],
            ['10', 'Region 10 — Northern Mindanao'],
            ['11', 'Region 11 — Davao Region'],
            ['12', 'Region 12 — SOCCSKSARGEN'],
            ['13', 'Region 13 — National Capital Region / NCR'],
            ['14', 'Region 14 — Cordillera Administrative Region / CAR'],
            ['16', 'Region 16 — Caraga'],
        ];

        $out = [];
        foreach ($rows as $index => [$code, $label]) {
            $out[] = [
                'kind' => 'region',
                'code' => $code,
                'label' => $label,
                'sort_order' => $index + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{kind: string, code: string, label: string, sort_order: int}>
     */
    public static function territories(): array
    {
        $rows = [
            ['LUZ', 'Luzon Territory'],
            ['VIS', 'Visayas Territory'],
            ['MIN', 'Mindanao Territory'],
            ['NCR', 'National Capital Region'],
            ['SLZ', 'South Luzon'],
            ['NLZ', 'North Luzon'],
        ];

        $out = [];
        foreach ($rows as $index => [$code, $label]) {
            $out[] = [
                'kind' => 'territory',
                'code' => $code,
                'label' => $label,
                'sort_order' => $index + 1,
            ];
        }

        return $out;
    }
}
