<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalExportHistory;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormOutboundFile;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EApprovalFileStorageService
{
    public function assertUploadAllowed(UploadedFile $file): void
    {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);
    }

    public function store(
        EApprovalSubmission $submission,
        UploadedFile $file,
        ?string $fieldName,
        ?array $metadata = null,
    ): EApprovalAttachment {
        $this->assertUploadAllowed($file);

        $existing = $this->findExistingByOriginalName($submission, $file->getClientOriginalName(), $fieldName);
        if ($existing !== null) {
            if ($metadata !== null && $metadata !== [] && empty($existing->metadata)) {
                $existing->metadata = $metadata;
                $existing->save();
            }

            return $existing;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $tenantId = $this->tenantStoragePrefix();
        $storedPath = sprintf(
            '%s/e-approval/%s/%s',
            $tenantId,
            $submission->id,
            $filename,
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('File could not be stored. Check storage configuration and try again.')],
            ]);
        }

        return EApprovalAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_name' => $fieldName,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'metadata' => $metadata,
        ]);
    }

    public function findExistingByOriginalName(
        EApprovalSubmission $submission,
        string $originalFileName,
        ?string $fieldName,
    ): ?EApprovalAttachment {
        $submission->loadMissing('attachments');

        return $submission->attachments->first(static function (EApprovalAttachment $attachment) use ($originalFileName, $fieldName): bool {
            if ((string) $attachment->file_name !== $originalFileName) {
                return false;
            }

            return (string) ($attachment->field_name ?? '') === (string) ($fieldName ?? '');
        });
    }

    /**
     * @return array{brand_logo_url: string, brand_logo_path: string}
     */
    public function storeFormLogo(EApprovalForm $form, UploadedFile $file): array
    {
        $this->assertUploadAllowed($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $allowedLogo = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        if (! in_array($extension, $allowedLogo, true)) {
            throw ValidationException::withMessages([
                'file' => [__('Logo must be an image (png, jpg, gif, webp, svg).')],
            ]);
        }

        $previousPath = $this->resolveFormLogoStoragePath($form);
        if ($previousPath !== null) {
            $this->deleteIfExists($previousPath);
        }

        $filename = 'logo-'.Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/e-approval/forms/%s/%s',
            $this->tenantStoragePrefix(),
            $form->id,
            $filename,
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('Logo could not be stored. Check storage configuration and try again.')],
            ]);
        }

        return [
            'brand_logo_path' => $storedPath,
            'brand_logo_url' => '/api/v1/e-approval/forms/'.$form->id.'/logo',
        ];
    }

    /**
     * @return array{logo_path: string, logo_url: string, code: string}
     */
    public function storeFormSubsidiaryLogo(EApprovalForm $form, string $code, UploadedFile $file): array
    {
        $code = app(EApprovalPdfLayoutService::class)->normalizeSubsidiaryCode($code);

        $this->assertUploadAllowed($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $allowedLogo = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        if (! in_array($extension, $allowedLogo, true)) {
            throw ValidationException::withMessages([
                'file' => [__('Logo must be an image (png, jpg, gif, webp, svg).')],
            ]);
        }

        $previousPath = $this->resolveFormSubsidiaryLogoStoragePath($form, $code);
        if ($previousPath !== null) {
            $this->deleteIfExists($previousPath);
        }

        $safeCode = preg_replace('/[^A-Z0-9_-]/', '', $code) ?: 'SUB';
        $filename = 'subsidiary-'.$safeCode.'-logo-'.Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/e-approval/forms/%s/%s',
            $this->tenantStoragePrefix(),
            $form->id,
            $filename,
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('Logo could not be stored. Check storage configuration and try again.')],
            ]);
        }

        return [
            'code' => $code,
            'logo_path' => $storedPath,
            'logo_url' => '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/'.$code,
        ];
    }

    public function presentFormSubsidiaryLogoUrl(EApprovalForm $form, string $code): ?string
    {
        if ($this->resolveFormSubsidiaryLogoStoragePath($form, $code) === null) {
            return null;
        }

        $code = app(EApprovalPdfLayoutService::class)->normalizeSubsidiaryCode($code);

        return '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/'.$code;
    }

    public function streamFormSubsidiaryLogo(EApprovalForm $form, string $code): StreamedResponse
    {
        $path = $this->resolveFormSubsidiaryLogoStoragePath($form, $code);
        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function resolveFormSubsidiaryLogoStoragePath(EApprovalForm $form, string $code): ?string
    {
        try {
            $code = app(EApprovalPdfLayoutService::class)->normalizeSubsidiaryCode($code);
        } catch (ValidationException) {
            return null;
        }

        $raw = app(EApprovalSettingsService::class)->getJson('pdf_layout_form_'.$form->id);
        $rawTemplate = is_array($raw['template'] ?? null) ? $raw['template'] : [];
        $logos = is_array($rawTemplate['subsidiary_logos'] ?? null) ? $rawTemplate['subsidiary_logos'] : [];
        $value = trim((string) ($logos[$code] ?? ''));

        if ($value !== '') {
            if (str_starts_with($value, '/storage/tenant/')) {
                $candidate = substr($value, strlen('/storage/tenant/'));
                if (Storage::disk($this->disk())->exists($candidate)) {
                    return $candidate;
                }
            } elseif (! str_starts_with($value, '/api/') && str_contains($value, '/e-approval/forms/')) {
                $candidate = ltrim($value, '/');
                if (Storage::disk($this->disk())->exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return $this->findLatestFormSubsidiaryLogoPath((string) $form->id, $code);
    }

    public function deleteFormSubsidiaryLogo(EApprovalForm $form, string $code): void
    {
        $path = $this->resolveFormSubsidiaryLogoStoragePath($form, $code);
        if ($path !== null) {
            $this->deleteIfExists($path);
        }
    }

    private function findLatestFormSubsidiaryLogoPath(string $formId, string $code): ?string
    {
        $prefix = sprintf('%s/e-approval/forms/%s/', $this->tenantStoragePrefix(), $formId);
        $disk = Storage::disk($this->disk());
        $files = $disk->files($prefix);
        $needle = 'subsidiary-'.strtoupper($code).'-logo-';
        $matches = array_values(array_filter(
            $files,
            static fn (string $path): bool => str_contains(basename($path), $needle),
        ));
        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => strcmp($b, $a));

        return $matches[0];
    }

    public function presentFormLogoUrl(EApprovalForm $form): ?string
    {
        if ($this->resolveFormLogoStoragePath($form) === null) {
            return null;
        }

        return '/api/v1/e-approval/forms/'.$form->id.'/logo';
    }

    public function streamFormLogo(EApprovalForm $form): StreamedResponse
    {
        $path = $this->resolveFormLogoStoragePath($form);
        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function resolveFormLogoStoragePath(EApprovalForm $form): ?string
    {
        $value = trim((string) ($form->brand_logo_url ?? ''));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '/storage/tenant/')) {
            return substr($value, strlen('/storage/tenant/'));
        }

        if (str_starts_with($value, '/api/')) {
            return $this->findLatestFormLogoPath((string) $form->id);
        }

        if (str_contains($value, '/e-approval/forms/')) {
            return ltrim($value, '/');
        }

        return null;
    }

    private function findLatestFormLogoPath(string $formId): ?string
    {
        $prefix = sprintf('%s/e-approval/forms/%s/', $this->tenantStoragePrefix(), $formId);
        $disk = Storage::disk($this->disk());
        $files = $disk->files($prefix);
        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => strcmp($b, $a));

        return $files[0];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function storeUserAttachment(TenantUser $user, UploadedFile $file): array
    {
        $this->assertUploadAllowed($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/e-approval/users/%s/%s',
            $this->tenantStoragePrefix(),
            $user->id,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return [
            'path' => $storedPath,
            'name' => $file->getClientOriginalName(),
        ];
    }

    public function deleteIfExists(string $path): void
    {
        $disk = Storage::disk($this->disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function download(EApprovalAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($attachment->file_path)) {
            abort(404);
        }

        return $disk->response($attachment->file_path, $attachment->file_name);
    }

    /**
     * @return array{path: string, name: string, byte_size: int}
     */
    public function storeFormOutboundFile(EApprovalForm $form, UploadedFile $file): array
    {
        $this->assertUploadAllowed($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/e-approval/forms/%s/outbound/%s',
            $this->tenantStoragePrefix(),
            $form->id,
            $filename,
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('File could not be stored. Check storage configuration and try again.')],
            ]);
        }

        return [
            'path' => $storedPath,
            'name' => $file->getClientOriginalName(),
            'byte_size' => (int) $file->getSize(),
        ];
    }

    public function downloadFormOutboundFile(EApprovalFormOutboundFile $file): StreamedResponse
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($file->file_path)) {
            abort(404);
        }

        return $disk->response($file->file_path, $file->file_name);
    }

    /**
     * Persist a generated export file under the tenant files disk.
     *
     * @return array{path: string, disk: string, byte_size: int}
     */
    public function storeExport(string $localPath, string $userId, string $historyId, string $filename): array
    {
        $diskName = $this->disk();
        $safeName = basename($filename);
        $storedPath = sprintf(
            '%s/e-approval/exports/%s/%s/%s',
            $this->tenantStoragePrefix(),
            $userId,
            $historyId,
            $safeName,
        );

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open the generated export file for storage.');
        }

        try {
            $stored = Storage::disk($diskName)->put($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        if ($stored === false) {
            throw new \RuntimeException('Unable to store the export file.');
        }

        return [
            'path' => $storedPath,
            'disk' => $diskName,
            'byte_size' => (int) filesize($localPath),
        ];
    }

    /**
     * @return array{url: string, stream: bool}|null
     */
    public function exportDownloadInfo(EApprovalExportHistory $history): ?array
    {
        $path = (string) ($history->file_path ?? '');
        $diskName = (string) ($history->disk ?: $this->disk());
        if ($path === '' || ! Storage::disk($diskName)->exists($path)) {
            return null;
        }

        if ($history->expires_at !== null && $history->expires_at->isPast()) {
            return null;
        }

        if ($diskName === 's3') {
            $minutes = (int) config('toweros.tenant_files.signed_url_minutes', 60);

            return [
                'url' => Storage::disk($diskName)->temporaryUrl($path, now()->addMinutes($minutes)),
                'stream' => false,
            ];
        }

        return [
            'url' => '/e-approval/export-history/'.$history->id.'/download',
            'stream' => true,
        ];
    }

    public function downloadExport(EApprovalExportHistory $history): StreamedResponse
    {
        $path = (string) ($history->file_path ?? '');
        $diskName = (string) ($history->disk ?: $this->disk());
        $disk = Storage::disk($diskName);

        if ($path === '' || ! $disk->exists($path)) {
            abort(404);
        }

        if ($history->expires_at !== null && $history->expires_at->isPast()) {
            abort(410, 'This export download has expired.');
        }

        return $disk->response($path, (string) ($history->filename ?: 'export'));
    }

    public function deleteAttachment(EApprovalAttachment $attachment): void
    {
        $this->deleteIfExists((string) $attachment->file_path);
        $attachment->delete();
    }

    private function assertAllowedMime(UploadedFile $file): void
    {
        $mime = strtolower((string) $file->getMimeType());
        $allowed = config('toweros.tenant_files.allowed_mimes', []);

        if ($allowed === []) {
            return;
        }

        if (in_array($mime, $allowed, true)) {
            return;
        }

        if ($this->mimeAllowedByExtension($file, $allowed)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => [__('File type is not allowed.')],
        ]);
    }

    /**
     * Browsers and Windows often report Office files as application/octet-stream or application/zip.
     *
     * @param  list<string>  $allowedMimes
     */
    private function mimeAllowedByExtension(UploadedFile $file, array $allowedMimes): bool
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '') {
            return false;
        }

        $mimeByExtension = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $expectedMime = $mimeByExtension[$ext] ?? null;

        return $expectedMime !== null && in_array($expectedMime, $allowedMimes, true);
    }

    private function assertAllowedSize(UploadedFile $file): void
    {
        $maxKb = (int) config('toweros.tenant_files.max_size_kb', 25600);
        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => [__('File exceeds maximum upload size.')],
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
