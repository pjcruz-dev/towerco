<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\DocumentSiteWorkspace;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Builder;

final class DocumentRolloutLinkOptionsService
{
    /**
     * Rollout programs that can be linked to a site binder workspace.
     *
     * @return list<array{id: string, rollout_ref: string, status: string, site_match: bool}>
     */
    public function forSite(Site $site, string $search = ''): array
    {
        $search = trim($search);
        $linkedId = DocumentSiteWorkspace::query()
            ->where('site_id', $site->id)
            ->value('rollout_program_id');

        $siteMatched = RolloutProgram::query()
            ->where('status', '!=', 'batch')
            ->where(static function (Builder $builder) use ($site): void {
                $builder->where('site_id', $site->id);

                if ($site->site_code !== '') {
                    $builder->orWhere('tco_site_id', $site->site_code);
                }
            })
            ->orderBy('rollout_ref')
            ->get(['id', 'rollout_ref', 'status', 'site_id']);

        $options = [];
        $seen = [];

        foreach ($siteMatched as $program) {
            $id = (string) $program->id;
            $seen[$id] = true;
            $options[] = $this->mapOption($program, true);
        }

        if ($linkedId !== null && ! isset($seen[(string) $linkedId])) {
            $linked = RolloutProgram::query()->find($linkedId);
            if ($linked instanceof RolloutProgram) {
                $options[] = $this->mapOption($linked, (string) $linked->site_id === (string) $site->id);
            }
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $searchMatches = RolloutProgram::query()
                ->where('status', '!=', 'batch')
                ->where(static function (Builder $builder) use ($like): void {
                    $builder->where('rollout_ref', 'like', $like)
                        ->orWhere('tco_site_id', 'like', $like)
                        ->orWhere('search_ring_name', 'like', $like);
                })
                ->orderBy('rollout_ref')
                ->limit(25)
                ->get(['id', 'rollout_ref', 'status', 'site_id']);

            foreach ($searchMatches as $program) {
                $id = (string) $program->id;
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $options[] = $this->mapOption($program, (string) $program->site_id === (string) $site->id);
            }
        }

        usort($options, static function (array $left, array $right): int {
            if ($left['site_match'] !== $right['site_match']) {
                return $left['site_match'] ? -1 : 1;
            }

            return strcmp($left['rollout_ref'], $right['rollout_ref']);
        });

        return $options;
    }

    /**
     * @return array{id: string, rollout_ref: string, status: string, site_match: bool}
     */
    private function mapOption(RolloutProgram $program, bool $siteMatch): array
    {
        return [
            'id' => (string) $program->id,
            'rollout_ref' => (string) $program->rollout_ref,
            'status' => (string) $program->status,
            'site_match' => $siteMatch,
        ];
    }
}
