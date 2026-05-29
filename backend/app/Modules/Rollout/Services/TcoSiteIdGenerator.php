<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Support\Facades\DB;

final class TcoSiteIdGenerator
{
    private const MNO_CODES = [
        'globe' => 'GLO',
        'smart' => 'SMT',
        'dito' => 'DIT',
    ];

    private const REGION_CODES = [
        'ncr-t1' => 'N1',
        'ncr-t2' => 'N2',
        'ncr-t3' => 'N3',
        'ncr-t4' => 'N4',
        'nlz' => 'NL',
        'slz' => 'SL',
        'vis' => 'VI',
        'min' => 'MI',
        'ncr' => 'NC',
    ];

    public function generate(string $region, string $mno, string $tenantSequencePrefix, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('y');
        $regionCode = self::REGION_CODES[strtolower($region)] ?? strtoupper(substr(preg_replace('/[^a-z]/', '', strtolower($region)) ?? 'RG', 0, 2));
        $mnoCode = self::MNO_CODES[strtolower($mno)] ?? strtoupper(substr($mno, 0, 3));
        $prefix = strtoupper(substr($tenantSequencePrefix, 0, 1));

        $sequence = $this->nextSequence($regionCode, $mnoCode, $prefix, $year);

        return sprintf('%s-%s%s%02d-%s%03d', $regionCode, $mnoCode, $prefix, $year, $prefix, $sequence);
    }

    private function nextSequence(string $regionCode, string $mnoCode, string $prefix, int $year): int
    {
        $pattern = "{$regionCode}-{$mnoCode}{$prefix}{$year}-{$prefix}%";

        $latest = RolloutProgram::query()
            ->where('tco_site_id', 'like', $pattern)
            ->orderByDesc('tco_site_id')
            ->value('tco_site_id');

        if (! is_string($latest)) {
            return 1;
        }

        if (preg_match('/-([A-Z])(\d{3})$/', $latest, $matches) !== 1) {
            return 1;
        }

        return ((int) $matches[2]) + 1;
    }
}
