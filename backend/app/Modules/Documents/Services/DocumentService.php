<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentService
{
    public function __construct(
        private readonly DocumentWorkspaceService $workspace,
        private readonly DocumentFileStorageService $storage,
        private readonly DocumentActivityLogger $activity,
        private readonly DocumentPlanFeaturesService $planFeatures,
        private readonly DocumentApprovalService $approvals,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForNode(Site $site, string $nodeId): array
    {
        $node = $this->workspace->assertUploadNodeBelongsToSite($site, $nodeId);

        $documents = Document::query()
            ->where('site_id', $site->id)
            ->where('site_node_id', $node->id)
            ->whereNull('deleted_at')
            ->with(['uploadedBy:id,name', 'lastTouchedBy:id,name'])
            ->orderBy('sort_order')
            ->orderByDesc('last_touched_at')
            ->get();

        $submissionIds = $documents
            ->pluck('e_approval_submission_id')
            ->filter()
            ->unique()
            ->values();

        $submissions = $submissionIds->isEmpty()
            ? collect()
            : EApprovalSubmission::query()
                ->with('form:id,name')
                ->whereIn('id', $submissionIds)
                ->get()
                ->keyBy('id');

        return $documents
            ->map(function (Document $doc) use ($submissions): array {
                $submission = $doc->e_approval_submission_id !== null
                    ? $submissions->get($doc->e_approval_submission_id)
                    : null;

                return array_merge(
                    $this->toRow($doc),
                    $this->approvals->approvalPayloadForList($doc, $submission),
                );
            })
            ->values()
            ->all();
    }

    public function upload(
        Site $site,
        string $nodeId,
        UploadedFile $file,
        TenantUser $actor,
        ?string $title = null,
        ?string $expiresAt = null,
    ): array {
        $this->planFeatures->assertCanUpload();
        $node = $this->workspace->assertUploadNodeBelongsToSite($site, $nodeId);

        return DB::connection('tenant')->transaction(function () use ($site, $node, $file, $actor, $title, $expiresAt): array {
            $document = Document::query()->create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'site_node_id' => $node->id,
                'title' => $title !== null && trim($title) !== '' ? trim($title) : $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => '',
                'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                'size_bytes' => (int) $file->getSize(),
                'status' => DocumentStatus::DRAFT,
                'version' => 1,
                'expires_at' => $expiresAt,
                'sort_order' => (int) Document::query()->where('site_node_id', $node->id)->max('sort_order') + 1,
                'uploaded_by_id' => $actor->id,
                'last_touched_by_id' => $actor->id,
                'last_touched_at' => now(),
            ]);

            $storedPath = $this->storage->storeForDocument($document, $file, 1);
            $document->stored_path = $storedPath;
            $document->save();

            $this->createVersionRecord($document, 1, $actor);

            $this->activity->log($document, 'uploaded', $actor, ['version' => 1, 'upload_method' => 'multipart']);

            return $this->toRow($document->fresh(['uploadedBy', 'lastTouchedBy']));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function createFromStoredFile(
        Site $site,
        string $nodeId,
        string $documentId,
        string $storedPath,
        string $originalFilename,
        string $mimeType,
        int $sizeBytes,
        TenantUser $actor,
        ?string $title = null,
        ?string $expiresAt = null,
    ): array {
        $this->planFeatures->assertCanUpload();
        $node = $this->workspace->assertUploadNodeBelongsToSite($site, $nodeId);

        return DB::connection('tenant')->transaction(function () use (
            $site,
            $node,
            $documentId,
            $storedPath,
            $originalFilename,
            $mimeType,
            $sizeBytes,
            $actor,
            $title,
            $expiresAt,
        ): array {
            $document = new Document([
                'site_id' => $site->id,
                'site_node_id' => $node->id,
                'title' => $title !== null && trim($title) !== '' ? trim($title) : $originalFilename,
                'original_filename' => $originalFilename,
                'stored_path' => $storedPath,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'status' => DocumentStatus::DRAFT,
                'version' => 1,
                'expires_at' => $expiresAt,
                'sort_order' => (int) Document::query()->where('site_node_id', $node->id)->max('sort_order') + 1,
                'uploaded_by_id' => $actor->id,
                'last_touched_by_id' => $actor->id,
                'last_touched_at' => now(),
            ]);
            $document->id = $documentId;
            $document->save();

            $this->createVersionRecord($document, 1, $actor);

            $this->activity->log($document, 'uploaded', $actor, [
                'version' => 1,
                'upload_method' => 'presigned',
            ]);

            return $this->toRow($document->fresh(['uploadedBy', 'lastTouchedBy']));
        });
    }

    private function createVersionRecord(Document $document, int $version, TenantUser $actor): void
    {
        DocumentVersion::query()->create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'version' => $version,
            'original_filename' => $document->original_filename,
            'stored_path' => $document->stored_path,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'uploaded_by_id' => $actor->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{title?: string|null, status?: string|null, expires_at?: string|null}  $input
     */
    public function updateMetadata(Document $document, array $input, TenantUser $actor): array
    {
        if (isset($input['title']) && trim((string) $input['title']) !== '') {
            $document->title = trim((string) $input['title']);
        }

        if (isset($input['status']) && in_array($input['status'], [DocumentStatus::DRAFT, DocumentStatus::FINAL, DocumentStatus::SUPERSEDED], true)) {
            $document->status = (string) $input['status'];
        }

        if (array_key_exists('expires_at', $input)) {
            $document->expires_at = $input['expires_at'] !== null && $input['expires_at'] !== ''
                ? $input['expires_at']
                : null;
        }

        $document->last_touched_by_id = $actor->id;
        $document->last_touched_at = now();
        $document->save();

        $this->activity->log($document, 'metadata_updated', $actor);

        return $this->toRow($document->fresh(['uploadedBy', 'lastTouchedBy']));
    }

    public function uploadNewVersion(Document $document, UploadedFile $file, TenantUser $actor): array
    {
        $this->planFeatures->assertCanUpload();

        return DB::connection('tenant')->transaction(function () use ($document, $file, $actor): array {
            $nextVersion = (int) $document->version + 1;
            $storedPath = $this->storage->storeForDocument($document, $file, $nextVersion);

            DocumentVersion::query()->create([
                'id' => (string) Str::uuid(),
                'document_id' => $document->id,
                'version' => $nextVersion,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by_id' => $actor->id,
                'created_at' => now(),
            ]);

            $document->fill([
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                'size_bytes' => (int) $file->getSize(),
                'version' => $nextVersion,
                'last_touched_by_id' => $actor->id,
                'last_touched_at' => now(),
            ]);
            $document->save();

            $this->activity->log($document, 'version_uploaded', $actor, ['version' => $nextVersion]);

            return $this->toRow($document->fresh(['uploadedBy', 'lastTouchedBy']));
        });
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(Site $site, string $nodeId, array $orderedIds, TenantUser $actor): void
    {
        $node = $this->workspace->assertUploadNodeBelongsToSite($site, $nodeId);

        $documents = Document::query()
            ->where('site_id', $site->id)
            ->where('site_node_id', $node->id)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy(static fn (Document $d) => (string) $d->id);

        if ($documents->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'order' => [__('One or more documents were not found in this folder.')],
            ]);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $doc = $documents->get($id);
            if ($doc instanceof Document) {
                $doc->sort_order = $index;
                $doc->last_touched_by_id = $actor->id;
                $doc->last_touched_at = now();
                $doc->save();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Document $document): array
    {
        $document->load(['uploadedBy:id,name', 'lastTouchedBy:id,name', 'activities.actor:id,name', 'versions.uploadedBy:id,name']);

        $document = $this->approvals->syncApprovalStatus($document);

        return array_merge($this->toRow($document), $this->approvals->approvalPayload($document), [
            'download_url' => $this->storage->downloadUrl($document),
            'activities' => $document->activities->map(static fn ($a) => [
                'id' => (string) $a->id,
                'event' => $a->event,
                'at' => $a->created_at?->toIso8601String(),
                'actor' => $a->actor ? ['id' => (string) $a->actor->id, 'name' => $a->actor->name] : null,
                'metadata' => $a->metadata,
            ])->values()->all(),
            'versions' => $document->versions->map(static fn (DocumentVersion $v) => [
                'version' => $v->version,
                'original_filename' => $v->original_filename,
                'size_bytes' => $v->size_bytes,
                'uploaded_at' => $v->created_at?->toIso8601String(),
                'uploaded_by' => $v->uploadedBy ? ['id' => (string) $v->uploadedBy->id, 'name' => $v->uploadedBy->name] : null,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Document $document): array
    {
        return [
            'id' => (string) $document->id,
            'site_id' => (string) $document->site_id,
            'site_node_id' => (string) $document->site_node_id,
            'title' => $document->title,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'status' => $document->status,
            'version' => $document->version,
            'expires_at' => $document->expires_at?->toIso8601String(),
            'sort_order' => $document->sort_order,
            'uploaded_by' => $document->uploadedBy ? [
                'id' => (string) $document->uploadedBy->id,
                'name' => $document->uploadedBy->name,
            ] : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'last_touched_by' => $document->lastTouchedBy ? [
                'id' => (string) $document->lastTouchedBy->id,
                'name' => $document->lastTouchedBy->name,
            ] : null,
            'last_touched_at' => $document->last_touched_at?->toIso8601String(),
            'approval_status' => $document->approval_status,
            'e_approval_submission_id' => $document->e_approval_submission_id,
        ];
    }
}
