<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EApprovalFileStorageService
{
    public function store(
        EApprovalSubmission $submission,
        UploadedFile $file,
        ?string $fieldName,
    ): EApprovalAttachment {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $tenantId = $this->tenantStoragePrefix();
        $storedPath = sprintf(
            '%s/e-approval/%s/%s',
            $tenantId,
            $submission->id,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return EApprovalAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_name' => $fieldName,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * @return array{brand_logo_url: string}
     */
    public function storeFormLogo(EApprovalForm $form, UploadedFile $file): array
    {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $allowedLogo = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        if (! in_array($extension, $allowedLogo, true)) {
            throw ValidationException::withMessages([
                'file' => [__('Logo must be an image (png, jpg, gif, webp, svg).')],
            ]);
        }

        $filename = 'logo-'.Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/e-approval/forms/%s/%s',
            $this->tenantStoragePrefix(),
            $form->id,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        $publicUrl = '/storage/tenant/'.$storedPath;

        return ['brand_logo_url' => $publicUrl];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function storeUserAttachment(TenantUser $user, UploadedFile $file): array
    {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

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

    private function assertAllowedMime(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $allowed = config('toweros.tenant_files.allowed_mimes', []);

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => [__('File type is not allowed.')],
            ]);
        }
    }

    private function assertAllowedSize(UploadedFile $file): void
    {
        $maxKb = (int) config('toweros.tenant_files.max_size_kb', 10240);
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
