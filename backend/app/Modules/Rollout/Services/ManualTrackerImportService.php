<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\ManualTrackerSheetParser;
use App\Modules\Rollout\Support\RolloutPermitCatalog;
use App\Modules\Rollout\Support\SimpleXlsxSheetReader;

final class ManualTrackerImportService
{
    /** @var array<string, string> */
    private const PERMIT_FIELD_MAP = [
        'permit_moc_secured' => 'moc:secured',
        'permit_brgy_applied' => 'brgy_clearance:applied',
        'permit_brgy_secured' => 'brgy_clearance:secured',
        'permit_locational_applied' => 'locational_clearance:applied',
        'permit_locational_secured' => 'locational_clearance:secured',
        'permit_excavation_applied' => 'excavation_permit:applied',
        'permit_excavation_secured' => 'excavation_permit:secured',
        'permit_building_applied' => 'building_permit:applied',
        'permit_building_secured' => 'building_permit:secured',
        'permit_occupancy_applied' => 'occupancy_permit:applied',
        'permit_occupancy_secured' => 'occupancy_permit:secured',
        'permit_cfei_applied' => 'cfei:applied',
        'permit_cfei_secured' => 'cfei:secured',
    ];

    /** @var array<string, string> */
    private const PHASE_END_FIELDS = [
        'phase_tssr_creation_end' => 'tssr_creation',
        'phase_permitting_end' => 'permitting',
        'phase_construction_end' => 'construction',
        'phase_rfti_submission_end' => 'permitting',
    ];

    /** @var array<string, string> */
    private const PHASE_START_FIELDS = [
        'phase_construction_start' => 'construction',
        'phase_site_license_start' => 'permitting',
    ];

    public function __construct(
        private readonly RolloutProgramService $programs,
        private readonly RolloutBulkPhaseDatesService $phaseDates,
        private readonly RolloutPermitService $permits,
        private readonly SimpleXlsxSheetReader $xlsxReader,
        private readonly ManualTrackerSheetParser $sheetParser,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFile(string $path, bool $dryRun = false): array
    {
        $parsed = $this->parseWorkbook($path);
        $payloads = $parsed['payloads'];

        if ($payloads === []) {
            $hints = $parsed['hints'] ?? [];

            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => array_merge([
                    'No importable sites found.',
                    "Sheet used: {$parsed['sheet']} ({$parsed['layout']} layout).",
                ], $hints, [
                    'Row tracker: headers in row 1 (TCO SITE ID, MNO Anchor, …), one site per row below.',
                    'Transposed tracker: field names in column A or B, site values in columns beside them.',
                ]),
                'sheet' => $parsed['sheet'],
                'layout' => $parsed['layout'],
                'site_count' => 0,
                'hints' => $hints,
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($payloads as $lineNumber => $payload) {
            if (! $this->sheetParser->isResolvablePayload($payload)) {
                $errors[] = 'Site '.($lineNumber + 1).': Unable to resolve rollout (provide TCO SITE ID or MNO Anchor + Project Type).';
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            try {
                $this->importPayload($payload);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'Site '.($lineNumber + 1).': '.$e->getMessage();
            }
        }

        return array_merge(compact('imported', 'skipped', 'errors'), [
            'sheet' => $parsed['sheet'],
            'layout' => $parsed['layout'],
            'site_count' => count($payloads),
            'hints' => $parsed['hints'] ?? [],
        ]);
    }

    /**
     * @return array{sheet: string, layout: string, payloads: list<array<string, string>>}
     */
    public function inspectFile(string $path): array
    {
        return $this->parseWorkbook($path);
    }

    /**
     * @return array{sheet: string, layout: string, payloads: list<array<string, string>>}
     */
    private function parseWorkbook(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            $sheets = $this->xlsxReader->readAllSheets($path);

            return $this->sheetParser->payloadsFromWorkbookSheets($sheets);
        }

        $rows = $this->readCsvRows($path);
        $payloads = $this->sheetParser->payloadsFromSheetRows($rows);

        return [
            'sheet' => 'CSV',
            'layout' => $this->sheetParser->isTransposedLayout($rows) ? 'transposed' : 'rows',
            'payloads' => $payloads,
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: {$path}");
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($value) => $value === '' ? null : $value, $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private function importPayload(array $payload): void
    {
        $program = $this->resolveRollout($payload);
        if ($program === null) {
            throw new \RuntimeException('Unable to resolve rollout (provide TCO SITE ID or create fields).');
        }

        $metadata = array_intersect_key($payload, array_flip([
            'mno_anchor_site_id',
            'region',
            'territory',
            'area',
            'alliance_tag',
            'search_ring_name',
            'site_license_remarks',
        ]));

        if ($metadata !== []) {
            $this->programs->backfillImportedMetadata($program, $metadata);
            $program->refresh();
        }

        $this->programs->backfillImportedDates($program, array_intersect_key($payload, array_flip([
            'endorsement_date',
            'tssr_approved_date',
            'site_license_executed_date',
            'actual_rfi_date',
            'energization_tempo_date',
            'rfti_signed_tempo_date',
        ])));
        $program->refresh();

        if (isset($payload['full_address']) || isset($payload['latitude']) || isset($payload['longitude'])) {
            $siteProfile = array_filter(
                array_intersect_key($payload, array_flip([
                    'full_address',
                    'latitude',
                    'longitude',
                ])),
                static fn ($value) => $value !== null && $value !== '',
            );
            if ($siteProfile !== []) {
                $this->programs->backfillImportedSiteProfile($program, $siteProfile);
                $program->refresh();
            }
        }

        $permits = $this->permitsFromPayload($payload);
        if ($permits !== []) {
            $this->permits->syncForProgram($program, $permits);
        }

        $phaseDates = $this->phaseDatesFromPayload($payload);
        if ($phaseDates !== []) {
            $this->phaseDates->bulkApply([$program->id], $phaseDates, true, null, true);
        }

        $this->importColocationTenant($program, $payload, 2);
        $this->importColocationTenant($program, $payload, 3);
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private function resolveRollout(array $payload): ?RolloutProgram
    {
        if (! empty($payload['tco_site_id'])) {
            $existing = RolloutProgram::query()
                ->where('tco_site_id', $payload['tco_site_id'])
                ->whereNull('parent_rollout_id')
                ->first();
            if ($existing instanceof RolloutProgram) {
                return $existing;
            }
        }

        if (! empty($payload['rollout_ref'])) {
            $existing = RolloutProgram::query()
                ->where('rollout_ref', $payload['rollout_ref'])
                ->whereNull('parent_rollout_id')
                ->first();
            if ($existing instanceof RolloutProgram) {
                return $existing;
            }
        }

        if (empty($payload['mno']) || empty($payload['project_type'])) {
            if (empty($payload['tco_site_id']) && empty($payload['rollout_ref'])) {
                return null;
            }

            $payload['mno'] = $payload['mno'] ?? 'globe';
            $payload['project_type'] = $payload['project_type'] ?? 'bts';
        }

        return $this->programs->create([
            'mno' => strtolower((string) $payload['mno']),
            'project_type' => strtolower((string) $payload['project_type']),
            'endorsement_date' => $payload['endorsement_date'] ?? null,
            'search_ring_name' => $payload['search_ring_name'] ?? null,
            'region' => $payload['region'] ?? null,
            'territory' => $payload['territory'] ?? null,
            'area' => $payload['area'] ?? null,
            'alliance_tag' => $payload['alliance_tag'] ?? null,
            'mno_anchor_site_id' => $payload['mno_anchor_site_id'] ?? null,
            'tco_site_id' => $payload['tco_site_id'] ?? null,
            'rollout_ref' => $payload['rollout_ref'] ?? null,
        ]);
    }

    /**
     * @param  array<string, string|null>  $payload
     * @return list<array{permit_type: string, applied_date?: string|null, secured_date?: string|null}>
     */
    private function permitsFromPayload(array $payload): array
    {
        /** @var array<string, array{applied_date?: string, secured_date?: string}> $grouped */
        $grouped = [];

        foreach (self::PERMIT_FIELD_MAP as $field => $mapping) {
            if (empty($payload[$field])) {
                continue;
            }

            [$type, $slot] = explode(':', $mapping, 2);
            if (! RolloutPermitCatalog::isValid($type)) {
                continue;
            }

            $grouped[$type] ??= [];
            $grouped[$type][$slot === 'applied' ? 'applied_date' : 'secured_date'] = (string) $payload[$field];
        }

        $rows = [];
        foreach ($grouped as $type => $dates) {
            $rows[] = array_merge(['permit_type' => $type], $dates);
        }

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $payload
     * @return list<array{phase_key: string, actual_date: string}>
     */
    private function phaseDatesFromPayload(array $payload): array
    {
        $entries = [];

        foreach (self::PHASE_END_FIELDS as $field => $phaseKey) {
            if (! empty($payload[$field])) {
                $entries[] = ['phase_key' => $phaseKey, 'actual_date' => (string) $payload[$field]];
            }
        }

        foreach (self::PHASE_START_FIELDS as $field => $phaseKey) {
            if (! empty($payload[$field])) {
                $entries[] = ['phase_key' => $phaseKey, 'actual_date' => (string) $payload[$field]];
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private function importColocationTenant(RolloutProgram $parent, array $payload, int $index): void
    {
        $mnoKey = "coloc_{$index}_mno";
        if (empty($payload[$mnoKey])) {
            return;
        }

        $child = RolloutProgram::query()
            ->where('parent_rollout_id', $parent->id)
            ->where('mno', strtolower((string) $payload[$mnoKey]))
            ->first();

        if (! $child instanceof RolloutProgram) {
            $child = $this->programs->create([
                'parent_rollout_id' => $parent->id,
                'mno' => strtolower((string) $payload[$mnoKey]),
                'project_type' => 'colocation',
                'search_ring_name' => $payload["coloc_{$index}_site_name"] ?? $parent->search_ring_name,
                'region' => $parent->region,
                'territory' => $parent->territory,
            ]);
        }

        $updates = [];
        $remarksKey = "coloc_{$index}_sl_remarks";
        if (! empty($payload[$remarksKey])) {
            $updates['site_license_remarks'] = $payload[$remarksKey];
        }

        $rftiKey = "coloc_{$index}_rfti_date";
        if (! empty($payload[$rftiKey])) {
            $this->programs->backfillImportedDates($child, [
                'actual_rfi_date' => $payload[$rftiKey],
                'tssr_approved_date' => $child->tssr_approved_date?->toDateString()
                    ?? $parent->tssr_approved_date?->toDateString(),
            ]);
        } elseif ($updates !== []) {
            $this->programs->updateMetadata($child, $updates);
        }
    }
}
