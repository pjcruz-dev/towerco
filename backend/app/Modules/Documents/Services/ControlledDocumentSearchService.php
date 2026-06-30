<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Identity\Models\TenantUser;

final class ControlledDocumentSearchService
{
    public function __construct(
        private readonly ControlledDocumentAccessService $access,
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
    public function asWorkspaceResults(TenantUser $viewer, string $query, int $limit = 5): array
    {
        $search = trim($query);
        if ($search === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $needle = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

        $builder = ControlledDocument::query()
            ->select(['id', 'document_code', 'title', 'department', 'status'])
            ->orderBy('document_code');

        $this->access->applyRegistryScope($builder, $viewer);
        $builder->where(static function ($inner) use ($needle): void {
            $inner->whereRaw('LOWER(document_code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(title) LIKE ?', [$needle]);
        });

        return $builder
            ->limit($limit)
            ->get()
            ->map(static function (ControlledDocument $document): array {
                $subtitleParts = array_filter([
                    $document->department,
                    $document->title !== $document->document_code ? $document->title : null,
                ]);

                return [
                    'module' => 'documents',
                    'entity_type' => 'controlled_document',
                    'id' => (string) $document->id,
                    'title' => (string) $document->document_code,
                    'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
                    'status' => $document->status,
                    'href' => '/documents/controlled?document='.(string) $document->id,
                ];
            })
            ->values()
            ->all();
    }
}
