<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetDocument;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetFileStorageService
{
    public function store(Asset $asset, UploadedFile $file, TenantUser $actor, ?string $repairId = null): AssetDocument
    {
        $mime = (string) $file->getMimeType();
        $allowed = config('toweros.tenant_files.allowed_mimes', []);
        if (is_array($allowed) && $allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => [__('File type is not allowed.')]]);
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = Str::uuid()->toString().'.'.$extension;
        $tenantId = (string) (tenant()?->getTenantKey() ?? 'unknown');
        $path = $tenantId.'/assets/'.$asset->id.'/'.$name;
        Storage::disk($this->disk())->putFileAs(dirname($path), $file, basename($path));

        return AssetDocument::query()->create([
            'id' => (string) Str::uuid(),
            'asset_id' => $asset->id,
            'repair_id' => $repairId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'local');
    }
}
