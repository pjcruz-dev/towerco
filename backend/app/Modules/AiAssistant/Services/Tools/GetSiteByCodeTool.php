<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;

final class GetSiteByCodeTool implements AssistantToolInterface
{
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
        return null;
    }

    public function requiredPermissions(): array
    {
        return [];
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
        $code = trim((string) $args['site_code']);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: ['site' => null, 'candidates' => []],
            summary: sprintf('No site found for code "%s".', $code),
            moduleKey: null,
            relatedRoutes: [],
            rowCount: 0,
        );
    }
}
