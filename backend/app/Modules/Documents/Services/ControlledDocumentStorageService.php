<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\DocumentUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ControlledDocumentStorageService
{
    public function __construct(
        private readonly DocumentUploadValidator $validator,
    ) {}

    public function storeUploadedFile(
        ControlledDocument $document,
        ControlledDocumentRevision $revision,
        UploadedFile $file,
    ): array {
        $this->validator->assertAllowedUploadedFile($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/controlled-documents/%s/rev-%d/%s',
            $this->tenantStoragePrefix(),
            $document->id,
            $revision->revision_number,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return [
            'stored_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    /**
     * @return array{stored_path: string, original_filename: string, mime_type: string|null, size_bytes: int|null}
     */
    public function copyFromEApprovalPath(
        ControlledDocument $document,
        ControlledDocumentRevision $revision,
        string $sourcePath,
        string $originalFilename,
    ): array {
        $disk = $this->disk();
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'file' => [__('Source attachment not found in storage.')],
            ]);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/controlled-documents/%s/rev-%d/%s',
            $this->tenantStoragePrefix(),
            $document->id,
            $revision->revision_number,
            $filename,
        );

        $storage->put($storedPath, $storage->get($sourcePath));

        return [
            'stored_path' => $storedPath,
            'original_filename' => $originalFilename,
            'mime_type' => null,
            'size_bytes' => $storage->size($storedPath),
        ];
    }

    public function streamDownload(ControlledDocumentRevision $revision): StreamedResponse
    {
        $path = (string) $revision->stored_path;
        $disk = Storage::disk($this->disk());

        if ($path === '' || ! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, (string) ($revision->original_filename ?: 'document'));
    }

    /**
     * @return array{url: string, stream: bool}
     *
     * For S3 disks, returns a time-limited presigned URL that the browser can
     * open directly (no auth headers needed).
     *
     * For local disks, returns a relative API path that the frontend must fetch
     * through its authenticated API client (which knows the correct base URL and
     * sends the bearer token / tenant header automatically).  The `stream: true`
     * flag tells the client to use a blob fetch rather than window.open().
     */
    public function downloadInfo(ControlledDocumentRevision $revision): array
    {
        $disk = $this->disk();
        $path = (string) $revision->stored_path;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        if ($disk === 's3') {
            $minutes = (int) config('toweros.tenant_files.signed_url_minutes', 60);

            return [
                'url'    => Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes)),
                'stream' => false,
            ];
        }

        // For local disk, return a relative API path so the frontend fetches it
        // through its own apiClient.  This avoids domain/port mismatch between
        // the Next.js SPA host and the Laravel backend host.
        return [
            'url' => sprintf(
                '/documents/controlled/%s/revisions/%s/stream',
                $revision->controlled_document_id,
                $revision->id,
            ),
            'stream' => true,
        ];
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }

    private function tenantStoragePrefix(): string
    {
        $tenant = tenant();

        return $tenant !== null ? (string) $tenant->getTenantKey() : 'unknown';
    }
}
