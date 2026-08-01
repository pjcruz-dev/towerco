<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Identity\Models\TenantUser;

final class GetControlledDocumentByCodeTool implements AssistantToolInterface
{
    public function __construct(
        private readonly ControlledDocumentAccessService $access,
    ) {}

    public function name(): string
    {
        return 'get_controlled_document_by_code';
    }

    public function description(): string
    {
        return 'Look up a controlled document in the register by document_code.';
    }

    public function requiredModule(): ?string
    {
        return 'document_register';
    }

    public function requiredPermissions(): array
    {
        return ['documents:controlled:view'];
    }

    public function argumentRules(): array
    {
        return [
            'document_code' => ['required', 'string', 'min:2', 'max:64'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        $code = trim((string) $args['document_code']);
        if ($code === '') {
            return new ToolResult(
                tool: $this->name(),
                ok: false,
                data: [],
                summary: 'Document code is required.',
                moduleKey: 'document_register',
                relatedRoutes: ['/documents/controlled'],
                rowCount: 0,
                error: 'missing document_code',
            );
        }

        $exactQuery = ControlledDocument::query()->where('document_code', $code);
        $this->access->applyRegistryScope($exactQuery, $viewer);
        $exact = $exactQuery->first();

        if ($exact instanceof ControlledDocument && $this->access->canViewDocument($viewer, $exact)) {
            $row = $this->summarize($exact);

            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['document' => $row, 'match' => 'exact'],
                summary: $this->summaryFor($row),
                moduleKey: 'document_register',
                relatedRoutes: [$row['href']],
                rowCount: 1,
            );
        }

        $needle = '%'.addcslashes($code, '%_\\').'%';
        $searchQuery = ControlledDocument::query()
            ->where(static function ($inner) use ($needle): void {
                $inner->where('document_code', 'like', $needle)
                    ->orWhere('title', 'like', $needle);
            })
            ->orderBy('document_code');
        $this->access->applyRegistryScope($searchQuery, $viewer);

        $candidates = $searchQuery
            ->limit(max(1, min($maxRows, 5)))
            ->get()
            ->filter(fn (ControlledDocument $document): bool => $this->access->canViewDocument($viewer, $document))
            ->values();

        if ($candidates->isEmpty()) {
            return new ToolResult(
                tool: $this->name(),
                ok: true,
                data: ['document' => null, 'candidates' => []],
                summary: sprintf('No controlled document found for code "%s".', $code),
                moduleKey: 'document_register',
                relatedRoutes: ['/documents/controlled'],
                rowCount: 0,
            );
        }

        $best = $candidates->first();
        $row = $this->summarize($best);

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: [
                'document' => $row,
                'match' => 'search',
                'candidates' => $candidates
                    ->map(fn (ControlledDocument $document): array => $this->summarize($document))
                    ->all(),
            ],
            summary: $this->summaryFor($row),
            moduleKey: 'document_register',
            relatedRoutes: [$row['href']],
            rowCount: $candidates->count(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(ControlledDocument $document): array
    {
        return [
            'id' => (string) $document->id,
            'document_code' => (string) $document->document_code,
            'title' => (string) $document->title,
            'department' => $document->department,
            'status' => $document->status,
            'current_revision' => $document->current_revision,
            'effective_date' => $document->effective_date?->toDateString(),
            'next_review_date' => $document->next_review_date?->toDateString(),
            'href' => '/documents/controlled?document='.(string) $document->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function summaryFor(array $row): string
    {
        $revision = is_numeric($row['current_revision'] ?? null)
            ? (int) $row['current_revision']
            : null;

        $parts = [
            sprintf(
                'Document %s — %s',
                $row['document_code'] ?? '?',
                $row['title'] ?? 'Untitled',
            ),
            sprintf('Status: %s', $row['status'] ?? 'unknown'),
        ];

        if ($revision !== null) {
            $parts[] = sprintf('Current revision: %d', $revision);
        }

        if (is_string($row['department'] ?? null) && $row['department'] !== '') {
            $parts[] = 'Department: '.$row['department'];
        }

        return implode('. ', $parts).'.';
    }
}
