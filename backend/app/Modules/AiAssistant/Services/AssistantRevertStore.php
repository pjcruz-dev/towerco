<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One restorable snapshot of the last admin delete or database write.
 */
final class AssistantRevertStore
{
    public function remember(array $snapshot): void
    {
        try {
            Storage::disk($this->disk())->put(
                $this->path(),
                json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            );
        } catch (Throwable) {
            // A missing snapshot only means revert is unavailable.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        try {
            $disk = Storage::disk($this->disk());
            if (! $disk->exists($this->path())) {
                return null;
            }
            $decoded = json_decode($disk->get($this->path()), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function forget(): void
    {
        try {
            Storage::disk($this->disk())->delete($this->path());
        } catch (Throwable) {
            // Already gone.
        }
    }

    public function copyAside(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            $disk = Storage::disk($this->disk());
            if (! $disk->exists($path)) {
                return null;
            }
            $trash = 'assistant-revert/files/'.sha1($path);
            $disk->copy($path, $trash);

            return $trash;
        } catch (Throwable) {
            return null;
        }
    }

    public function restoreFile(string $trash, string $path): void
    {
        if ($trash === '' || $path === '') {
            return;
        }

        try {
            $disk = Storage::disk($this->disk());
            if ($disk->exists($trash)) {
                $disk->copy($trash, $path);
            }
        } catch (Throwable) {
            // The row can still come back without the file.
        }
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'local');
    }

    private function path(): string
    {
        return 'assistant-revert/last.json';
    }
}
