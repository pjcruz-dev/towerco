<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use Carbon\Carbon;

final class ManualTrackerValueNormalizer
{
    private const DATE_FIELD_KEYS = [
        'endorsement_date',
        'tssr_approved_date',
        'site_license_executed_date',
        'site_license_remarks',
        'energization_tempo_date',
        'rfti_signed_tempo_date',
        'actual_rfi_date',
        'coloc_2_rfti_date',
        'coloc_3_rfti_date',
        'permit_moc_secured',
        'permit_brgy_applied',
        'permit_brgy_secured',
        'permit_locational_applied',
        'permit_locational_secured',
        'permit_excavation_applied',
        'permit_excavation_secured',
        'permit_building_applied',
        'permit_building_secured',
        'permit_occupancy_applied',
        'permit_occupancy_secured',
        'permit_cfei_applied',
        'permit_cfei_secured',
        'phase_tssr_creation_end',
        'phase_permitting_end',
        'phase_construction_start',
        'phase_construction_end',
        'phase_rfti_submission_end',
        'phase_site_license_start',
    ];

    public static function normalize(string $fieldKey, string $value): string
    {
        $value = trim($value);

        return match ($fieldKey) {
            'mno' => self::normalizeMno($value),
            'project_type' => self::normalizeProjectType($value),
            'latitude', 'longitude' => self::normalizeCoordinate($value),
            default => self::isDateField($fieldKey) ? self::normalizeDate($value) : $value,
        };
    }

    private static function normalizeMno(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'globe', 'glo' => 'globe',
            'smart' => 'smart',
            'dito' => 'dito',
            default => $normalized,
        };
    }

    private static function normalizeProjectType(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'bts', 'macro', 'micro', 'greenfield', 'small cell' => 'bts',
            'rtb', 'rooftop', 'rtd' => 'rtb',
            'colocation', 'colo', 'co-location', 'co loc' => 'colocation',
            default => $normalized,
        };
    }

    private static function normalizeCoordinate(string $value): string
    {
        $value = trim(str_replace(["\u{00B0}", '°', 'º', ','], '', $value));
        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        return $value;
    }

    private static function normalizeDate(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $lower = mb_strtolower(trim($value));
        if ($lower === '' || str_contains($lower, 'n/a') || str_starts_with($lower, '#ref')) {
            return '';
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial <= 2958465) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function isDateField(string $fieldKey): bool
    {
        return in_array($fieldKey, self::DATE_FIELD_KEYS, true)
            || str_starts_with($fieldKey, 'permit_')
            || str_starts_with($fieldKey, 'phase_')
            || str_ends_with($fieldKey, '_date');
    }
}
