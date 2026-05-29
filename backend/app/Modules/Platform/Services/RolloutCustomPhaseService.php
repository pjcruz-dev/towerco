<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\RolloutCustomPhase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RolloutCustomPhaseService
{
    /**
     * @return list<RolloutCustomPhase>
     */
    public function list(?string $templateKey = null, bool $activeOnly = true): array
    {
        $query = RolloutCustomPhase::query()->orderBy('label');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($templateKey !== null && $templateKey !== '' && $templateKey !== 'all') {
            $query->whereJsonContains('applicable_templates', $templateKey);
        }

        return $query->get()->all();
    }

    public function find(string $id): RolloutCustomPhase
    {
        /** @var RolloutCustomPhase $phase */
        $phase = RolloutCustomPhase::query()->findOrFail($id);

        return $phase;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): RolloutCustomPhase
    {
        $normalized = $this->normalizeInput($input);

        if (RolloutCustomPhase::query()->where('phase_key', $normalized['phase_key'])->exists()) {
            throw ValidationException::withMessages([
                'phase_key' => [__('A custom phase with this key already exists.')],
            ]);
        }

        /** @var RolloutCustomPhase $phase */
        $phase = RolloutCustomPhase::query()->create($normalized);

        return $phase;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(RolloutCustomPhase $phase, array $input): RolloutCustomPhase
    {
        $normalized = $this->normalizeInput($input, $phase);

        if (isset($normalized['phase_key']) && $normalized['phase_key'] !== $phase->phase_key) {
            if (RolloutCustomPhase::query()->where('phase_key', $normalized['phase_key'])->where('id', '!=', $phase->id)->exists()) {
                throw ValidationException::withMessages([
                    'phase_key' => [__('A custom phase with this key already exists.')],
                ]);
            }
        }

        $phase->fill($normalized);
        $phase->save();

        return $phase->fresh() ?? $phase;
    }

    public function deactivate(RolloutCustomPhase $phase): RolloutCustomPhase
    {
        $phase->is_active = false;
        $phase->save();

        return $phase->fresh() ?? $phase;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RolloutCustomPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'phase_key' => $phase->phase_key,
            'label' => $phase->label,
            'description' => $phase->description,
            'owner_role' => $phase->owner_role,
            'default_anchor' => $phase->default_anchor,
            'default_working_day_start' => $phase->default_working_day_start,
            'default_working_day_end' => $phase->default_working_day_end,
            'default_gate' => $phase->default_gate,
            'counts_toward_sla' => $phase->counts_toward_sla,
            'applicable_templates' => $phase->applicable_templates ?? [],
            'is_active' => $phase->is_active,
            'updated_at' => $phase->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build a timeline template row from a catalog entry.
     *
     * @return array<string, mixed>
     */
    public function toTimelinePhase(RolloutCustomPhase $phase, ?array $overrides = null): array
    {
        $row = [
            'phase_key' => $phase->phase_key,
            'label' => $phase->label,
            'owner_role' => $phase->owner_role,
            'anchor' => $phase->default_anchor,
            'working_day_start' => $phase->default_working_day_start,
            'working_day_end' => $phase->default_working_day_end,
            'gate' => $phase->default_gate,
            'counts_toward_sla' => $phase->counts_toward_sla,
            'is_custom' => true,
            'catalog_phase_id' => $phase->id,
        ];

        if ($overrides !== null) {
            foreach (['label', 'owner_role', 'anchor', 'working_day_start', 'working_day_end', 'gate', 'counts_toward_sla'] as $key) {
                if (array_key_exists($key, $overrides)) {
                    $row[$key] = $overrides[$key];
                }
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input, ?RolloutCustomPhase $existing = null): array
    {
        $normalized = [];

        if (array_key_exists('phase_key', $input)) {
            $slug = Str::slug((string) $input['phase_key'], '_');
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'phase_key' => [__('Phase key is required.')],
                ]);
            }
            $normalized['phase_key'] = $slug;
        } elseif ($existing === null) {
            throw ValidationException::withMessages([
                'phase_key' => [__('Phase key is required.')],
            ]);
        }

        if (array_key_exists('label', $input)) {
            $normalized['label'] = trim((string) $input['label']);
        }

        if (array_key_exists('description', $input)) {
            $normalized['description'] = $input['description'] !== null ? trim((string) $input['description']) : null;
        }

        if (array_key_exists('owner_role', $input)) {
            $normalized['owner_role'] = $input['owner_role'] !== null && $input['owner_role'] !== ''
                ? (string) $input['owner_role']
                : null;
        }

        if (array_key_exists('default_anchor', $input)) {
            $anchor = (string) $input['default_anchor'];
            if (! in_array($anchor, [RolloutCustomPhase::ANCHOR_ENDORSEMENT, RolloutCustomPhase::ANCHOR_TSSR_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'default_anchor' => [__('Invalid anchor.')],
                ]);
            }
            $normalized['default_anchor'] = $anchor;
        }

        foreach (['default_working_day_start', 'default_working_day_end'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = max(0, (int) $input[$key]);
            }
        }

        if (array_key_exists('default_gate', $input)) {
            $normalized['default_gate'] = $input['default_gate'] !== null && $input['default_gate'] !== ''
                ? (string) $input['default_gate']
                : null;
        }

        if (array_key_exists('counts_toward_sla', $input)) {
            $normalized['counts_toward_sla'] = (bool) $input['counts_toward_sla'];
        }

        if (array_key_exists('applicable_templates', $input)) {
            $templates = array_values(array_unique(array_filter(
                array_map(static fn ($t) => (string) $t, (array) $input['applicable_templates']),
                static fn (string $t): bool => in_array($t, RolloutCustomPhase::TEMPLATE_KEYS, true),
            )));

            if ($templates === []) {
                throw ValidationException::withMessages([
                    'applicable_templates' => [__('At least one applicable template is required.')],
                ]);
            }

            $normalized['applicable_templates'] = $templates;
        } elseif ($existing === null) {
            throw ValidationException::withMessages([
                'applicable_templates' => [__('At least one applicable template is required.')],
            ]);
        }

        if (array_key_exists('is_active', $input)) {
            $normalized['is_active'] = (bool) $input['is_active'];
        }

        if (isset($normalized['default_working_day_start'], $normalized['default_working_day_end'])
            && $normalized['default_working_day_end'] < $normalized['default_working_day_start']) {
            throw ValidationException::withMessages([
                'default_working_day_end' => [__('Working day end must be greater than or equal to start.')],
            ]);
        }

        return $normalized;
    }
}
