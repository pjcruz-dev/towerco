<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\RolloutOpsGeography;

final class TcoSiteIdGenerator
{
    private const MNO_CODES = [
        'globe' => 'GLO',
        'smart' => 'SMT',
        'dito' => 'DIT',
    ];

    /** Preferred telecom territory codes (Phase C). */
    private const TERRITORY_CODES = [
        'luz' => 'LZ',
        'vis' => 'VI',
        'min' => 'MI',
        'ncr' => 'NC',
        'slz' => 'SL',
        'nlz' => 'NL',
    ];

    /** Legacy free-text region values still seen on older rollouts. */
    private const LEGACY_CODES = [
        'ncr-t1' => 'N1',
        'ncr-t2' => 'N2',
        'ncr-t3' => 'N3',
        'ncr-t4' => 'N4',
        'nlz' => 'NL',
        'slz' => 'SL',
        'vis' => 'VI',
        'visayas' => 'VI',
        'min' => 'MI',
        'mindanao' => 'MI',
        'luzon' => 'LZ',
        'ncr' => 'NC',
    ];

    /**
     * @param  string  $opsCode  Territory preferred; region/legacy accepted as fallback
     */
    public function generate(string $opsCode, string $mno, string $tenantSequencePrefix, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('y');
        $opsPrefix = $this->resolveOpsPrefix($opsCode);
        $mnoCode = self::MNO_CODES[strtolower($mno)] ?? strtoupper(substr($mno, 0, 3));
        $prefix = strtoupper(substr($tenantSequencePrefix, 0, 1));

        $sequence = $this->nextSequence($opsPrefix, $mnoCode, $prefix, $year);

        return sprintf('%s-%s%s%02d-%s%03d', $opsPrefix, $mnoCode, $prefix, $year, $prefix, $sequence);
    }

    public function generateForProgram(RolloutProgram $program, string $tenantSequencePrefix, ?int $year = null): string
    {
        $opsCode = RolloutOpsGeography::forProgram($program) ?? 'NCR';

        return $this->generate($opsCode, (string) $program->mno, $tenantSequencePrefix, $year);
    }

    private function resolveOpsPrefix(string $opsCode): string
    {
        $key = strtolower(trim($opsCode));

        if ($key === '') {
            return 'RG';
        }

        if (isset(self::TERRITORY_CODES[$key])) {
            return self::TERRITORY_CODES[$key];
        }

        if (isset(self::LEGACY_CODES[$key])) {
            return self::LEGACY_CODES[$key];
        }

        if (preg_match('/^\d{1,2}$/', $key) === 1) {
            return 'R'.str_pad($key, 2, '0', STR_PAD_LEFT);
        }

        $letters = preg_replace('/[^a-z]/', '', $key) ?? '';
        if ($letters !== '') {
            return strtoupper(substr($letters, 0, 2));
        }

        return 'RG';
    }

    private function nextSequence(string $opsPrefix, string $mnoCode, string $prefix, int $year): int
    {
        $pattern = "{$opsPrefix}-{$mnoCode}{$prefix}{$year}-{$prefix}%";

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
