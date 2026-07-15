<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;

final class RolloutMediaAttachmentService
{
    public function __construct(
        private readonly TenantFileStorageService $files,
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function photoLinksRules(): array
    {
        return [
            'photo_links' => ['sometimes', 'nullable', 'array'],
            'photo_links.*.file_id' => ['required', 'uuid'],
            'photo_links.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function leasePackageRules(): array
    {
        return [
            'lease_package' => ['sometimes', 'nullable', 'array'],
            'lease_package.lessor_id_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'lease_package.lease_term_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'lease_package.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lease_package.documents' => ['sometimes', 'nullable', 'array'],
            'lease_package.documents.*.file_id' => ['required', 'uuid'],
            'lease_package.documents.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return list<array{file_id: string, url: string, label: string|null, mime_type: string}>|null
     */
    public function normalizePhotoLinks(?array $input, string $rolloutProgramId): ?array
    {
        if ($input === null) {
            return null;
        }

        if ($input === []) {
            return [];
        }

        $fileIds = $this->collectFileIdsFromPhotoLinks($input);
        $this->files->assertFilesBelongToRollout($fileIds, $rolloutProgramId);

        $normalized = [];
        foreach ($input as $row) {
            if (! is_array($row) || ! isset($row['file_id'])) {
                continue;
            }

            $file = $this->files->findForRollout((string) $row['file_id'], $rolloutProgramId);
            $normalized[] = [
                'file_id' => $file->id,
                'url' => $this->files->apiUrl($file),
                'label' => isset($row['label']) ? (string) $row['label'] : $file->original_filename,
                'mime_type' => $file->mime_type,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>|null
     */
    public function normalizeLeasePackage(?array $input, string $rolloutProgramId): ?array
    {
        if ($input === null) {
            return null;
        }

        $documents = is_array($input['documents'] ?? null) ? $input['documents'] : [];
        $fileIds = $this->collectFileIdsFromDocuments($documents);
        $this->files->assertFilesBelongToRollout($fileIds, $rolloutProgramId);

        $normalizedDocuments = [];
        foreach ($documents as $document) {
            if (! is_array($document) || ! isset($document['file_id'])) {
                continue;
            }

            $file = $this->files->findForRollout((string) $document['file_id'], $rolloutProgramId);
            $normalizedDocuments[] = [
                'file_id' => $file->id,
                'url' => $this->files->apiUrl($file),
                'label' => isset($document['label']) ? (string) $document['label'] : $file->original_filename,
                'mime_type' => $file->mime_type,
            ];
        }

        return [
            'lessor_id_type' => isset($input['lessor_id_type']) ? (string) $input['lessor_id_type'] : null,
            'lease_term_months' => isset($input['lease_term_months']) ? (int) $input['lease_term_months'] : null,
            'notes' => isset($input['notes']) ? (string) $input['notes'] : null,
            'documents' => $normalizedDocuments,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $links
     * @return list<array<string, mixed>>|null
     */
    public function enrichPhotoLinks(?array $links): ?array
    {
        if ($links === null || $links === []) {
            return $links;
        }

        $enriched = [];
        foreach ($links as $link) {
            if (! is_array($link) || ! isset($link['file_id'])) {
                continue;
            }

            $file = TenantRolloutFile::query()->find($link['file_id']);
            if ($file === null) {
                $enriched[] = $link;

                continue;
            }

            $enriched[] = [
                'file_id' => $file->id,
                'url' => $this->files->apiUrl($file),
                'label' => $link['label'] ?? $file->original_filename,
                'mime_type' => $file->mime_type,
            ];
        }

        return $enriched;
    }

    /**
     * @param  array<string, mixed>|null  $package
     * @return array<string, mixed>|null
     */
    public function enrichLeasePackage(?array $package): ?array
    {
        if ($package === null || $package === []) {
            return $package;
        }

        $documents = is_array($package['documents'] ?? null) ? $package['documents'] : [];
        $enrichedDocuments = [];

        foreach ($documents as $document) {
            if (! is_array($document) || ! isset($document['file_id'])) {
                continue;
            }

            $file = TenantRolloutFile::query()->find($document['file_id']);
            if ($file === null) {
                $enrichedDocuments[] = $document;

                continue;
            }

            $enrichedDocuments[] = array_merge([
                'file_id' => $file->id,
                'url' => $this->files->apiUrl($file),
                'label' => $document['label'] ?? $file->original_filename,
                'mime_type' => $file->mime_type,
            ], $this->documentMigrationMeta($file));
        }

        return array_merge($package, ['documents' => $enrichedDocuments]);
    }

    /**
     * @param  list<mixed>  $input
     * @return list<string>
     */
    private function collectFileIdsFromPhotoLinks(array $input): array
    {
        $fileIds = [];
        foreach ($input as $row) {
            if (is_array($row) && isset($row['file_id'])) {
                $fileIds[] = (string) $row['file_id'];
            }
        }

        return $fileIds;
    }

    /**
     * @param  list<mixed>  $documents
     * @return list<string>
     */
    private function collectFileIdsFromDocuments(array $documents): array
    {
        $fileIds = [];
        foreach ($documents as $document) {
            if (is_array($document) && isset($document['file_id'])) {
                $fileIds[] = (string) $document['file_id'];
            }
        }

        return $fileIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMigrationMeta(TenantRolloutFile $file): array
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();
        if (! in_array('documents', $enabled, true)) {
            return [];
        }

        $migrated = Document::query()->where('source_rollout_file_id', $file->id)->first();
        if ($migrated === null) {
            return [];
        }

        return [
            'document_id' => (string) $migrated->id,
            'document_href' => '/sites/'.$migrated->site_id,
        ];
    }
}
