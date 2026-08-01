<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Documents\Services\DocumentExpiringService;
use App\Modules\Identity\Models\TenantUser;

final class ListExpiringDocumentsTool implements AssistantToolInterface
{
    public function __construct(
        private readonly DocumentExpiringService $expiring,
    ) {}

    public function name(): string
    {
        return 'list_expiring_documents';
    }

    public function description(): string
    {
        return 'List documents (leases, permits, contracts) expiring within N days.';
    }

    public function requiredModule(): ?string
    {
        return 'documents';
    }

    public function requiredPermissions(): array
    {
        return ['documents:view'];
    }

    public function argumentRules(): array
    {
        return [
            'within_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $days = max(1, min(365, (int) ($args['within_days'] ?? 90)));
        $all = $this->expiring->list($days);
        $slice = array_slice($all, 0, $maxRows);

        $rows = array_map(static function (array $doc): array {
            return [
                'id' => $doc['id'] ?? null,
                'title' => $doc['title'] ?? null,
                'expires_at' => $doc['expires_at'] ?? null,
                'status' => $doc['status'] ?? null,
                'site_code' => $doc['site']['site_code'] ?? null,
                'site_name' => $doc['site']['name'] ?? null,
            ];
        }, $slice);

        $count = count($rows);
        $total = count($all);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'within_days' => $days,
                'documents' => $rows,
                'total' => $total,
                'returned' => $count,
                'summary_counts' => $this->expiring->summaryCounts(),
            ],
            summary: $total === 0
                ? sprintf('No documents expiring within %d days.', $days)
                : sprintf('%d document(s) expiring within %d days (showing %d).', $total, $days, $count),
            moduleKey: 'documents',
            relatedRoutes: ['/documents?filter=expiring'],
            rowCount: $count,
        );
    }
}
