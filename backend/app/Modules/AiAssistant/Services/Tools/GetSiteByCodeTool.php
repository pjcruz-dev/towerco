<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Documents\Models\Site;
use App\Modules\Identity\Models\TenantUser;

final class GetSiteByCodeTool implements AssistantToolInterface
{
    public function name(): string
    {
        return 'get_site_by_code';
    }

    public function description(): string
    {
        return 'Look up a site by its exact site code.';
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function requiredPermissions(): array
    {
        return ['ai_assistant:tools:use'];
    }

    public function argumentRules(): array
    {
        return [
            'site_code' => ['required', 'string', 'min:1', 'max:64'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        unset($viewer, $maxRows);
        $code = strtoupper(trim((string) $args['site_code']));
        $site = Site::query()
            ->whereRaw('UPPER(site_code) = ?', [$code])
            ->first();

        if (! $site instanceof Site) {
            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['site' => null, 'candidates' => []],
                summary: sprintf('No site found for code "%s".', $code),
                moduleKey: 'documents',
                relatedRoutes: ['/sites'],
                rowCount: 0,
            );
        }

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'site' => [
                    'site_code' => $site->site_code,
                    'name' => $site->name,
                    'full_address' => $site->full_address,
                    'type' => $site->type,
                    'status' => $site->status,
                ],
                'candidates' => [],
            ],
            summary: sprintf('Site %s: %s.', $site->site_code, $site->name),
            moduleKey: 'documents',
            relatedRoutes: ['/sites'],
            rowCount: 1,
        );
    }
}
