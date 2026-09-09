<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocExtractFileStorageService
{
    public function store(string $batchId, UploadedFile $file): array
    {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/doc-extract/%s/%s',
            $this->tenantStoragePrefix(),
            $batchId,
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
            'mime_type' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function absolutePath(string $storedPath): string
    {
        return Storage::disk($this->disk())->path($storedPath);
    }

    public function delete(string $storedPath): void
    {
        $disk = Storage::disk($this->disk());
        if ($disk->exists($storedPath)) {
            $disk->delete($storedPath);
        }
    }

    public function readBytes(string $storedPath): string
    {
        $disk = Storage::disk($this->disk());
        if (! $disk->exists($storedPath)) {
            throw ValidationException::withMessages([
                'file' => [__('Document file is missing from storage.')],
            ]);
        }

        $contents = $disk->get($storedPath);
        if (! is_string($contents)) {
            throw ValidationException::withMessages([
                'file' => [__('Unable to read document file.')],
            ]);
        }

        return $contents;
    }

    private function assertAllowedMime(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $allowed = config('doc_extract.allowed_mimes', []);

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'files' => [__('File type is not allowed for DocExtract. Use PDF or image files.')],
            ]);
        }
    }

    private function assertAllowedSize(UploadedFile $file): void
    {
        $maxKb = (int) config('doc_extract.max_file_size_kb', 20480);
        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'files' => [__('File exceeds maximum DocExtract upload size.')],
            ]);
        }
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'local');
    }

    private function tenantStoragePrefix(): string
    {
        $tenant = tenant();

        return $tenant !== null ? (string) $tenant->getTenantKey() : 'unknown';
    }
}
