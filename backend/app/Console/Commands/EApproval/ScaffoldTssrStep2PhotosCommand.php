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
 * Inserts TSSR Step 2 photo documentation + Site Options (map + justifications grid)
 * after Site Survey instructions / before Step 3.
 */
final class ScaffoldTssrStep2PhotosCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    private const ANCHOR_INSTRUCTIONS = 'instructions';

    private const ANCHOR_SECTION_B = 'b_site_survey_report';

    private const ANCHOR_STEP3 = 'c_radio_room_access_power';

    /** @var list<string> */
    private const FIELD_NAMES = [
        // Current section headers
        'b_site_options_justifications',
        'b1_site_development_photos',
        // Legacy divider headers (pre-step split)
        'tssr_divider_site_options',
        'tssr_divider_b1_photos',
        'b_site_options_map',
        'b_site_options_and_justifications',
        'b1b_panoramic_site_photos',
        'b1c_proposed_tower_location_greenfield',
        'b1d_proposed_equipment_modified_room_rooftop',
        'b1e_proposed_kwhr_meter_ecb_tapping_point',
        'b1f_nearest_existing_electrical_facilities',
        'tssr_divider_going_to_proposed_site',
        'going_to_proposed_site_approach',
        'tssr_divider_going_to_proposed_site_caption',
        'going_to_proposed_site_location',
    ];

    private const SECTION_SITE_OPTIONS = 'b_site_options_justifications';

    private const SECTION_B1_PHOTOS = 'b1_site_development_photos';

    protected $signature = 'e-approval:scaffold-tssr-step-2-photos
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--form= : E-Approval form UUID (defaults to name match TSSR)}
        {--force : Replace existing Step 2 photo / site-options fields if present}
        {--dry-run : Preview without writing}
    ';

    protected $description = 'Scaffold TSSR Step 2 site options + B1 photos as their own compose sections/steps.';

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
            '[%s] form=%s | created=%d | removed=%d | total_fields=%d%s',
            $tenant->domains()->value('domain') ?? $tenant->id,
            $summary['form_name'],
            $summary['created'],
            $summary['removed'],
            $summary['total_fields'],
            $dryRun ? ' [dry-run]' : '',
        ));

        foreach ($summary['order'] as $line) {
            $this->line('  '.$line);
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply.'
            : 'Step 2 photos / site options scaffolded. Reload the form editor (discard unsaved canvas) to see changes.');

        return self::SUCCESS;
    }

    /**
     * @return array{error: ?string, form_name: string, created: int, removed: int, total_fields: int, order: list<string>}
     */
    private function applyToTenant(bool $dryRun, bool $force, ?string $formId): array
    {
        $empty = [
            'error' => null,
            'form_name' => '',
            'created' => 0,
            'removed' => 0,
            'total_fields' => 0,
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
        $already = $existing->has('b1b_panoramic_site_photos')
            || $existing->has('b_site_options_and_justifications')
            || $existing->has(self::SECTION_SITE_OPTIONS)
            || $existing->has(self::SECTION_B1_PHOTOS);
        if ($already && ! $force) {
            $empty['error'] = 'Step 2 photos / site options already exist. Re-run with --force to replace them.';

            return $empty;
        }

        $removed = 0;
        if ($already && $force) {
            $toRemove = $form->fields->filter(
                static fn (EApprovalFormField $f): bool => in_array((string) $f->name, self::FIELD_NAMES, true),
            );
            $removed = $toRemove->count();
            if (! $dryRun) {
                foreach ($toRemove as $field) {
                    $field->delete();
                }
            }
            $form->load('fields');
        }

        $baseFields = $form->fields->sortBy('step_order')->values();
        $insertAt = $this->resolveInsertIndex($baseFields);
        $defs = $this->fieldDefinitions();

        $createdModels = [];
        foreach ($defs as $offset => $def) {
            $field = new EApprovalFormField([
                'form_id' => $form->id,
                'type' => $def['type'],
                'name' => mb_substr($def['name'], 0, 100),
                'label' => $def['label'],
                'step_order' => $insertAt + $offset + 1,
                'options' => $def['options'] ?? [],
            ]);
            $field->id = (string) Str::uuid();
            $createdModels[] = $field;
        }

        $before = $baseFields->slice(0, $insertAt)->values();
        $after = $baseFields->slice($insertAt)->values();
        $allFields = $before->concat($createdModels)->concat($after)->values();

        if (! $dryRun) {
            foreach ($createdModels as $field) {
                $field->save();
            }

            foreach ($allFields as $index => $field) {
                $order = $index + 1;
                if ((int) $field->step_order !== $order) {
                    $field->step_order = $order;
                    $field->save();
                }
            }
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
            'order' => $orderLines,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EApprovalFormField>  $fields
     */
    private function resolveInsertIndex($fields): int
    {
        foreach ($fields as $index => $field) {
            if ((string) $field->name === self::ANCHOR_STEP3) {
                return (int) $index;
            }
        }

        foreach ($fields as $index => $field) {
            if ((string) $field->name === self::ANCHOR_INSTRUCTIONS) {
                return (int) $index + 1;
            }
        }

        foreach ($fields as $index => $field) {
            if ((string) $field->name === self::ANCHOR_SECTION_B) {
                // After all current section-B content: append at end if no better anchor.
                return $fields->count();
            }
        }

        return $fields->count();
    }

    /**
     * @return list<array{type: string, name: string, label: string, options?: array<string, mixed>}>
     */
    private function fieldDefinitions(): array
    {
        $full = static fn (): array => ['layout' => ['width' => 'full']];

        $camera = static function (int $min, int $max, array $slots = []) use ($full): array {
            return array_merge($full(), [
                'capture_mode' => 'camera_or_gallery',
                'min' => $min,
                'max' => $max,
                'geotag' => true,
                'caption' => true,
                'slots' => $slots,
            ]);
        };

        $degreeSlots = [];
        for ($deg = 0; $deg <= 330; $deg += 30) {
            $degreeSlots[] = $deg.' degrees';
        }

        return [
            [
                'type' => 'section',
                'name' => self::SECTION_SITE_OPTIONS,
                'label' => 'B. Site Options and Justifications',
                'options' => [],
            ],
            [
                'type' => 'camera',
                'name' => 'b_site_options_map',
                'label' => 'Site options map (Google Earth / vicinity)',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'grid',
                'name' => 'b_site_options_and_justifications',
                'label' => 'Site options and justifications',
                'options' => array_merge($full(), [
                    'columns' => [
                        ['label' => 'Rank', 'type' => 'number'],
                        ['label' => 'Option Names', 'type' => 'text'],
                        ['label' => 'Latitude', 'type' => 'text'],
                        ['label' => 'Longitude', 'type' => 'text'],
                        ['label' => 'Justifications', 'type' => 'text'],
                    ],
                ]),
            ],
            [
                'type' => 'section',
                'name' => self::SECTION_B1_PHOTOS,
                'label' => 'B1. Site Development Photos',
                'options' => [],
            ],
            [
                'type' => 'camera',
                'name' => 'b1b_panoramic_site_photos',
                'label' => 'B1.b Photo of Existing Site Development (Panoramic View)',
                'options' => $camera(0, 12, $degreeSlots),
            ],
            [
                'type' => 'camera',
                'name' => 'b1c_proposed_tower_location_greenfield',
                'label' => 'B1.c Photo of Proposed Tower Location (Greenfield)',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'camera',
                'name' => 'b1d_proposed_equipment_modified_room_rooftop',
                'label' => 'B1.d Photo of Proposed Equipment/Modified Room (Rooftop)',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'camera',
                'name' => 'b1e_proposed_kwhr_meter_ecb_tapping_point',
                'label' => 'B1.e Photo of Proposed KWHR meter, ECB location / Tapping Point',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'camera',
                'name' => 'b1f_nearest_existing_electrical_facilities',
                'label' => 'B1.f Photo of nearest Existing Electrical Facilities (Post, Transformer, Secondary/Primary Line)',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_divider_going_to_proposed_site',
                'label' => 'Access to proposed site',
                'options' => [],
            ],
            [
                'type' => 'camera',
                'name' => 'going_to_proposed_site_approach',
                'label' => 'Approach photo (road / facilities toward site)',
                'options' => $camera(0, 1),
            ],
            [
                'type' => 'divider',
                'name' => 'tssr_divider_going_to_proposed_site_caption',
                'label' => 'Going to Proposed Site Location',
                'options' => [],
            ],
            [
                'type' => 'camera',
                'name' => 'going_to_proposed_site_location',
                'label' => 'Proposed site location / entrance photo',
                'options' => $camera(0, 1),
            ],
        ];
    }
}
