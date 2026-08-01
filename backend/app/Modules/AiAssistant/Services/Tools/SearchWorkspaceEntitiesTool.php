<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceSearchService;

final class SearchWorkspaceEntitiesTool implements AssistantToolInterface
{
    public function __construct(
        private readonly WorkspaceSearchService $search,
    ) {}

    public function name(): string
    {
        return 'search_workspace_entities';
    }

    public function description(): string
    {
        return 'RBAC-aware workspace entity search (sites, tickets, submissions, etc.).';
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function requiredPermissions(): array
    {
        return ['dashboard:view'];
    }

    public function argumentRules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $query = trim((string) $args['query']);
        $limitPerType = max(1, min(5, (int) ceil($maxRows / 3)));
        $results = $this->search->search($viewer, $query, $limitPerType);
        $slice = array_slice($results, 0, $maxRows);

        $rows = array_map(static function (array $hit): array {
            return [
                'module' => $hit['module'] ?? null,
                'entity_type' => $hit['entity_type'] ?? null,
                'id' => $hit['id'] ?? null,
                'title' => $hit['title'] ?? null,
                'subtitle' => $hit['subtitle'] ?? null,
                'href' => $hit['href'] ?? null,
            ];
        }, $slice);

        $routes = [];
        foreach ($rows as $row) {
            if (is_string($row['href'] ?? null) && $row['href'] !== '') {
                $routes[] = $row['href'];
            }
        }
        $routes = array_values(array_unique(array_slice($routes, 0, 8)));

        $count = count($rows);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'query' => $query,
                'results' => $rows,
                'returned' => $count,
            ],
            summary: $count === 0
                ? sprintf('No workspace results for "%s".', $query)
                : sprintf('Found %d workspace result(s) for "%s".', $count, $query),
            moduleKey: null,
            relatedRoutes: $routes,
            rowCount: $count,
        );
    }
}
