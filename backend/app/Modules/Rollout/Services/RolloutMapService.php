<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;

final class RolloutMapService
{
    /**
     * @return array<string, mixed>
     */
    public function geoJson(RolloutProgram $program): array
    {
        $program->load(['candidates', 'site']);

        $features = [];

        if ($program->site !== null
            && $program->site->latitude !== null
            && $program->site->longitude !== null) {
            $features[] = $this->pointFeature(
                (float) $program->site->longitude,
                (float) $program->site->latitude,
                [
                    'pin_type' => 'rollout_site',
                    'id' => (string) $program->site->id,
                    'label' => $program->site->name,
                    'rollout_id' => (string) $program->id,
                    'rollout_ref' => $program->rollout_ref,
                    'status' => $program->status,
                ],
            );
        }

        foreach ($program->candidates as $candidate) {
            if ($candidate->latitude === null || $candidate->longitude === null) {
                continue;
            }

            $features[] = $this->pointFeature(
                (float) $candidate->longitude,
                (float) $candidate->latitude,
                [
                    'pin_type' => 'candidate',
                    'id' => (string) $candidate->id,
                    'label' => $candidate->label ?? ('Candidate '.$candidate->candidate_number),
                    'candidate_number' => $candidate->candidate_number,
                    'status' => $candidate->status,
                    'rollout_id' => (string) $program->id,
                    'rollout_ref' => $program->rollout_ref,
                ],
            );
        }

        return [
            'type' => 'FeatureCollection',
            'properties' => [
                'rollout_id' => (string) $program->id,
                'rollout_ref' => $program->rollout_ref,
                'status' => $program->status,
            ],
            'features' => $features,
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function pointFeature(float $lng, float $lat, array $properties): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => $properties,
        ];
    }
}
