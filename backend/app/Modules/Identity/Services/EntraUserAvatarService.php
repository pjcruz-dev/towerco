<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store and stream Entra / Microsoft 365 profile photos for tenant users.
 */
final class EntraUserAvatarService
{
    public function hasAvatarColumns(): bool
    {
        return Schema::connection('tenant')->hasColumn('users', 'avatar_path');
    }

    public function presentAvatarUrl(TenantUser $user): ?string
    {
        if (! $this->hasAvatarColumns()) {
            return null;
        }

        $path = is_string($user->avatar_path) ? trim($user->avatar_path) : '';
        if ($path === '') {
            return null;
        }

        return '/admin/users/'.rawurlencode((string) $user->id).'/avatar';
    }

    public function syncPhotoFromGraph(TenantUser $user, string $token, string $entraUserId): bool
    {
        if (! $this->hasAvatarColumns() || $token === '' || $entraUserId === '') {
            return false;
        }

        $photo = app(EntraGraphAppService::class)->fetchUserPhoto($token, $entraUserId);
        if ($photo === null) {
            return false;
        }

        $extension = match (strtolower($photo['content_type'])) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = 'identity/avatars/'.((string) $user->id).'.'.$extension;
        $disk = Storage::disk($this->disk());

        try {
            $disk->put($path, $photo['bytes']);
        } catch (\Throwable $exception) {
            Log::warning('Failed to store Entra avatar', [
                'user_id' => (string) $user->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $previous = is_string($user->avatar_path) ? trim($user->avatar_path) : '';
        if ($previous !== '' && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }

        $user->avatar_path = $path;
        if (Schema::connection('tenant')->hasColumn('users', 'avatar_synced_at')) {
            $user->avatar_synced_at = now();
        }

        return true;
    }

    public function streamAvatar(TenantUser $user): StreamedResponse
    {
        if (! $this->hasAvatarColumns()) {
            abort(404);
        }

        $path = is_string($user->avatar_path) ? trim($user->avatar_path) : '';
        $disk = Storage::disk($this->disk());
        if ($path === '' || ! $disk->exists($path)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }
}
