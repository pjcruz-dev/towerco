<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteProfitabilityRecord;

final class SiteProfitabilityService
{
    /** @var list<string> */
    private const BUCKETS = [
        'saq',
        'engineering',
        'permitting',
        'cme',
        'tower_material',
        'dc_plant',
        'power',
    ];

    /** @var array<string, list<string>> */
    private const DISCIPLINE_BUCKETS = [
        'saq' => ['saq', 'permitting'],
        'cme' => ['cme', 'tower_material', 'dc_plant'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function showForUser(RolloutProgram $program, TenantUser $user): array
    {
        $record = $program->profitability ?? SiteProfitabilityRecord::query()
            ->where('rollout_program_id', $program->id)
            ->first();

        if ($record === null) {
            return ['rollout_program_id' => $program->id, 'baseline' => [], 'actual' => []];
        }

        if ($user->can('project_one:finance:view') || $user->can('tenant:manage')) {
            return $this->fullPayload($record);
        }

        if ($user->can('project_one:finance:view_discipline')) {
            return $this->disciplinePayload($record, $user);
        }

        return [
            'rollout_program_id' => $program->id,
            'profitability_status' => $record->profitability_status,
            'access' => 'summary_only',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(RolloutProgram $program, TenantUser $user, array $input): SiteProfitabilityRecord
    {
        abort_unless(
            $user->can('project_one:finance:edit') || $user->can('tenant:manage'),
            403,
        );

        $record = SiteProfitabilityRecord::query()->firstOrCreate(['rollout_program_id' => $program->id]);

        if (array_key_exists('baseline', $input) && is_array($input['baseline'])) {
            $record->baseline = array_merge($record->baseline ?? [], $input['baseline']);
        }
        if (array_key_exists('actual', $input) && is_array($input['actual'])) {
            $record->actual = array_merge($record->actual ?? [], $input['actual']);
        }
        foreach (['vo_cost_cumulative', 'ld_accrued_php', 'variance_category', 'profitability_status', 'anchor_tenant_lease_fee_php'] as $field) {
            if (array_key_exists($field, $input)) {
                $record->{$field} = $input[$field];
            }
        }
        $record->updated_by_id = $user->id;
        $record->save();

        return $record->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function fullPayload(SiteProfitabilityRecord $record): array
    {
        $baselineTotal = $this->sumBuckets($record->baseline ?? []);
        $actualTotal = $this->sumBuckets($record->actual ?? []);

        return [
            'rollout_program_id' => $record->rollout_program_id,
            'baseline' => $record->baseline,
            'actual' => $record->actual,
            'baseline_total' => $baselineTotal,
            'actual_total' => $actualTotal,
            'variance_php' => $actualTotal - $baselineTotal,
            'vo_cost_cumulative' => $record->vo_cost_cumulative,
            'ld_accrued_php' => $record->ld_accrued_php,
            'variance_category' => $record->variance_category,
            'profitability_status' => $record->profitability_status,
            'anchor_tenant_lease_fee_php' => $record->anchor_tenant_lease_fee_php,
            'access' => 'full',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disciplinePayload(SiteProfitabilityRecord $record, TenantUser $user): array
    {
        $keys = $user->can('project_one:saq:manage')
            ? self::DISCIPLINE_BUCKETS['saq']
            : ($user->can('project_one:cme:manage') ? self::DISCIPLINE_BUCKETS['cme'] : []);

        $baseline = array_intersect_key($record->baseline ?? [], array_flip($keys));
        $actual = array_intersect_key($record->actual ?? [], array_flip($keys));

        return [
            'rollout_program_id' => $record->rollout_program_id,
            'baseline' => $baseline,
            'actual' => $actual,
            'profitability_status' => $record->profitability_status,
            'access' => 'discipline',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sumBuckets(array $data): float
    {
        $sum = 0.0;
        foreach (self::BUCKETS as $bucket) {
            $sum += (float) ($data[$bucket] ?? 0);
        }
        $sum += (float) ($data['vo_cost_cumulative'] ?? 0);

        return $sum;
    }
}
