<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ApplyTssrOptionBLayoutCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    private const NAMES = [
        'section' => 'b_site_survey_report',
        'distance' => 'a_distance_from_shoreline',
        'structure' => 'b_type_and_height_of_structure',
        'buildingMounted' => 'building_mounted_produce_as_built_drawing_check_structural_stability_of_building_seek_structural',
        'roof' => 'roof',
        'attachment' => 'c_type_of_attachment_anchorage',
        'topographic' => 'c1_topographic_condition_of_the_site',
        'rolling' => 'c2_if_the_site_is_rolling_or_mountains_does_it_require',
        'floors' => 'floors',
        'instructions' => 'instructions',
        'dividerBasics' => 'tssr_divider_site_basics',
        'dividerStructure' => 'tssr_divider_structure',
        'dividerComponents' => 'tssr_divider_building_components',
        'dividerTerrain' => 'tssr_divider_terrain',
        'dividerFloors' => 'tssr_divider_floors',
        'sectionSiteOptions' => 'b_site_options_justifications',
        'siteOptionsMap' => 'b_site_options_map',
        'siteOptionsGrid' => 'b_site_options_and_justifications',
        'sectionB1Photos' => 'b1_site_development_photos',
        'panoramicPhotos' => 'b1b_panoramic_site_photos',
        'photoTowerGreenfield' => 'b1c_proposed_tower_location_greenfield',
        'photoEquipmentRooftop' => 'b1d_proposed_equipment_modified_room_rooftop',
        'photoKwhrMeter' => 'b1e_proposed_kwhr_meter_ecb_tapping_point',
        'photoElectricalFacilities' => 'b1f_nearest_existing_electrical_facilities',
        'dividerGoingToSite' => 'tssr_divider_going_to_proposed_site',
        'goingToSiteApproach' => 'going_to_proposed_site_approach',
        'dividerGoingToSiteCaption' => 'tssr_divider_going_to_proposed_site_caption',
        'goingToSiteLocation' => 'going_to_proposed_site_location',
    ];

    private const PHOTO_BLOCK = [
        'sectionSiteOptions',
        'siteOptionsMap',
        'siteOptionsGrid',
        'sectionB1Photos',
        'panoramicPhotos',
        'photoTowerGreenfield',
        'photoEquipmentRooftop',
        'photoKwhrMeter',
        'photoElectricalFacilities',
        'dividerGoingToSite',
        'goingToSiteApproach',
        'dividerGoingToSiteCaption',
        'goingToSiteLocation',
    ];

    private const ROW_BASICS = 'row_tssr_site_basics';

    private const ROW_STRUCTURE = 'row_tssr_structure';

    private const ROW_TERRAIN = 'row_tssr_terrain';

    private const LEGACY_ROW_IDS = ['row_ms5f1ohq', 'row_ms5mucbp'];

    protected $signature = 'e-approval:apply-tssr-option-b-layout
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--form= : E-Approval form UUID (defaults to name match TSSR)}
        {--dry-run : Preview changes without writing}
    ';

    protected $description = 'Apply Option B sectioned Site Survey layout to a TSSR e-approval form.';

    public function handle(): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if (! $tenant instanceof Tenant) {
            $this->error('No tenant matched. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $formId = $this->option('form');
        $formId = is_string($formId) && $formId !== '' ? $formId : null;

        $summary = $tenant->run(function () use ($dryRun, $formId): array {
            return $this->applyToTenant($dryRun, $formId);
        });

        if ($summary['error'] !== null) {
            $this->error($summary['error']);

            return self::FAILURE;
        }

        $this->line(sprintf(
            '[%s] form=%s | fields=%d | dividers_created=%d | layout_rows=%d%s',
            $tenant->domains()->value('domain') ?? $tenant->id,
            $summary['form_name'],
            $summary['field_count'],
            $summary['dividers_created'],
            $summary['layout_row_count'],
            $dryRun ? ' [dry-run]' : '',
        ));

        foreach ($summary['order'] as $line) {
            $this->line('  '.$line);
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply.'
            : 'Option B layout applied. Reload the form editor (discard unsaved canvas) to see changes.');

        return self::SUCCESS;
    }

    /**
     * @return array{error: ?string, form_name: string, field_count: int, dividers_created: int, layout_row_count: int, order: list<string>}
     */
    private function applyToTenant(bool $dryRun, ?string $formId): array
    {
        $empty = [
            'error' => null,
            'form_name' => '',
            'field_count' => 0,
            'dividers_created' => 0,
            'layout_row_count' => 0,
            'order' => [],
        ];

        $query = EApprovalForm::query()->with('fields');
        if ($formId !== null) {
            $query->whereKey($formId);
        } else {
            $query->where(function ($q): void {
                $q->where('name', 'TSSR')->orWhere('name', 'like', '%TSSR%');
            });
        }

        /** @var EApprovalForm|null $form */
        $form = $query->orderBy('name')->first();
        if ($form === null) {
            $empty['error'] = 'TSSR form not found.';

            return $empty;
        }

        $fields = $form->fields->sortBy('step_order')->values();
        $byName = $fields->keyBy(static fn (EApprovalFormField $f): string => (string) $f->name);

        $required = [
            self::NAMES['distance'],
            self::NAMES['structure'],
            self::NAMES['buildingMounted'],
            self::NAMES['roof'],
            self::NAMES['attachment'],
            self::NAMES['topographic'],
            self::NAMES['rolling'],
            self::NAMES['floors'],
        ];
        foreach ($required as $name) {
            if (! $byName->has($name)) {
                $empty['error'] = "Missing required field: {$name}";

                return $empty;
            }
        }

        $section = $byName->get(self::NAMES['section']);
        if (! $section instanceof EApprovalFormField) {
            $empty['error'] = 'Missing section field: '.self::NAMES['section'];

            return $empty;
        }

        $sectionIndex = $fields->search(static fn (EApprovalFormField $f): bool => $f->id === $section->id);
        if ($sectionIndex === false) {
            $empty['error'] = 'Could not locate section field in form.';

            return $empty;
        }

        $photoNames = [];
        foreach (self::PHOTO_BLOCK as $key) {
            $photoNames[] = self::NAMES[$key];
        }

        $consumed = array_merge($required, [
            self::NAMES['instructions'],
            self::NAMES['dividerBasics'],
            self::NAMES['dividerStructure'],
            self::NAMES['dividerComponents'],
            self::NAMES['dividerTerrain'],
            self::NAMES['dividerFloors'],
        ], $photoNames);
        $consumedSet = array_fill_keys($consumed, true);

        $prefix = $fields->slice(0, (int) $sectionIndex + 1)->values();
        $trailing = $fields
            ->slice((int) $sectionIndex + 1)
            ->filter(static fn (EApprovalFormField $f): bool => ! isset($consumedSet[(string) $f->name]))
            ->values();

        $dividersCreated = 0;
        $ensureDivider = function (string $name, string $label) use ($byName, &$dividersCreated): EApprovalFormField {
            $existing = $byName->get($name);
            if ($existing instanceof EApprovalFormField) {
                return $existing;
            }
            $dividersCreated++;
            $field = new EApprovalFormField([
                'type' => 'divider',
                'name' => $name,
                'label' => $label,
                'step_order' => 0,
                'options' => [],
            ]);
            $field->id = (string) Str::uuid();

            return $field;
        };

        $half = static function (EApprovalFormField $field, string $rowId, int $slot, int $stackOrder): EApprovalFormField {
            $options = is_array($field->options) ? $field->options : [];
            $options['layout'] = [
                'row_id' => $rowId,
                'slot' => $slot,
                'stack_order' => $stackOrder,
                'row_columns' => 2,
                'width' => 'half',
            ];
            $field->options = $options;

            return $field;
        };

        $full = static function (EApprovalFormField $field): EApprovalFormField {
            $options = is_array($field->options) ? $field->options : [];
            $options['layout'] = ['width' => 'full'];
            $field->options = $options;

            return $field;
        };

        $block = [
            $ensureDivider(self::NAMES['dividerBasics'], 'Site basics'),
            $half($byName->get(self::NAMES['distance']), self::ROW_BASICS, 0, 0),
            $half($byName->get(self::NAMES['attachment']), self::ROW_BASICS, 1, 0),
            $ensureDivider(self::NAMES['dividerStructure'], 'Structure'),
            $full($byName->get(self::NAMES['structure'])),
            $full($byName->get(self::NAMES['buildingMounted'])),
            $ensureDivider(self::NAMES['dividerComponents'], 'Building components'),
            $full($byName->get(self::NAMES['roof'])),
            $ensureDivider(self::NAMES['dividerTerrain'], 'Terrain & access conditions'),
            $half($byName->get(self::NAMES['topographic']), self::ROW_TERRAIN, 0, 0),
            $half($byName->get(self::NAMES['rolling']), self::ROW_TERRAIN, 1, 0),
            $ensureDivider(self::NAMES['dividerFloors'], 'Floors / as-built'),
            $full($byName->get(self::NAMES['floors'])),
        ];

        $instructions = $byName->get(self::NAMES['instructions']);
        if ($instructions instanceof EApprovalFormField) {
            $block[] = $full($instructions);
        }

        foreach (self::PHOTO_BLOCK as $key) {
            $photoField = $byName->get(self::NAMES[$key]);
            if (! $photoField instanceof EApprovalFormField) {
                continue;
            }
            $block[] = in_array($photoField->type, ['divider', 'section'], true)
                ? $photoField
                : $full($photoField);
        }

        $ordered = $prefix->concat($block)->concat($trailing)->values();
        $orderLines = [];
        foreach ($ordered as $index => $field) {
            $field->step_order = $index + 1;
            $orderLines[] = sprintf('%d. %s (%s)', $index + 1, $field->name, $field->type);
        }

        $indexOf = static function (string $name) use ($ordered): int {
            foreach ($ordered as $i => $field) {
                if ((string) $field->name === $name) {
                    return $i;
                }
            }

            return 0;
        };

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $existingRows = is_array($metadata['builder_layout_rows'] ?? null) ? $metadata['builder_layout_rows'] : [];
        $keptRows = [];
        foreach ($existingRows as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_string($row['id'])) {
                continue;
            }
            if (in_array($row['id'], [self::ROW_BASICS, self::ROW_STRUCTURE, self::ROW_TERRAIN, ...self::LEGACY_ROW_IDS], true)) {
                continue;
            }
            $keptRows[] = $row;
        }
        $layoutRows = array_values(array_merge($keptRows, [
            ['id' => self::ROW_BASICS, 'columns' => 2, 'insert_index' => $indexOf(self::NAMES['distance'])],
            ['id' => self::ROW_TERRAIN, 'columns' => 2, 'insert_index' => $indexOf(self::NAMES['topographic'])],
        ]));
        usort($layoutRows, static function (array $a, array $b): int {
            $ai = (int) ($a['insert_index'] ?? 0);
            $bi = (int) ($b['insert_index'] ?? 0);
            if ($ai === $bi) {
                return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
            }

            return $ai <=> $bi;
        });
        $metadata['builder_layout_rows'] = $layoutRows;

        if (! $dryRun) {
            foreach ($ordered as $field) {
                if (! $field->exists) {
                    $field->form_id = $form->id;
                    $field->save();
                } else {
                    $field->save();
                }
            }
            // Remove leftover Site Survey fields that were replaced by dividers? none.
            // Delete obsolete fields that were in consumed but no longer in ordered — shouldn't happen.
            $form->metadata_json = $metadata;
            $form->save();
        }

        return [
            'error' => null,
            'form_name' => (string) $form->name,
            'field_count' => $ordered->count(),
            'dividers_created' => $dividersCreated,
            'layout_row_count' => count($layoutRows),
            'order' => $orderLines,
        ];
    }
}
