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
     *   status_label: string|null,
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
        $needles = $this->likeNeedles($search);

        $builder = ControlledDocument::query()
            ->select([
                'id',
                'document_code',
                'dcf_number',
                'title',
                'document_type',
                'department',
                'current_revision',
                'status',
            ])
            ->orderBy('document_code');

        $this->access->applyRegistryScope($builder, $viewer);
        $builder->where(static function ($inner) use ($needles): void {
            foreach ($needles as $needle) {
                // Rely on DB collation for case-insensitivity (avoids LOWER() full scans).
                $inner->orWhere('document_code', 'like', $needle)
                    ->orWhere('title', 'like', $needle)
                    ->orWhere('dcf_number', 'like', $needle)
                    ->orWhere('department', 'like', $needle)
                    ->orWhere('document_type', 'like', $needle);
            }
        });

        return $builder
            ->limit($limit)
            ->get()
            ->map(static function (ControlledDocument $document): array {
                $revision = (int) $document->current_revision;
                $subtitleParts = array_filter([
                    $revision > 0 ? 'Rev '.$revision : null,
                    is_string($document->department) && $document->department !== '' ? $document->department : null,
                    is_string($document->document_type) && $document->document_type !== '' ? $document->document_type : null,
                    $document->title !== $document->document_code ? $document->title : null,
                    is_string($document->dcf_number) && $document->dcf_number !== '' ? $document->dcf_number : null,
                ]);

                return [
                    'module' => 'document_register',
                    'entity_type' => 'controlled_document',
                    'id' => (string) $document->id,
                    'title' => (string) $document->document_code,
                    'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
                    'status' => $document->status,
                    'status_label' => $document->status !== null && $document->status !== ''
                        ? ucfirst((string) $document->status)
                        : null,
                    'href' => '/documents/controlled?document='.(string) $document->id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Full query, plus the register code when the query is a revision number
     * such as ATC-P-ENGINEERING-001-R001 (stored document_code has no -R suffix).
     *
     * @return list<string>
     */
    private function likeNeedles(string $search): array
    {
        $terms = [trim($search)];
        $compact = preg_replace('/\s+/', '', $terms[0]) ?? $terms[0];
        if ($compact !== '' && $compact !== $terms[0]) {
            $terms[] = $compact;
        }

        $needles = [];
        foreach ($terms as $term) {
            if (mb_strlen($term) < 2) {
                continue;
            }
            $needles[$term] = '%'.addcslashes($term, '%_\\').'%';
            if (preg_match('/^(.+)-R\d{1,4}$/i', $term, $matches) === 1 && mb_strlen($matches[1]) >= 2) {
                $needles[$matches[1]] = '%'.addcslashes($matches[1], '%_\\').'%';
            }
        }

        return array_values($needles);
    }
}
