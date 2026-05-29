<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Support\RolloutFileContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantFileStorageService
{
    public function store(
        UploadedFile $uploadedFile,
        string $context,
        RolloutProgram $rollout,
        TenantUser $uploadedBy,
    ): TenantRolloutFile {
        if (! in_array($context, RolloutFileContext::all(), true)) {
            throw ValidationException::withMessages([
                'context' => [__('Invalid file context.')],
            ]);
        }

        $this->assertAllowedMime($uploadedFile);
        $this->assertAllowedSize($uploadedFile);
        $this->scanForMalware($uploadedFile);

        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $tenantId = $this->tenantStoragePrefix();
        $storedPath = sprintf(
            '%s/rollout/%s/%s/%s',
            $tenantId,
            $rollout->id,
            $context,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $uploadedFile,
            basename($storedPath),
        );

        /** @var TenantRolloutFile $record */
        $record = TenantRolloutFile::query()->create([
            'rollout_program_id' => $rollout->id,
            'context' => $context,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => (string) ($uploadedFile->getMimeType() ?? 'application/octet-stream'),
            'size_bytes' => (int) $uploadedFile->getSize(),
            'uploaded_by_id' => $uploadedBy->id,
        ]);

        return $record;
    }

    public function findForRollout(string $fileId, string $rolloutProgramId): TenantRolloutFile
    {
        /** @var TenantRolloutFile|null $file */
        $file = TenantRolloutFile::query()
            ->where('id', $fileId)
            ->where('rollout_program_id', $rolloutProgramId)
            ->first();

        if ($file === null) {
            throw ValidationException::withMessages([
                'file_id' => [__('Uploaded file not found for this rollout.')],
            ]);
        }

        return $file;
    }

    /**
     * @param  list<string>  $fileIds
     */
    public function assertFilesBelongToRollout(array $fileIds, string $rolloutProgramId): void
    {
        if ($fileIds === []) {
            return;
        }

        $found = TenantRolloutFile::query()
            ->where('rollout_program_id', $rolloutProgramId)
            ->whereIn('id', $fileIds)
            ->pluck('id')
            ->all();

        if (count($found) !== count(array_unique($fileIds))) {
            throw ValidationException::withMessages([
                'file_id' => [__('One or more uploaded files are invalid for this rollout.')],
            ]);
        }
    }

    public function downloadResponse(TenantRolloutFile $file): StreamedResponse
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($file->stored_path)) {
            abort(404);
        }

        return $disk->response($file->stored_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    /**
     * @return array{id: string, url: string, path: string, mime_type: string, size_bytes: int, original_filename: string}
     */
    public function present(TenantRolloutFile $file): array
    {
        return [
            'id' => $file->id,
            'url' => $this->apiUrl($file),
            'path' => $file->stored_path,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'original_filename' => $file->original_filename,
        ];
    }

    public function apiUrl(TenantRolloutFile $file): string
    {
        $disk = $this->disk();

        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $file->stored_path,
                now()->addMinutes((int) config('toweros.tenant_files.signed_url_minutes', 60)),
            );
        }

        return url('/api/v1/project-one/files/'.$file->id);
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }

    private function assertAllowedMime(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        /** @var list<string> $allowed */
        $allowed = config('toweros.tenant_files.allowed_mimes', []);

        foreach ($allowed as $pattern) {
            if ($pattern === $mime) {
                return;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($mime, $prefix)) {
                    return;
                }
            }
        }

        throw ValidationException::withMessages([
            'file' => [__('File type is not allowed.')],
        ]);
    }

    private function assertAllowedSize(UploadedFile $file): void
    {
        $maxKb = (int) config('toweros.tenant_files.max_size_kb', 10240);

        if ((int) $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => [__('File exceeds the maximum upload size.')],
            ]);
        }
    }

    private function scanForMalware(UploadedFile $file): void
    {
        // Hook for external AV integration in production.
    }

    private function tenantStoragePrefix(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return (string) tenant('id');
        }

        return 'local-dev';
    }
}
