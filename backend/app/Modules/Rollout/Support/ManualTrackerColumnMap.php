<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

/**
 * Maps legacy manual tracker column headers to internal import keys.
 */
final class ManualTrackerColumnMap
{
    /** @var array<string, string> */
    public const HEADER_ALIASES = [
        'tco site id' => 'tco_site_id',
        'mno anchor site id' => 'mno_anchor_site_id',
        'mno anchor' => 'mno',
        'globe project batch tagging' => 'search_ring_name',
        'project type' => 'project_type',
        'alliance tagging' => 'alliance_tag',
        'region' => 'region',
        'area' => 'area',
        'territory' => 'territory',
        'search ring name' => 'search_ring_name',
        'latitude (actual)' => 'latitude',
        'longitude (actual)' => 'longitude',
        'full address' => 'full_address',
        'mno 2' => 'coloc_2_mno',
        'coloc 2 site id' => 'coloc_2_tco_site_id',
        'coloc 2 site name' => 'coloc_2_site_name',
        'rfti date (coloc 2)' => 'coloc_2_rfti_date',
        'sl remarks (coloc 2)' => 'coloc_2_sl_remarks',
        'mno 3' => 'coloc_3_mno',
        'coloc 3 site id' => 'coloc_3_tco_site_id',
        'coloc 3 site name' => 'coloc_3_site_name',
        'rfti date (coloc 3)' => 'coloc_3_rfti_date',
        'sl remarks (3)' => 'coloc_3_sl_remarks',
        'moc secured' => 'permit_moc_secured',
        'brgy. clearance applied' => 'permit_brgy_applied',
        'brgy clearance secured' => 'permit_brgy_secured',
        'locational clearance applied' => 'permit_locational_applied',
        'locational clearance secured' => 'permit_locational_secured',
        'excavation permit applied' => 'permit_excavation_applied',
        'excavation permit secured' => 'permit_excavation_secured',
        'building permit applied' => 'permit_building_applied',
        'building permit secured' => 'permit_building_secured',
        'occupancy permit applied' => 'permit_occupancy_applied',
        'occupancy permit secured' => 'permit_occupancy_secured',
        'cfei applied' => 'permit_cfei_applied',
        'cfei secured' => 'permit_cfei_secured',
        'date endorsed by globe' => 'endorsement_date',
        'tssr submitted' => 'phase_tssr_creation_end',
        'tssr approved' => 'tssr_approved_date',
        'risk build declared date' => 'phase_permitting_end',
        'cw start date' => 'phase_construction_start',
        'cw completed date' => 'phase_construction_end',
        'energization tempo date' => 'energization_tempo_date',
        'energization (permanent)' => 'phase_construction_end',
        'rfti docs submitted' => 'phase_rfti_submission_end',
        'rfti docs signed (tempo)' => 'rfti_signed_tempo_date',
        'rft docs signed (permanent)' => 'actual_rfi_date',
        'sl submitted' => 'phase_site_license_start',
        'sl signed' => 'site_license_executed_date',
        // PMO transposed export / header variants
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'legacy site id' => 'mno_anchor_site_id',
        'site id' => 'mno_anchor_site_id',
        'rollout id' => 'rollout_ref',
        'site name' => 'site_name',
        'll nominal address' => 'full_address',
        'province' => 'territory',
        'city/municipality' => 'area',
        'barangay' => 'barangay',
        'sl remarks' => 'coloc_2_sl_remarks',
        'rfti date (coloc 2)' => 'coloc_2_rfti_date',
        'rfti date (coloc 3)' => 'coloc_3_rfti_date',
        'solution' => 'solution',
        'site classification' => 'project_type',
        'site category' => 'site_category',
        'mno' => 'mno',
        'operator' => 'mno',
        'anchor mno' => 'mno',
        'work package id' => 'endorsement_ref',
        'search ring id' => 'search_ring_id',
        'acquisition status' => 'acquisition_status',
        'acquisition remarks' => 'acquisition_remarks',
    ];

    /**
     * @return list<string>
     */
    public static function knownImportKeys(): array
    {
        return array_values(array_unique(array_values(self::HEADER_ALIASES)));
    }

    public static function resolveFieldKey(string $label): ?string
    {
        $normalized = self::normalizeHeader($label);
        if ($normalized === '') {
            return null;
        }

        return self::HEADER_ALIASES[$normalized] ?? null;
    }

    /**
     * @param  list<string|null>  $headers
     * @return array<int, string>
     */
    public static function mapHeaders(array $headers): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            $normalized = self::normalizeHeader((string) $header);
            if ($normalized === '') {
                continue;
            }

            $mapped[$index] = self::HEADER_ALIASES[$normalized] ?? $normalized;
        }

        return $mapped;
    }

    public static function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }
}
