<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\AppMenuTile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AppMenuIconAssetService
{
    private const MAX_BYTES = 512 * 1024;

    /** @var list<string> */
    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function hostedUrl(AppMenuTile $tile): string
    {
        return '/api/v1/public/app-menu-icons/'.$tile->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function store(AppMenuTile $tile, UploadedFile $file): array
    {
        $this->assertUploadAllowed($file);

        $previousPath = is_string($tile->icon_asset) ? $tile->icon_asset : null;
        $this->deleteStoredAsset($tile, $previousPath);

        $extension = $this->extension($file);
        $directory = $this->directory($tile);
        $filename = 'icon.'.$extension;

        $stored = Storage::disk($this->disk())->putFileAs($directory, $file, $filename);
        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('Icon could not be stored. Check storage configuration and try again.')],
            ]);
        }

        $tile->icon_asset = $stored;
        $tile->icon_url = $this->hostedUrl($tile);
        $tile->save();

        return ($tile->fresh() ?? $tile)->toApiArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(AppMenuTile $tile): array
    {
        $previousPath = is_string($tile->icon_asset) ? $tile->icon_asset : null;
        $this->deleteStoredAsset($tile, $previousPath);

        $tile->icon_asset = null;
        $tile->icon_url = null;
        $tile->save();

        return ($tile->fresh() ?? $tile)->toApiArray();
    }

    public function deleteForTile(AppMenuTile $tile): void
    {
        $previousPath = is_string($tile->icon_asset) ? $tile->icon_asset : null;
        $this->deleteStoredAsset($tile, $previousPath);
    }

    public function stream(AppMenuTile $tile): StreamedResponse
    {
        $path = $this->resolveStoredPath($tile);
        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function resolveStoredPath(AppMenuTile $tile): ?string
    {
        $path = is_string($tile->icon_asset) ? trim($tile->icon_asset) : '';
        if ($path === '') {
            return null;
        }

        $prefix = $this->directory($tile).'/';
        if (! str_starts_with($path, $prefix) || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function assertUploadAllowed(UploadedFile $file): void
    {
        if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => [__('Image must be 512 KB or smaller.')],
            ]);
        }

        $extension = $this->extension($file);
        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => [__('Upload a PNG, JPEG, GIF, or WebP image.')],
            ]);
        }
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    private function directory(AppMenuTile $tile): string
    {
        return 'platform/app-menu-icons/'.$tile->id;
    }

    private function deleteStoredAsset(AppMenuTile $tile, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $prefix = $this->directory($tile).'/';
        if (! str_starts_with($path, $prefix) || str_contains($path, '..')) {
            return;
        }

        $disk = Storage::disk($this->disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function disk(): string
    {
        return 'local';
    }
}
