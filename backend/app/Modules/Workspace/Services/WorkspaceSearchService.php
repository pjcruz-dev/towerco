<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Modules\AdminOne\Services\TenantUserIndexService;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Services\AssetIndexService;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\FiberOne\Models\FiberRoute;
use App\Modules\FiberOne\Services\FiberRouteIndexService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Services\ProjectIndexService;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramIndexService;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteIndexService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Ticketing\Services\TicketingTicketService;
use App\Modules\TowerOne\Models\Tower;
use App\Modules\TowerOne\Services\TowerIndexService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WorkspaceSearchService
{
    private const MIN_QUERY_LENGTH = 2;

    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly EApprovalSubmissionService $eApprovalSubmissions,
        private readonly TicketingTicketService $ticketingTickets,
        private readonly SiteIndexService $sites,
        private readonly TowerIndexService $towers,
        private readonly AssetIndexService $assets,
        private readonly FiberRouteIndexService $fiberRoutes,
        private readonly ProjectIndexService $projects,
        private readonly RolloutProgramIndexService $rollouts,
        private readonly TenantUserIndexService $users,
    ) {}

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    public function search(TenantUser $viewer, string $query, int $limitPerType = 5): array
    {
        $search = trim($query);
        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $limitPerType = max(1, min(10, $limitPerType));
        $enabled = $this->enabledModules->resolveForCurrentTenant();
        $results = [];

        if ($this->canSearchModule($enabled, $viewer, 'e_approval', 'e_approval:submissions:view')) {
            $results = array_merge($results, $this->searchEApprovalSubmissions($viewer, $search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'ticketing', 'ticketing:view')) {
            $results = array_merge($results, $this->searchTicketingTickets($viewer, $search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'sites', 'sites:view')) {
            $results = array_merge($results, $this->searchSites($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'tower_one', 'tower_one:view')) {
            $results = array_merge($results, $this->searchTowers($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'asset_one', 'asset_one:view')) {
            $results = array_merge($results, $this->searchAssets($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'fiber_one', 'fiber_one:view')) {
            $results = array_merge($results, $this->searchFiberRoutes($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'project_one', 'project_one:view')) {
            $results = array_merge($results, $this->searchProjects($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'project_one', 'project_one:rollout:view')) {
            $results = array_merge($results, $this->searchRollouts($search, $limitPerType));
        }

        if ($this->canSearchModule($enabled, $viewer, 'team_access', 'user:manage')) {
            $results = array_merge($results, $this->searchUsers($search, $limitPerType));
        }

        return $results;
    }

    /**
     * @param  list<string>  $enabled
     */
    private function canSearchModule(array $enabled, TenantUser $viewer, string $module, string $permission): bool
    {
        if (! in_array($module, $enabled, true)) {
            return false;
        }

        return $viewer->can($permission);
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchEApprovalSubmissions(TenantUser $viewer, string $search, int $limit): array
    {
        $canViewAll = $viewer->can('e_approval:forms:manage');
        $paginator = $this->eApprovalSubmissions->paginate($viewer, 1, $limit, $search, null, $canViewAll);

        return $this->mapPaginator($paginator, static function (EApprovalSubmission $submission): array {
            $submission->loadMissing(['form:id,name']);

            return [
                'module' => 'e_approval',
                'entity_type' => 'submission',
                'id' => (string) $submission->id,
                'title' => (string) $submission->document_no,
                'subtitle' => $submission->form?->name,
                'status' => $submission->status,
                'href' => '/e-approval/submissions/'.$submission->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchTicketingTickets(TenantUser $viewer, string $search, int $limit): array
    {
        $paginator = $this->ticketingTickets->paginate($viewer, [
            'page' => 1,
            'per_page' => $limit,
            'search' => $search,
        ]);

        return $this->mapPaginator($paginator, function ($ticket): array {
            $row = $this->ticketingTickets->asListRow($ticket);

            return [
                'module' => 'ticketing',
                'entity_type' => 'ticket',
                'id' => (string) $row['id'],
                'title' => trim((string) ($row['ticket_number'] ?? '').' · '.(string) ($row['title'] ?? '')),
                'subtitle' => isset($row['assignee']['name']) ? 'Assignee: '.$row['assignee']['name'] : null,
                'status' => isset($row['status']) ? (string) $row['status'] : null,
                'href' => '/ticketing/tickets/'.$row['id'],
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchSites(string $search, int $limit): array
    {
        $paginator = $this->sites->paginate(1, $limit, $search);

        return $this->mapPaginator($paginator, static function (Site $site): array {
            return [
                'module' => 'sites',
                'entity_type' => 'site',
                'id' => (string) $site->id,
                'title' => trim($site->site_code.' · '.$site->name),
                'subtitle' => $site->type,
                'status' => $site->status,
                'href' => '/sites/'.$site->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchTowers(string $search, int $limit): array
    {
        $paginator = $this->towers->paginate(1, $limit, $search);

        return $this->mapPaginator($paginator, static function (Tower $tower): array {
            $tower->loadMissing(['site:id,site_code,name']);
            $siteLabel = $tower->site
                ? trim($tower->site->site_code.' · '.$tower->site->name)
                : 'Unlinked site';

            return [
                'module' => 'tower_one',
                'entity_type' => 'tower',
                'id' => (string) $tower->id,
                'title' => ucfirst((string) $tower->tower_type).' tower',
                'subtitle' => $siteLabel,
                'status' => $tower->status,
                'href' => '/tower-one/towers/'.$tower->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchAssets(string $search, int $limit): array
    {
        $paginator = $this->assets->paginate(1, $limit, $search);

        return $this->mapPaginator($paginator, static function (Asset $asset): array {
            return [
                'module' => 'asset_one',
                'entity_type' => 'asset',
                'id' => (string) $asset->id,
                'title' => trim($asset->asset_code.' · '.$asset->name),
                'subtitle' => $asset->category,
                'status' => $asset->status,
                'href' => '/asset-one/assets/'.$asset->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchFiberRoutes(string $search, int $limit): array
    {
        $paginator = $this->fiberRoutes->paginate(1, $limit, $search);

        return $this->mapPaginator($paginator, static function (FiberRoute $route): array {
            $route->loadMissing(['fromSite:id,site_code,name', 'toSite:id,site_code,name']);
            $from = $route->fromSite?->site_code ?? '—';
            $to = $route->toSite?->site_code ?? '—';

            return [
                'module' => 'fiber_one',
                'entity_type' => 'fiber_route',
                'id' => (string) $route->id,
                'title' => (string) $route->name,
                'subtitle' => $from.' → '.$to,
                'status' => $route->status,
                'href' => '/fiber-one/routes?search='.rawurlencode((string) $route->name),
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchProjects(string $search, int $limit): array
    {
        $paginator = $this->projects->paginate(1, $limit, $search);

        return $this->mapPaginator($paginator, static function (Project $project): array {
            $project->loadMissing(['site:id,site_code,name']);

            return [
                'module' => 'project_one',
                'entity_type' => 'project',
                'id' => (string) $project->id,
                'title' => (string) $project->name,
                'subtitle' => $project->site
                    ? trim($project->site->site_code.' · '.$project->site->name)
                    : null,
                'status' => $project->status,
                'href' => '/project-one/projects/'.$project->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchRollouts(string $search, int $limit): array
    {
        $paginator = $this->rollouts->paginate(1, $limit, ['search' => $search]);

        return $this->mapPaginator($paginator, static function (RolloutProgram $rollout): array {
            return [
                'module' => 'project_one',
                'entity_type' => 'rollout',
                'id' => (string) $rollout->id,
                'title' => (string) $rollout->rollout_ref,
                'subtitle' => $rollout->search_ring_name,
                'status' => $rollout->status,
                'href' => '/project-one/rollouts/'.$rollout->id,
            ];
        });
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchUsers(string $search, int $limit): array
    {
        $paginator = $this->users->paginate(1, $limit, $search, null);

        return $this->mapPaginator($paginator, static function (TenantUser $user): array {
            return [
                'module' => 'team_access',
                'entity_type' => 'user',
                'id' => (string) $user->id,
                'title' => (string) $user->name,
                'subtitle' => (string) $user->email,
                'status' => $user->isActive() ? 'active' : 'inactive',
                'href' => '/users?search='.rawurlencode((string) $user->email),
            ];
        });
    }

    /**
     * @template TModel of object
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }  $mapper
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function mapPaginator(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return $paginator->getCollection()
            ->map(static fn ($model) => $mapper($model))
            ->values()
            ->all();
    }
}
