<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Support\RolloutFileContext;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentRolloutLeasePackageMigrationService
{
    public function __construct(
        private readonly DocumentWorkspaceService $workspace,
        private readonly DocumentFileStorageService $storage,
        private readonly DocumentActivityLogger $activity,
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @return array{migrated: int, skipped: int, errors: list<string>, documents: list<string>}
     */
    public function migrateRollout(
        RolloutProgram $program,
        ?string $candidateId = null,
        ?TenantUser $actor = null,
    ): array {
        $this->assertModuleEnabled();

        if ($program->site_id === null) {
            throw ValidationException::withMessages([
                'rollout' => [__('Rollout must be linked to a site before migrating lease documents.')],
            ]);
        }

        /** @var Site|null $site */
        $site = Site::query()->find($program->site_id);
        if ($site === null) {
            throw ValidationException::withMessages([
                'site' => [__('Site not found for this rollout.')],
            ]);
        }

        $this->workspace->updateWorkspace($site, (string) $program->id);

        $candidatesQuery = SiteCandidate::query()->where('rollout_program_id', $program->id);
        if ($candidateId !== null && $candidateId !== '') {
            $candidatesQuery->where('id', $candidateId);
        } else {
            $candidatesQuery->where('status', 'selected');
        }

        $summary = ['migrated' => 0, 'skipped' => 0, 'errors' => [], 'documents' => []];

        foreach ($candidatesQuery->get() as $candidate) {
            $result = $this->migrateCandidate($candidate, $site, $actor);
            $summary['migrated'] += $result['migrated'];
            $summary['skipped'] += $result['skipped'];
            $summary['errors'] = array_merge($summary['errors'], $result['errors']);
            $summary['documents'] = array_merge($summary['documents'], $result['documents']);
        }

        return $summary;
    }

    /**
     * @return array{migrated: int, skipped: int, errors: list<string>, documents: list<string>}
     */
    public function migrateCandidate(
        SiteCandidate $candidate,
        Site $site,
        ?TenantUser $actor = null,
    ): array {
        $this->assertModuleEnabled();

        $package = $candidate->lease_package;
        if (! is_array($package)) {
            return ['migrated' => 0, 'skipped' => 0, 'errors' => [], 'documents' => []];
        }

        $documents = is_array($package['documents'] ?? null) ? $package['documents'] : [];
        if ($documents === []) {
            return ['migrated' => 0, 'skipped' => 0, 'errors' => [], 'documents' => []];
        }

        $uploadNode = $this->resolveUploadNode($site, $candidate);
        $summary = ['migrated' => 0, 'skipped' => 0, 'errors' => [], 'documents' => []];
        $updatedDocuments = [];

        foreach ($documents as $entry) {
            if (! is_array($entry) || ! isset($entry['file_id'])) {
                continue;
            }

            $fileId = (string) $entry['file_id'];

            if (Document::query()->where('source_rollout_file_id', $fileId)->exists()) {
                $summary['skipped']++;
                $existing = Document::query()->where('source_rollout_file_id', $fileId)->first();
                if ($existing instanceof Document) {
                    $updatedDocuments[] = $this->leasePackageDocumentRow($entry, $existing);
                    $summary['documents'][] = (string) $existing->id;
                }

                continue;
            }

            try {
                $document = $this->migrateRolloutFile(
                    $fileId,
                    $candidate,
                    $site,
                    $uploadNode,
                    $actor,
                    isset($entry['label']) ? (string) $entry['label'] : null,
                );
                $summary['migrated']++;
                $summary['documents'][] = (string) $document->id;
                $updatedDocuments[] = $this->leasePackageDocumentRow($entry, $document);
            } catch (\Throwable $exception) {
                $summary['errors'][] = $fileId.': '.$exception->getMessage();
                $updatedDocuments[] = $entry;
            }
        }

        if ($updatedDocuments !== []) {
            $candidate->lease_package = array_merge($package, ['documents' => $updatedDocuments]);
            $candidate->save();
        }

        return $summary;
    }

    private function migrateRolloutFile(
        string $fileId,
        SiteCandidate $candidate,
        Site $site,
        DocumentSiteNode $uploadNode,
        ?TenantUser $actor,
        ?string $title,
    ): Document {
        /** @var TenantRolloutFile|null $rolloutFile */
        $rolloutFile = TenantRolloutFile::query()
            ->where('id', $fileId)
            ->where('rollout_program_id', $candidate->rollout_program_id)
            ->first();

        if ($rolloutFile === null) {
            throw ValidationException::withMessages([
                'file_id' => [__('Rollout file not found.')],
            ]);
        }

        if ($rolloutFile->context !== RolloutFileContext::LEASE_DOCUMENT) {
            throw ValidationException::withMessages([
                'file_id' => [__('Only lease documents can be migrated to the site binder.')],
            ]);
        }

        $uploaderId = $actor?->id ?? $rolloutFile->uploaded_by_id;

        return DB::connection('tenant')->transaction(function () use (
            $rolloutFile,
            $site,
            $uploadNode,
            $uploaderId,
            $title,
            $candidate,
            $actor,
        ): Document {
            $document = Document::query()->create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'site_node_id' => $uploadNode->id,
                'title' => $title !== null && trim($title) !== '' ? trim($title) : $rolloutFile->original_filename,
                'original_filename' => $rolloutFile->original_filename,
                'stored_path' => '',
                'mime_type' => $rolloutFile->mime_type,
                'size_bytes' => $rolloutFile->size_bytes,
                'status' => DocumentStatus::FINAL,
                'version' => 1,
                'sort_order' => (int) Document::query()->where('site_node_id', $uploadNode->id)->max('sort_order') + 1,
                'uploaded_by_id' => $uploaderId,
                'last_touched_by_id' => $uploaderId,
                'last_touched_at' => now(),
                'source_rollout_file_id' => $rolloutFile->id,
            ]);

            $storedPath = $this->storage->importFromExistingPath(
                $document,
                $rolloutFile->stored_path,
                $rolloutFile->original_filename,
                $rolloutFile->mime_type,
                $rolloutFile->size_bytes,
                1,
            );

            $document->stored_path = $storedPath;
            $document->save();

            DocumentVersion::query()->create([
                'id' => (string) Str::uuid(),
                'document_id' => $document->id,
                'version' => 1,
                'original_filename' => $rolloutFile->original_filename,
                'stored_path' => $storedPath,
                'mime_type' => $rolloutFile->mime_type,
                'size_bytes' => $rolloutFile->size_bytes,
                'uploaded_by_id' => $uploaderId,
                'created_at' => now(),
            ]);

            $this->activity->log($document, 'migrated_from_rollout', $actor, [
                'rollout_file_id' => (string) $rolloutFile->id,
                'rollout_program_id' => (string) $candidate->rollout_program_id,
                'site_candidate_id' => (string) $candidate->id,
            ]);

            return $document;
        });
    }

    private function resolveUploadNode(Site $site, SiteCandidate $candidate): DocumentSiteNode
    {
        $lessorName = trim((string) ($candidate->lessor_name ?? ''));
        if ($lessorName !== '') {
            $workspace = $this->workspace->ensureForSite($site);
            $existing = DocumentSiteNode::query()
                ->where('workspace_id', $workspace->id)
                ->where('lessor_name', $lessorName)
                ->first();

            if ($existing instanceof DocumentSiteNode) {
                $uploadNode = DocumentSiteNode::query()
                    ->where('parent_id', $existing->id)
                    ->where('node_key', 'lessor_documents')
                    ->first();
                if ($uploadNode instanceof DocumentSiteNode) {
                    return $uploadNode;
                }
            }

            $lessor = $this->workspace->addLessor(
                $site,
                $lessorName,
                $candidate->lessor_contact,
            );

            $uploadNode = DocumentSiteNode::query()->find($lessor['instance']['upload_node_id']);
            if ($uploadNode instanceof DocumentSiteNode) {
                return $uploadNode;
            }
        }

        $workspace = $this->workspace->ensureForSite($site);
        $colNode = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'col')
            ->first();

        if ($colNode instanceof DocumentSiteNode) {
            return $colNode;
        }

        throw ValidationException::withMessages([
            'binder' => [__('COL folder is not configured for this site binder.')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function leasePackageDocumentRow(array $entry, Document $document): array
    {
        return array_merge($entry, [
            'document_id' => (string) $document->id,
            'document_href' => '/sites/'.$document->site_id,
            'migrated_at' => now()->toIso8601String(),
        ]);
    }

    private function assertModuleEnabled(): void
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();
        if (! in_array('documents', $enabled, true) || ! in_array('sites', $enabled, true)) {
            throw ValidationException::withMessages([
                'documents' => [__('Documents module is not enabled for this tenant.')],
            ]);
        }
    }
}
