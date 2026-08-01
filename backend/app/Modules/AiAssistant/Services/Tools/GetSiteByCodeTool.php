<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteIndexService;

final class GetSiteByCodeTool implements AssistantToolInterface
{
    public function __construct(
        private readonly SiteIndexService $sites,
    ) {}

    public function name(): string
    {
        return 'get_site_by_code';
    }

    public function description(): string
    {
        return 'Look up a site by site_code (exact match preferred, then search).';
    }

    public function requiredModule(): ?string
    {
        return 'sites';
    }

    public function requiredPermissions(): array
    {
        return ['sites:view'];
    }

    public function argumentRules(): array
    {
        return [
            'site_code' => ['required', 'string', 'min:1', 'max:64'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $code = trim((string) $args['site_code']);

        // Prefer exact match within the tenant DB (tenant-isolated by connection).
        $exact = Site::query()->where('site_code', $code)->first();
        if ($exact instanceof Site) {
            $row = $this->summarize($exact);

            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['site' => $row, 'match' => 'exact'],
                summary: sprintf('Found site %s — %s (%s).', $row['site_code'], $row['name'], $row['status'] ?? 'unknown'),
                moduleKey: 'sites',
                relatedRoutes: ['/sites/'.$row['id']],
                rowCount: 1,
            );
        }

        // Fall back to SiteIndexService search (same path as UI list).
        $paginator = $this->sites->paginate(1, min($maxRows, 5), $code, 'site_code:asc');
        $payload = $this->sites->asPayload($paginator);
        $candidates = array_slice($payload['data'], 0, $maxRows);

        if ($candidates === []) {
            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['site' => null, 'candidates' => []],
                summary: sprintf('No site found for code "%s".', $code),
                moduleKey: 'sites',
                relatedRoutes: ['/sites'],
                rowCount: 0,
            );
        }

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'site' => $candidates[0],
                'match' => 'search',
                'candidates' => $candidates,
            ],
            summary: sprintf(
                'Closest match for "%s": %s — %s.',
                $code,
                $candidates[0]['site_code'] ?? '?',
                $candidates[0]['name'] ?? '?',
            ),
            moduleKey: 'sites',
            relatedRoutes: isset($candidates[0]['id']) ? ['/sites/'.$candidates[0]['id']] : ['/sites'],
            rowCount: count($candidates),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Site $site): array
    {
        return [
            'id' => (string) $site->id,
            'site_code' => $site->site_code,
            'name' => $site->name,
            'type' => $site->type,
            'status' => $site->status,
            'latitude' => $site->latitude,
            'longitude' => $site->longitude,
        ];
    }
}
