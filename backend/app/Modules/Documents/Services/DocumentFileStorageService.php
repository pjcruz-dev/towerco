<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentFileStorageService
{
    public function __construct(
        private readonly DocumentUploadValidator $validator,
    ) {}

    public function storeForDocument(
        Document $document,
        UploadedFile $file,
        int $version,
    ): string {
        $this->validator->assertAllowedUploadedFile($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/documents/%s/%s/v%d/%s',
            $this->tenantStoragePrefix(),
            $document->site_id,
            $document->id,
            $version,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return $storedPath;
    }

    public function importFromExistingPath(
        Document $document,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        int $sizeBytes,
        int $version,
    ): string {
        $disk = $this->disk();
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'file' => [__('Source file not found in storage.')],
            ]);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/documents/%s/%s/v%d/%s',
            $this->tenantStoragePrefix(),
            $document->site_id,
            $document->id,
            $version,
            $filename,
        );

        $storage->put($storedPath, $storage->get($sourcePath));

        return $storedPath;
    }

    public function downloadUrl(Document $document): string
    {
        $disk = $this->disk();

        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $document->stored_path,
                now()->addMinutes((int) config('toweros.tenant_files.signed_url_minutes', 60)),
            );
        }

        return url('/api/v1/documents/files/'.$document->id.'/download');
    }

    public function streamDownload(Document $document): StreamedResponse
    {
        $disk = $this->disk();

        if (! Storage::disk($disk)->exists($document->stored_path)) {
            throw ValidationException::withMessages([
                'document' => [__('File not found in storage.')],
            ]);
        }

        return Storage::disk($disk)->download(
            $document->stored_path,
            $document->original_filename,
        );
    }

    private function disk(): string
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
