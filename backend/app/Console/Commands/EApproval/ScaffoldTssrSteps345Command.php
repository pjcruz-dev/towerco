<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Appends TSSR compose Steps 3–5 (radio room / rooftop systems / site narrative)
 * using Option B sectioned layout (one section per step + dividers + 2-col rows).
 */
final class ScaffoldTssrSteps345Command extends Command
{
    use ResolvesTenantFromConsoleOptions;

    private const SECTION_STEP3 = 'c_radio_room_access_power';

    private const SECTION_STEP4 = 'd_rooftop_earthing_drainage';

    private const SECTION_STEP5 = 'e_site_conditions_narrative';

    private const STEP_FIELD_NAMES = [
        self::SECTION_STEP3,
        'tssr_s3_radio_room',
        'd_type_of_radio_room',
        'tssr_s3_accessibility',
        'e1_access_status',
        'e2_vehicle_passage',
        'e3_access_road_types',
        'e4_site_conditions',
        'tssr_s3_power',
        'f_commercial_power',
        'tssr_s3_greenfield',
        'gf_secondary_power_line_m',
        'gf_primary_power_line_m',
        'gf_primary_pole_spacing_m',
        'gf_primary_pole_count',
        'gf_commercial_ac_supplied_by',
        's3_drawing_instruction',
        self::SECTION_STEP4,
        'tssr_s4_rooftop',
        'rooftop_meter_type',
        'rooftop_meter_instruction',
        'temporary_power_source',
        'tssr_s4_earthing',
        'g_earthing_system',
        'tssr_s4_drainage',
        'h_drainage_system',
        'h_drainage_instruction',
        'tssr_s4_obstruction',
        'i_obstruction_during_erection',
        self::SECTION_STEP5,
        'tssr_s5_site_conditions',
        'j_flood_history',
        'k_lot_elevation',
        'k1_imported_materials',
        'k2_retaining_wall',
        'l_soil_condition',
        'm_usage_of_location',
        'tssr_s5_ordinance',
        'n_existing_ordinance',
        'o_peace_and_order',
        'tssr_s5_narrative',
        'p_mode_of_transportation',
        'q_variation_works',
        'r_remarks_narrative',
    ];

    private const ROW_S3_ACCESS = 'row_tssr_s3_access';

    private const ROW_S3_GREENFIELD_A = 'row_tssr_s3_greenfield_a';

    private const ROW_S3_GREENFIELD_B = 'row_tssr_s3_greenfield_b';

    private const ROW_S5_JK = 'row_tssr_s5_jk';

    private const ROW_S5_K12 = 'row_tssr_s5_k12';

    private const ROW_S5_LM = 'row_tssr_s5_lm';

    protected $signature = 'e-approval:scaffold-tssr-steps-3-5
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--form= : E-Approval form UUID (defaults to name match TSSR)}
        {--force : Replace existing Step 3–5 fields if present}
        {--dry-run : Preview without writing}
    ';

    protected $description = 'Scaffold TSSR Steps 3–5 (radio room, rooftop systems, site narrative) with Option B layout.';

    public function handle(): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if (! $tenant instanceof Tenant) {
            $this->error('No tenant matched. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $formId = $this->option('form');
        $formId = is_string($formId) && $formId !== '' ? $formId : null;

        $summary = $tenant->run(function () use ($dryRun, $force, $formId): array {
            return $this->applyToTenant($dryRun, $force, $formId);
        });

        if ($summary['error'] !== null) {
            $this->error($summary['error']);

            return self::FAILURE;
        }

        $this->line(sprintf(
            '[%s] form=%s | created=%d | removed=%d | total_fields=%d | layout_rows=%d%s',
            $tenant->domains()->value('domain') ?? $tenant->id,
            $summary['form_name'],
            $summary['created'],
            $summary['removed'],
            $summary['total_fields'],
            $summary['layout_row_count'],
            $dryRun ? ' [dry-run]' : '',
        ));

        foreach ($summary['order'] as $line) {
            $this->line('  '.$line);
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply.'
            : 'Steps 3–5 scaffolded. Reload the form editor (discard unsaved canvas) to see changes.');

        return self::SUCCESS;
    }

    /**
     * @return array{error: ?string, form_name: string, created: int, removed: int, total_fields: int, layout_row_count: int, order: list<string>}
     */
    private function applyToTenant(bool $dryRun, bool $force, ?string $formId): array
    {
        $empty = [
            'error' => null,
            'form_name' => '',
            'created' => 0,
            'removed' => 0,
            'total_fields' => 0,
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

        $existing = $form->fields->keyBy(static fn (EApprovalFormField $f): string => (string) $f->name);
        $already = $existing->has(self::SECTION_STEP3);
        if ($already && ! $force) {
            $empty['error'] = 'Steps 3–5 already exist. Re-run with --force to replace them.';

            return $empty;
        }

        $removed = 0;
        if ($already && $force) {
            $toRemove = $form->fields->filter(
                static fn (EApprovalFormField $f): bool => in_array((string) $f->name, self::STEP_FIELD_NAMES, true),
            );
            $removed = $toRemove->count();
            if (! $dryRun) {
                foreach ($toRemove as $field) {
                    $field->delete();
                }
            }
            $form->load('fields');
            $existing = $form->fields->keyBy(static fn (EApprovalFormField $f): string => (string) $f->name);
        }

        $baseFields = $form->fields->sortBy('step_order')->values();
        $startOrder = ((int) ($baseFields->max('step_order') ?? 0)) + 1;

        $defs = $this->fieldDefinitions();
        $createdModels = [];
        foreach ($defs as $offset => $def) {
            $field = new EApprovalFormField([
                'form_id' => $form->id,
                'type' => $def['type'],
                'name' => mb_substr($def['name'], 0, 100),
                'label' => $def['label'],
                'step_order' => $startOrder + $offset,
                'options' => $def['options'] ?? [],
            ]);
            $field->id = (string) Str::uuid();
            $createdModels[] = $field;
        }

        $allFields = $baseFields->concat($createdModels)->values();
        $indexOf = static function (string $name) use ($allFields): int {
            foreach ($allFields as $i => $field) {
                if ((string) $field->name === $name) {
                    return $i;
                }
            }

            return 0;
        };

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $existingRows = is_array($metadata['builder_layout_rows'] ?? null) ? $metadata['builder_layout_rows'] : [];
        $dropRowIds = [
            self::ROW_S3_ACCESS,
            self::ROW_S3_GREENFIELD_A,
            self::ROW_S3_GREENFIELD_B,
            self::ROW_S5_JK,
            self::ROW_S5_K12,
            self::ROW_S5_LM,
        ];
        $keptRows = [];
        foreach ($existingRows as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_string($row['id'])) {
                continue;
            }
            if (in_array($row['id'], $dropRowIds, true)) {
                continue;
            }
            $keptRows[] = $row;
        }

        $layoutRows = array_values(array_merge($keptRows, [
            ['id' => self::ROW_S3_ACCESS, 'columns' => 2, 'insert_index' => $indexOf('e1_access_status')],
            ['id' => self::ROW_S3_GREENFIELD_A, 'columns' => 2, 'insert_index' => $indexOf('gf_secondary_power_line_m')],
            ['id' => self::ROW_S3_GREENFIELD_B, 'columns' => 2, 'insert_index' => $indexOf('gf_primary_pole_spacing_m')],
            ['id' => self::ROW_S5_JK, 'columns' => 2, 'insert_index' => $indexOf('j_flood_history')],
            ['id' => self::ROW_S5_K12, 'columns' => 2, 'insert_index' => $indexOf('k1_imported_materials')],
            ['id' => self::ROW_S5_LM, 'columns' => 2, 'insert_index' => $indexOf('l_soil_condition')],
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

        // Ensure stepped compose uses sections.
        $compose = is_array($metadata['compose'] ?? null) ? $metadata['compose'] : [];
        $compose['mode'] = 'stepped';
        $compose['step_source'] = $compose['step_source'] ?? $compose['stepSource'] ?? 'sections';
        $metadata['compose'] = $compose;

        if (! $dryRun) {
            foreach ($createdModels as $field) {
                $field->save();
            }
            $form->metadata_json = $metadata;
            $form->save();
        }

        $orderLines = [];
        foreach ($allFields as $index => $field) {
            $orderLines[] = sprintf('%d. %s (%s)', $index + 1, $field->name, $field->type);
        }

        return [
            'error' => null,
            'form_name' => (string) $form->name,
            'created' => count($createdModels),
            'removed' => $removed,
            'total_fields' => $allFields->count(),
            'layout_row_count' => count($layoutRows),
            'order' => $orderLines,
        ];
    }

    /**
     * @return list<array{type: string, name: string, label: string, options?: array<string, mixed>}>
     */
    private function fieldDefinitions(): array
    {
        $half = static function (string $rowId, int $slot): array {
            return [
                'layout' => [
                    'row_id' => $rowId,
                    'slot' => $slot,
                    'stack_order' => 0,
                    'row_columns' => 2,
                    'width' => 'half',
                ],
            ];
        };
        $full = static fn (): array => ['layout' => ['width' => 'full']];

        return [
            // ── Step 3 ──────────────────────────────────────────────
            [
                'type' => 'section',
                'name' => self::SECTION_STEP3,
                'label' => 'C. Radio Room / Access / Power',
                'options' => [],
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s3_radio_room',
                'label' => 'Radio room',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'd_type_of_radio_room',
                'label' => 'D. Type of Radio Room (Provide separate drawing)',
                'options' => array_merge($full(), [
                    'choices' => [
                        [
                            'value' => 'dry_wall',
                            'label' => 'Dry Wall Type Panel/Collapsible cabin',
                            'inputs' => [['key' => 'size', 'type' => 'size']],
                        ],
                        [
                            'value' => 'container_van',
                            'label' => "20'/40' Container Van",
                            'inputs' => [['key' => 'size', 'type' => 'size']],
                        ],
                        [
                            'value' => 'prefab',
                            'label' => 'Prefab / Modular cabin',
                            'inputs' => [['key' => 'size', 'type' => 'size']],
                        ],
                        [
                            'value' => 'others',
                            'label' => 'Others (specify)',
                            'inputs' => [
                                ['key' => 'specify', 'type' => 'text', 'placeholder' => 'Specify'],
                            ],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s3_accessibility',
                'label' => 'Accessibility',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'e1_access_status',
                'label' => 'E1. Access status',
                'options' => array_merge($half(self::ROW_S3_ACCESS, 0), [
                    'choices' => [
                        ['value' => 'accessible', 'label' => 'Accessible (with existing right of way)'],
                        ['value' => 'na_not_accessible', 'label' => 'NA Not Accessible'],
                    ],
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'e2_vehicle_passage',
                'label' => 'E2. Types of vehicle that can pass',
                'options' => array_merge($half(self::ROW_S3_ACCESS, 1), [
                    'choices' => [
                        ['value' => 'trailer_truck', 'label' => 'Trailer Truck'],
                        ['value' => 'ten_wheeler', 'label' => 'Ten Wheeler'],
                        ['value' => 'six_wheeler', 'label' => 'Six Wheeler'],
                        ['value' => 'four_wheeler', 'label' => 'Four Wheeler'],
                        ['value' => 'tricycle', 'label' => 'Tricycle'],
                        ['value' => 'all_types', 'label' => 'All types of vehicle'],
                        ['value' => 'none_foot_trail', 'label' => 'None (foot trail)'],
                    ],
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'e3_access_road_types',
                'label' => 'E3. Access road type',
                'options' => array_merge($full(), [
                    'choices' => [
                        [
                            'value' => 'main_road',
                            'label' => 'Main Road',
                            'inputs' => [
                                ['key' => 'length', 'type' => 'number', 'suffix' => 'm', 'placeholder' => 'Length'],
                                ['key' => 'description', 'type' => 'text', 'placeholder' => 'Description'],
                            ],
                        ],
                        [
                            'value' => 'barangay_road',
                            'label' => 'Barangay Road',
                            'inputs' => [
                                ['key' => 'length', 'type' => 'number', 'suffix' => 'm', 'placeholder' => 'Length'],
                                ['key' => 'description', 'type' => 'text', 'placeholder' => 'Description'],
                            ],
                        ],
                        [
                            'value' => 'foot_trail',
                            'label' => 'Foot Trail',
                            'inputs' => [
                                ['key' => 'length', 'type' => 'number', 'suffix' => 'm', 'placeholder' => 'Length'],
                                ['key' => 'description', 'type' => 'text', 'placeholder' => 'Description'],
                            ],
                        ],
                        [
                            'value' => 'provide_access_road',
                            'label' => 'Provide access road to site (right of way)',
                            'inputs' => [
                                ['key' => 'length', 'type' => 'number', 'suffix' => 'm', 'placeholder' => 'Length'],
                                ['key' => 'description', 'type' => 'text', 'placeholder' => 'Description'],
                            ],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'matrix',
                'name' => 'e4_site_conditions',
                'label' => 'E4. Site conditions (elevation / structures / slope)',
                'options' => array_merge($full(), [
                    'rows' => [
                        ['value' => 'a', 'label' => 'A. Diff. in elev. from fronting road / access'],
                        ['value' => 'b', 'label' => 'B. Existing structure affected'],
                        ['value' => 'c', 'label' => 'C. Slope Protection Required'],
                    ],
                    'columns' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ],
                    'row_notes' => true,
                    'row_notes_label' => 'Notes / Approx. m / Photos',
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s3_power',
                'label' => 'Power',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'f_commercial_power',
                'label' => 'F. Commercial Power Availability',
                'options' => array_merge($full(), [
                    'choices' => [
                        ['value' => 'single_phase', 'label' => 'Single Phase'],
                        ['value' => 'three_phase', 'label' => 'Three Phase'],
                    ],
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s3_greenfield',
                'label' => 'Greenfield sites',
                'options' => [],
            ],
            [
                'type' => 'number',
                'name' => 'gf_secondary_power_line_m',
                'label' => 'A. Secondary Power Line — length (m)',
                'options' => $half(self::ROW_S3_GREENFIELD_A, 0),
            ],
            [
                'type' => 'number',
                'name' => 'gf_primary_power_line_m',
                'label' => 'B. Primary Power Line — distance (m)',
                'options' => $half(self::ROW_S3_GREENFIELD_A, 1),
            ],
            [
                'type' => 'number',
                'name' => 'gf_primary_pole_spacing_m',
                'label' => 'Estimated pole spacing (m)',
                'options' => $half(self::ROW_S3_GREENFIELD_B, 0),
            ],
            [
                'type' => 'number',
                'name' => 'gf_primary_pole_count',
                'label' => 'Estimated no. of poles (pcs)',
                'options' => $half(self::ROW_S3_GREENFIELD_B, 1),
            ],
            [
                'type' => 'text',
                'name' => 'gf_commercial_ac_supplied_by',
                'label' => 'Commercial AC Power supplied by',
                'options' => $full(),
            ],
            [
                'type' => 'instruction',
                'name' => 's3_drawing_instruction',
                'label' => 'Drawing note',
                'options' => array_merge($full(), [
                    'body' => 'Provide a separate drawing for the radio room. For elevation / structure / slope items marked Yes, attach supporting photos where applicable.',
                ]),
            ],

            // ── Step 4 ──────────────────────────────────────────────
            [
                'type' => 'section',
                'name' => self::SECTION_STEP4,
                'label' => 'D. Rooftop / Earthing / Drainage / Obstructions',
                'options' => [],
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s4_rooftop',
                'label' => 'Rooftop metering',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'rooftop_meter_type',
                'label' => 'Rooftop Sites — Meter type',
                'options' => array_merge($full(), [
                    'choices' => [
                        ['value' => 'separate_meter', 'label' => 'Separate meter (from Electric Co.)'],
                        ['value' => 'sub_meter', 'label' => 'Sub-meter (from building)'],
                        ['value' => 'na', 'label' => 'N/A'],
                    ],
                ]),
            ],
            [
                'type' => 'instruction',
                'name' => 'rooftop_meter_instruction',
                'label' => 'Meter / temporary power notes',
                'options' => array_merge($full(), [
                    'body' => "Approval of Lessor of the proposed location of meter or sub-meter.\n\n• Check load availability of the proposed tapping point.\n• Confirm temporary power supply arrangement during construction.",
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'temporary_power_source',
                'label' => 'Temporary power source',
                'options' => array_merge($full(), [
                    'choices' => [
                        ['value' => 'local_coop', 'label' => 'Tempo Power c/o of Local Coop'],
                        ['value' => 'neighbor_admin', 'label' => 'Tempo Power c/o of neighbor/bldg admin'],
                        ['value' => 'mobile_genset', 'label' => 'Tempo Power by mobile genset'],
                        [
                            'value' => 'others',
                            'label' => 'Others',
                            'inputs' => [['key' => 'specify', 'type' => 'text', 'placeholder' => 'Specify']],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s4_earthing',
                'label' => 'G. Earthing System (Rooftop)',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'g_earthing_system',
                'label' => 'G. Earthing System (for Rooftop Sites)',
                'options' => array_merge($full(), [
                    'choices' => [
                        [
                            'value' => 'separate',
                            'label' => 'Separate earthing system',
                            'help' => 'Negotiate with building owner for earthing route and pit location.',
                            'inputs' => [
                                [
                                    'key' => 'tapping_length',
                                    'type' => 'number',
                                    'suffix' => 'm',
                                    'placeholder' => 'Approx. length of tapping point',
                                ],
                            ],
                        ],
                        [
                            'value' => 'tap_existing',
                            'label' => 'Grounding to be tapped with existing building ground',
                            'help' => 'Discuss route with building owner; check for obstructions along the proposed path.',
                            'inputs' => [
                                [
                                    'key' => 'tapping_length',
                                    'type' => 'number',
                                    'suffix' => 'm',
                                    'placeholder' => 'Approx. length of tapping point',
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s4_drainage',
                'label' => 'H. Drainage System (Greenfield)',
                'options' => [],
            ],
            [
                'type' => 'checkbox',
                'name' => 'h_drainage_system',
                'label' => 'H. Drainage System (for greenfield only)',
                'options' => array_merge($full(), [
                    'choices' => [
                        [
                            'value' => 'existing',
                            'label' => 'With existing drainage system',
                            'inputs' => [
                                ['key' => 'type_description', 'type' => 'text', 'placeholder' => 'Type / Description'],
                            ],
                        ],
                        [
                            'value' => 'new',
                            'label' => 'Provide new drainage system',
                            'inputs' => [
                                ['key' => 'type_description', 'type' => 'text', 'placeholder' => 'Type / Description'],
                            ],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'instruction',
                'name' => 'h_drainage_instruction',
                'label' => 'Drainage note',
                'options' => array_merge($full(), [
                    'body' => 'See specific location of drainage system on conceptual drawing.',
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s4_obstruction',
                'label' => 'Obstructions',
                'options' => [],
            ],
            [
                'type' => 'matrix',
                'name' => 'i_obstruction_during_erection',
                'label' => 'I. Obstruction during Erection',
                'options' => array_merge($full(), [
                    'rows' => [
                        ['value' => 'high_tension_wire', 'label' => 'High Tension Wire'],
                        ['value' => 'existing_structure', 'label' => 'Existing Structure'],
                        ['value' => 'neighbors_too_close', 'label' => 'Neighbors too close'],
                        ['value' => 'others', 'label' => 'Others'],
                    ],
                    'columns' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'none', 'label' => 'None'],
                    ],
                    'row_notes' => true,
                    'row_notes_label' => 'Notes',
                ]),
            ],

            // ── Step 5 ──────────────────────────────────────────────
            [
                'type' => 'section',
                'name' => self::SECTION_STEP5,
                'label' => 'E. Site Conditions / Ordinance / Narrative',
                'options' => [],
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s5_site_conditions',
                'label' => 'Site conditions',
                'options' => [],
            ],
            [
                'type' => 'text',
                'name' => 'j_flood_history',
                'label' => 'J. Flood History (as inquired from Lessor and/or neighbors)',
                'options' => array_merge($half(self::ROW_S5_JK, 0), [
                    'help_text' => 'With respect to the road',
                ]),
            ],
            [
                'type' => 'text',
                'name' => 'k_lot_elevation',
                'label' => 'K. Lot Elevation from fronting road (approx.)',
                'options' => array_merge($half(self::ROW_S5_JK, 1), [
                    'help_text' => 'Format: (+) __ m / (-) __ m',
                    'placeholder' => '(+) 0 m / (-) 0 m',
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'k1_imported_materials',
                'label' => 'K.1 Does it require imported materials to attain desired elevation?',
                'options' => array_merge($half(self::ROW_S5_K12, 0), [
                    'choices' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ],
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'k2_retaining_wall',
                'label' => 'K.2 Does it require retaining wall?',
                'options' => array_merge($half(self::ROW_S5_K12, 1), [
                    'choices' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ],
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'l_soil_condition',
                'label' => 'L. Soil Condition Report',
                'options' => array_merge($half(self::ROW_S5_LM, 0), [
                    'help_text' => 'To be secured from Engineering Office / geotech report as applicable.',
                    'choices' => [
                        ['value' => 'sandy', 'label' => 'Sandy'],
                        ['value' => 'clayey', 'label' => 'Clayey'],
                        ['value' => 'rocky', 'label' => 'Rocky'],
                        ['value' => 'loose_soil', 'label' => 'Loose Soil'],
                        ['value' => 'hard_rock', 'label' => 'Hard Rock'],
                        [
                            'value' => 'others',
                            'label' => 'Others',
                            'inputs' => [['key' => 'specify', 'type' => 'text', 'placeholder' => 'Specify']],
                        ],
                    ],
                ]),
            ],
            [
                'type' => 'checkbox',
                'name' => 'm_usage_of_location',
                'label' => 'M. Usage of Proposed Location',
                'options' => array_merge($half(self::ROW_S5_LM, 1), [
                    'choices' => [
                        ['value' => 'residential', 'label' => 'Residential'],
                        ['value' => 'commercial', 'label' => 'Commercial'],
                        ['value' => 'industrial', 'label' => 'Industrial'],
                        ['value' => 'agricultural', 'label' => 'Agricultural'],
                    ],
                ]),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s5_ordinance',
                'label' => 'Ordinance & peace',
                'options' => [],
            ],
            [
                'type' => 'matrix',
                'name' => 'n_existing_ordinance',
                'label' => 'N. Existing Ordinance',
                'options' => array_merge($full(), [
                    'rows' => [
                        ['value' => 'city', 'label' => 'City'],
                        ['value' => 'municipal', 'label' => 'Municipal'],
                        ['value' => 'barangay', 'label' => 'Barangay'],
                    ],
                    'columns' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'na', 'label' => 'NA'],
                    ],
                    'row_notes' => true,
                    'row_notes_label' => 'Notes / Remarks',
                ]),
            ],
            [
                'type' => 'textarea',
                'name' => 'o_peace_and_order',
                'label' => 'O. Peace and Order Situation, History',
                'options' => $full(),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_s5_narrative',
                'label' => 'Narrative',
                'options' => [],
            ],
            [
                'type' => 'textarea',
                'name' => 'p_mode_of_transportation',
                'label' => 'P. Mode of Transportation going to proposed site (from main road/town)',
                'options' => $full(),
            ],
            [
                'type' => 'textarea',
                'name' => 'q_variation_works',
                'label' => 'Q. Variation Works',
                'options' => $full(),
            ],
            [
                'type' => 'textarea',
                'name' => 'r_remarks_narrative',
                'label' => 'R. Remarks / Narrative Report',
                'options' => $full(),
            ],
        ];
    }
}
