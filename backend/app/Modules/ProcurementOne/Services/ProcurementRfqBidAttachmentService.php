<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidAttachment;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementRfqBidAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeMany(
        ProcurementRfqBid $bid,
        array $files,
        string $uploadedVia,
        ?ProcurementRfqBidVersion $version = null,
        ?TenantUser $actor = null,
        string $fieldName = 'quotation',
    ): void {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->store($bid, $file, $uploadedVia, $version, $actor, $fieldName);
        }
    }

    public function store(
        ProcurementRfqBid $bid,
        UploadedFile $file,
        string $uploadedVia,
        ?ProcurementRfqBidVersion $version = null,
        ?TenantUser $actor = null,
        string $fieldName = 'quotation',
    ): ProcurementRfqBidAttachment {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/procurement/rfq-bids/%s/%s',
            $this->tenantStoragePrefix(),
            $bid->id,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return ProcurementRfqBidAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'bid_id' => (string) $bid->id,
            'version_id' => $version?->id,
            'field_name' => $fieldName !== '' ? $fieldName : 'quotation',
            'file_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_via' => $uploadedVia,
            'uploaded_by_id' => $actor?->id,
        ]);
    }

    public function download(ProcurementRfqBidAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($attachment->stored_path)) {
            abort(404);
        }

        return $disk->response($attachment->stored_path, $attachment->file_name);
    }

    private function assertAllowedMime(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $allowed = config('toweros.tenant_files.allowed_mimes', []);

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'attachments' => [__('File type is not allowed.')],
            ]);
        }
    }

    private function assertAllowedSize(UploadedFile $file): void
    {
        $maxKb = (int) config('toweros.tenant_files.max_size_kb', 10240);
        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'attachments' => [__('File exceeds maximum upload size.')],
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
