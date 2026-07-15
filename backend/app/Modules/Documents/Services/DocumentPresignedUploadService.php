<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\DocumentUploadIntent;
use App\Modules\Documents\Support\DocumentUploadValidator;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentPresignedUploadService
{
    public function __construct(
        private readonly DocumentWorkspaceService $workspace,
        private readonly DocumentPlanFeaturesService $planFeatures,
        private readonly DocumentUploadValidator $validator,
        private readonly DocumentService $documents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $maxKb = (int) config('toweros.documents.max_size_kb', 51200);

        return [
            'direct_upload_enabled' => $this->supportsDirectUpload(),
            'presigned_min_bytes' => (int) config('toweros.documents.presigned_upload_min_kb', 10240) * 1024,
            'max_size_bytes' => $maxKb * 1024,
            'cad_extensions' => $this->validator->cadExtensions(),
            'multipart_fallback' => true,
        ];
    }

    public function supportsDirectUpload(): bool
    {
        if (! (bool) config('toweros.documents.presigned_upload_enabled', true)) {
            return false;
        }

        return $this->diskName() === 's3';
    }

    /**
     * @return array<string, mixed>
     */
    public function createIntent(
        Site $site,
        string $nodeId,
        string $filename,
        string $mimeType,
        int $sizeBytes,
        TenantUser $actor,
    ): array {
        $this->planFeatures->assertCanUpload();
        $this->assertDirectUploadAvailable();
        $this->workspace->assertUploadNodeBelongsToSite($site, $nodeId);
        $this->validator->assertAllowedFile($mimeType, $filename, $sizeBytes);

        $documentId = (string) Str::uuid();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
        $storedFilename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/documents/%s/%s/v1/%s',
            $this->tenantStoragePrefix(),
            $site->id,
            $documentId,
            $storedFilename,
        );

        $uploadToken = Str::random(48);
        $expiresAt = now()->addMinutes((int) config('toweros.documents.presigned_upload_ttl_minutes', 15));

        $intent = DocumentUploadIntent::query()->create([
            'id' => (string) Str::uuid(),
            'upload_token' => $uploadToken,
            'site_id' => $site->id,
            'site_node_id' => $nodeId,
            'document_id' => $documentId,
            'stored_path' => $storedPath,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'uploaded_by_id' => $actor->id,
            'expires_at' => $expiresAt,
        ]);

        $presigned = $this->buildPresignedPutUrl($storedPath, $mimeType, $sizeBytes, $expiresAt);

        return [
            'upload_token' => $intent->upload_token,
            'document_id' => $documentId,
            'upload_url' => $presigned['url'],
            'upload_method' => 'PUT',
            'upload_headers' => $presigned['headers'],
            'stored_path' => $storedPath,
            'expires_at' => $expiresAt->toIso8601String(),
            'cad_file' => $this->validator->isCadFilename($filename),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function completeIntent(
        Site $site,
        string $uploadToken,
        TenantUser $actor,
        ?string $title = null,
        ?string $expiresAt = null,
    ): array {
        $this->planFeatures->assertCanUpload();

        /** @var DocumentUploadIntent|null $intent */
        $intent = DocumentUploadIntent::query()
            ->where('upload_token', $uploadToken)
            ->where('site_id', $site->id)
            ->whereNull('consumed_at')
            ->first();

        if ($intent === null) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Upload session not found or already completed.')],
            ]);
        }

        if ($intent->expires_at !== null && $intent->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Upload session has expired. Request a new upload URL.')],
            ]);
        }

        if ((string) $intent->uploaded_by_id !== (string) $actor->id) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Upload session belongs to another user.')],
            ]);
        }

        $this->assertObjectReady($intent->stored_path, $intent->size_bytes);

        $document = $this->documents->createFromStoredFile(
            $site,
            (string) $intent->site_node_id,
            (string) $intent->document_id,
            $intent->stored_path,
            $intent->original_filename,
            $intent->mime_type,
            (int) $intent->size_bytes,
            $actor,
            $title,
            $expiresAt,
        );

        $intent->consumed_at = now();
        $intent->save();

        return $document;
    }

    private function assertDirectUploadAvailable(): void
    {
        if (! $this->supportsDirectUpload()) {
            throw ValidationException::withMessages([
                'upload' => [__('Direct upload is only available when tenant files are stored on S3. Use multipart upload instead.')],
            ]);
        }
    }

    /**
     * @return array{url: string, headers: array<string, string>}
     */
    private function buildPresignedPutUrl(string $storedPath, string $mimeType, int $sizeBytes, \DateTimeInterface $expiresAt): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->diskName());

        if (! method_exists($disk, 'getClient')) {
            throw ValidationException::withMessages([
                'upload' => [__('Direct upload is not configured for this storage driver.')],
            ]);
        }

        $client = $disk->getClient();
        $bucket = config('filesystems.disks.'.$this->diskName().'.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $storedPath,
            'ContentType' => $mimeType,
            'ContentLength' => $sizeBytes,
        ]);

        $request = $client->createPresignedRequest($command, $expiresAt);

        return [
            'url' => (string) $request->getUri(),
            'headers' => [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $sizeBytes,
            ],
        ];
    }

    private function assertObjectReady(string $storedPath, int $expectedSizeBytes): void
    {
        $disk = Storage::disk($this->diskName());

        if (! $disk->exists($storedPath)) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Uploaded file was not found in storage. Complete the PUT to S3 first.')],
            ]);
        }

        $size = $disk->size($storedPath);
        if ($size !== false && $size > 0 && $size !== $expectedSizeBytes) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Uploaded file size does not match the declared size.')],
            ]);
        }
    }

    private function diskName(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }

    private function tenantStoragePrefix(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return (string) tenant('id');
        }

        return 'local-dev';
    }
}
