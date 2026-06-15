<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Models\TicketingAttachment;
use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TicketingFileStorageService
{
    public function store(TicketingTicket $ticket, UploadedFile $file, TenantUser $uploadedBy): TicketingAttachment
    {
        $this->assertAllowedMime($file);
        $this->assertAllowedSize($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = sprintf(
            '%s/ticketing/%s/%s',
            $this->tenantStoragePrefix(),
            $ticket->id,
            $filename,
        );

        Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        return TicketingAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'uploaded_by_id' => $uploadedBy->id,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ]);
    }

    public function download(TicketingAttachment $attachment): StreamedResponse
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
