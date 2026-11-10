<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Models\DocExtractTemplate;
use Illuminate\Database\Eloquent\Builder;

/**
 * Command-palette search for DocExtract files and templates.
 * Skips OCR text: it is large and matches too loosely for a jump list.
 */
final class DocExtractSearchService
{
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
    public function asWorkspaceResults(string $query, int $limit = 5): array
    {
        $search = trim($query);
        if (mb_strlen($search) < 2) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $like = '%'.addcslashes($search, '%_\\').'%';
        $results = [];

        $documents = DocExtractDocument::query()
            ->select(['id', 'batch_id', 'template_id', 'original_filename', 'status'])
            ->whereNull('purged_at')
            ->where(static function (Builder $inner) use ($like): void {
                $inner->where('original_filename', 'like', $like)
                    ->orWhere('field_values', 'like', $like)
                    ->orWhereHas('template', static function (Builder $template) use ($like): void {
                        $template->where('name', 'like', $like);
                    });
            })
            ->with(['template:id,name'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        foreach ($documents as $document) {
            $templateName = $document->template?->name;
            $results[] = [
                'module' => 'doc_extract',
                'entity_type' => 'document',
                'id' => (string) $document->id,
                'title' => (string) $document->original_filename,
                'subtitle' => is_string($templateName) && $templateName !== '' ? $templateName : null,
                'status' => $document->status,
                'href' => '/doc-extract/batches/'.(string) $document->batch_id,
            ];
        }

        $remaining = $limit - count($results);
        if ($remaining <= 0) {
            return $results;
        }

        $templates = DocExtractTemplate::query()
            ->select(['id', 'name', 'status'])
            ->where(static function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderBy('name')
            ->limit($remaining)
            ->get();

        foreach ($templates as $template) {
            $results[] = [
                'module' => 'doc_extract',
                'entity_type' => 'template',
                'id' => (string) $template->id,
                'title' => (string) $template->name,
                'subtitle' => 'Template',
                'status' => $template->status,
                'href' => '/doc-extract/templates',
            ];
        }

        return $results;
    }
}
