<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use Illuminate\Support\Str;

final class RolloutPhaseGateLabelBackfillService
{
    public function backfillProgram(RolloutProgram $program): int
    {
        $config = TenantRolloutPlaybookConfig::query()->first();
        if ($config === null) {
            return 0;
        }

        $templateKey = match ($program->project_type) {
            'rtb' => 'rtb',
            'colocation', 'colo' => 'colocation',
            default => 'bts',
        };

        $templates = $config->playbook_snapshot['timeline_templates'][$templateKey] ?? [];
        $gateByKey = collect($templates)
            ->filter(static fn ($phase) => isset($phase['phase_key'], $phase['gate']))
            ->mapWithKeys(static fn ($phase) => [(string) $phase['phase_key'] => (string) $phase['gate']])
            ->all();

        $updated = 0;

        foreach ($program->timelinePhases as $phase) {
            $label = $gateByKey[$phase->phase_key] ?? null;
            if ($label === null || $phase->gate_label === $label) {
                continue;
            }

            $phase->gate_label = $label;
            $phase->save();
            $updated++;
        }

        return $updated;
    }

    public function backfillAll(): int
    {
        $total = 0;

        RolloutProgram::query()
            ->where('status', '!=', 'batch')
            ->with('timelinePhases')
            ->chunkById(50, function ($programs) use (&$total): void {
                foreach ($programs as $program) {
                    $total += $this->backfillProgram($program);
                }
            });

        return $total;
    }
}
