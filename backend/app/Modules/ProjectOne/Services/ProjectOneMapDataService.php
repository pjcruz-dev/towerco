<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Illuminate\Support\Facades\Schema;

final class ProjectOneMapDataService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function buildPins(): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');

        return TenantScopedCache::remember(
            "project_one:map_pins:{$tenantId}",
            45,
            fn (): array => $this->buildPinsUncached(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPinsUncached(): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return $this->sitePinsOnly();
        }

        $pins = $this->sitePinsOnly();

        $activeRollouts = RolloutProgram::query()
            ->with(['site:id,name,latitude,longitude'])
            ->whereNotIn('status', ['completed', 'cancelled', 'batch'])
            ->whereNull('parent_rollout_id')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'rollout_ref', 'status', 'site_id']);

        $rolloutIds = $activeRollouts->pluck('id')->all();
        $candidatesByRollout = $rolloutIds === []
            ? collect()
            : SiteCandidate::query()
                ->whereIn('rollout_program_id', $rolloutIds)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('candidate_number')
                ->get(['id', 'rollout_program_id', 'candidate_number', 'status', 'label', 'latitude', 'longitude'])
                ->groupBy('rollout_program_id');

        foreach ($activeRollouts as $rollout) {
            if ($rollout->site !== null
                && $rollout->site->latitude !== null
                && $rollout->site->longitude !== null) {
                $pins[] = [
                    'type' => 'rollout_site',
                    'id' => (string) $rollout->site->id,
                    'lat' => (float) $rollout->site->latitude,
                    'lng' => (float) $rollout->site->longitude,
                    'label' => $rollout->site->name,
                    'status' => $rollout->status,
                    'rollout_id' => (string) $rollout->id,
                    'rollout_ref' => $rollout->rollout_ref,
                ];
            }

            foreach ($candidatesByRollout->get($rollout->id, collect()) as $candidate) {
                $pins[] = $this->candidatePin($rollout, $candidate);
            }
        }

        return $pins;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sitePinsOnly(): array
    {
        if (! Schema::connection('tenant')->hasTable('sites')) {
            return [];
        }

        return Site::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(static fn (Site $site) => [
                'type' => 'site',
                'id' => (string) $site->id,
                'lat' => (float) $site->latitude,
                'lng' => (float) $site->longitude,
                'label' => $site->name,
                'status' => match ($site->status) {
                    'under_construction' => 'warning',
                    'decommissioned' => 'critical',
                    default => 'healthy',
                },
                'rollout_id' => null,
                'rollout_ref' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function candidatePin(RolloutProgram $rollout, SiteCandidate $candidate): array
    {
        return [
            'type' => 'candidate',
            'id' => (string) $candidate->id,
            'lat' => (float) $candidate->latitude,
            'lng' => (float) $candidate->longitude,
            'label' => $candidate->label ?? ('Candidate '.$candidate->candidate_number),
            'status' => $candidate->status,
            'rollout_id' => (string) $rollout->id,
            'rollout_ref' => $rollout->rollout_ref,
        ];
    }
}
