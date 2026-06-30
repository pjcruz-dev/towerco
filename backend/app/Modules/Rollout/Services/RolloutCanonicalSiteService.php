<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Models\Tenant;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Sites\Models\Site;

final class RolloutCanonicalSiteService
{
    public function __construct(
        private readonly TcoSiteIdGenerator $tcoSiteIdGenerator,
    ) {}

    public function ensureForProgram(RolloutProgram $program): ?Site
    {
        if ($program->status === 'batch') {
            return null;
        }

        $linkedSite = $this->resolveLinkedProjectSite($program);
        if ($linkedSite !== null) {
            $this->linkProgramToSite($program, $linkedSite);
            $this->issueTcoSiteIdIfMissing($program);

            return $linkedSite->fresh();
        }

        $program = $this->issueTcoSiteIdIfMissing($program);
        if ($program->tco_site_id === null) {
            return null;
        }

        $site = $this->upsertSiteFromProgram($program);
        $this->linkProgramToSite($program, $site);

        return $site;
    }

    public function enrichFromCandidate(SiteCandidate $candidate, RolloutProgram $program): Site
    {
        $site = $this->ensureForProgram($program);
        if ($site === null) {
            throw new \RuntimeException('Canonical site could not be provisioned for rollout.');
        }

        $site->fill([
            'name' => $candidate->label ?? $program->search_ring_name ?? $program->rollout_ref,
            'latitude' => $candidate->latitude,
            'longitude' => $candidate->longitude,
            'status' => 'under_construction',
        ]);
        $site->save();

        return $site->fresh();
    }

    public function issueTcoSiteId(RolloutProgram $program, string $tenantSequencePrefix): RolloutProgram
    {
        if ($program->tco_site_id !== null) {
            return $program;
        }

        $program->tco_site_id = $this->tcoSiteIdGenerator->generate(
            (string) ($program->region ?? 'ncr'),
            (string) $program->mno,
            $tenantSequencePrefix,
        );
        $program->save();

        return $program->fresh();
    }

    private function resolveLinkedProjectSite(RolloutProgram $program): ?Site
    {
        if ($program->project_id === null) {
            return null;
        }

        $project = Project::query()->find($program->project_id);
        if ($project?->site_id === null) {
            return null;
        }

        return Site::query()->find($project->site_id);
    }

    private function issueTcoSiteIdIfMissing(RolloutProgram $program): RolloutProgram
    {
        if ($program->tco_site_id !== null) {
            return $program;
        }

        return $this->issueTcoSiteId($program, $this->resolveTcoPrefix());
    }

    private function upsertSiteFromProgram(RolloutProgram $program): Site
    {
        if ($program->site_id !== null) {
            $existing = Site::query()->find($program->site_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        return Site::query()->updateOrCreate(
            ['site_code' => (string) $program->tco_site_id],
            [
                'name' => $program->search_ring_name ?? $program->rollout_ref,
                'latitude' => null,
                'longitude' => null,
                'type' => $this->resolveSiteType($program),
                'status' => 'site_acquisition',
            ],
        );
    }

    private function linkProgramToSite(RolloutProgram $program, Site $site): void
    {
        if ($program->site_id === $site->id) {
            return;
        }

        $program->site_id = $site->id;
        $program->save();
    }

    private function resolveSiteType(RolloutProgram $program): string
    {
        return match ($program->project_type) {
            'colocation', 'colo' => 'rooftop',
            default => 'macro',
        };
    }

    private function resolveTcoPrefix(): string
    {
        $tenantId = tenant('id');
        if ($tenantId === null || $tenantId === '') {
            return 'A';
        }

        /** @var Tenant|null $central */
        $central = Tenant::query()->find((string) $tenantId);

        return strtoupper(substr((string) ($central?->tco_sequence_prefix ?? 'A'), 0, 1));
    }
}
