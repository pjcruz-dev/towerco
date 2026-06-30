<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Collection;

final class DocumentSearchService
{
    /**
     * @return list<Document>
     */
    public function search(string $query, int $limit = 5): array
    {
        $search = trim($query);
        if ($search === '') {
            return [];
        }

        $like = '%'.addcslashes($search, '%_\\').'%';

        return Document::query()
            ->whereNull('deleted_at')
            ->where(static function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('original_filename', 'like', $like)
                    ->orWhereHas('site', static function ($siteQuery) use ($like): void {
                        $siteQuery->where('site_code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            })
            ->with(['site:id,site_code,name'])
            ->orderByDesc('last_touched_at')
            ->limit(max(1, min(10, $limit)))
            ->get()
            ->all();
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
    public function asWorkspaceResults(string $query, int $limit = 5): array
    {
        return Collection::make($this->search($query, $limit))
            ->map(static function (Document $document): array {
                $site = $document->site;

                return [
                    'module' => 'documents',
                    'entity_type' => 'document',
                    'id' => (string) $document->id,
                    'title' => $document->title,
                    'subtitle' => $site instanceof Site
                        ? trim($site->site_code.' · '.$site->name)
                        : null,
                    'status' => $document->status,
                    'href' => $site !== null
                        ? '/sites/'.$site->id.'?document='.$document->id
                        : '/documents?document='.$document->id,
                ];
            })
            ->values()
            ->all();
    }
}
