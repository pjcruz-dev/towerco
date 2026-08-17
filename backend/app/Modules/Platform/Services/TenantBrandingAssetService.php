<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantBrandingAssetService
{
    public const KIND_LOGO = 'logo';

    public const KIND_FAVICON = 'favicon';

    private const MAX_BYTES = 512 * 1024;

    /** @var list<string> */
    private const LOGO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** @var list<string> */
    private const FAVICON_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'];

    public function __construct(
        private readonly PlatformTenantAuditLogger $platformAudit,
    ) {}

    public static function isKind(string $kind): bool
    {
        return $kind === self::KIND_LOGO || $kind === self::KIND_FAVICON;
    }

    public function hostedUrl(Tenant $tenant, string $kind): string
    {
        return '/api/v1/public/tenant-branding/'.$kind.'?tenant='.$tenant->id;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeForSave(Tenant $tenant, array $incoming): array
    {
        $existing = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];

        foreach ([self::KIND_LOGO, self::KIND_FAVICON] as $kind) {
            $urlKey = $kind.'_url';
            $assetKey = $kind.'_asset';
            $hosted = $this->hostedUrl($tenant, $kind);
            $nextUrl = is_string($incoming[$urlKey] ?? null) ? trim((string) $incoming[$urlKey]) : '';

            if ($nextUrl === $hosted) {
                $incoming[$assetKey] = is_string($incoming[$assetKey] ?? null) && $incoming[$assetKey] !== ''
                    ? $incoming[$assetKey]
                    : ($existing[$assetKey] ?? null);

                continue;
            }

            $this->deleteStoredAsset($tenant, $kind, is_string($existing[$assetKey] ?? null) ? (string) $existing[$assetKey] : null);
            unset($incoming[$assetKey]);
        }

        return TenantThemeTokensValidator::validate($incoming);
    }

    public function clear(Tenant $tenant): void
    {
        $existing = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];
        foreach ([self::KIND_LOGO, self::KIND_FAVICON] as $kind) {
            $assetKey = $kind.'_asset';
            $this->deleteStoredAsset($tenant, $kind, is_string($existing[$assetKey] ?? null) ? (string) $existing[$assetKey] : null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Tenant $tenant, UploadedFile $file, string $kind, ?User $actor): array
    {
        if (! self::isKind($kind)) {
            throw ValidationException::withMessages([
                'file' => [__('Unknown branding asset.')],
            ]);
        }

        $this->assertUploadAllowed($file, $kind);

        $existing = $this->baseTokens($tenant);
        $previousPath = is_string($existing[$kind.'_asset'] ?? null) ? (string) $existing[$kind.'_asset'] : null;
        $this->deleteStoredAsset($tenant, $kind, $previousPath);

        $extension = $this->extension($file);
        $directory = $this->directory($tenant);
        $filename = $kind.'.'.$extension;
        $storedPath = $directory.'/'.$filename;

        $stored = Storage::disk($this->disk())->putFileAs($directory, $file, $filename);
        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('Logo could not be stored. Check storage configuration and try again.')],
            ]);
        }

        $existing[$kind.'_asset'] = $storedPath;
        $existing[$kind.'_url'] = $this->hostedUrl($tenant, $kind);
        $existing['version'] = min(999, ((int) ($existing['version'] ?? 0)) + 1);

        $tokens = TenantThemeTokensValidator::validate($existing);
        $tenant->theme_tokens = $tokens;
        $tenant->save();

        $this->platformAudit->log(
            PlatformTenantAuditEventType::TENANT_BRANDING_UPDATED,
            $tenant,
            $actor,
            [
                'theme_tokens' => [
                    'from' => $kind.'_uploaded',
                    'to' => $tokens[$kind.'_url'] ?? null,
                ],
            ],
        );

        return $tokens;
    }

    public function stream(Tenant $tenant, string $kind): StreamedResponse
    {
        $path = $this->resolveStoredPath($tenant, $kind);
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

    public function resolveStoredPath(Tenant $tenant, string $kind): ?string
    {
        if (! self::isKind($kind)) {
            return null;
        }

        $tokens = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];
        $path = is_string($tokens[$kind.'_asset'] ?? null) ? trim((string) $tokens[$kind.'_asset']) : '';
        if ($path === '') {
            return null;
        }

        $prefix = $this->directory($tenant).'/';
        if (! str_starts_with($path, $prefix) || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function assertUploadAllowed(UploadedFile $file, string $kind): void
    {
        if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => [__('Image must be 512 KB or smaller.')],
            ]);
        }

        $extension = $this->extension($file);
        $allowed = $kind === self::KIND_FAVICON ? self::FAVICON_EXTENSIONS : self::LOGO_EXTENSIONS;
        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => [__('Upload a PNG, JPEG, GIF, or WebP image. ICO is allowed for favicon.')],
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

    /**
     * @return array<string, mixed>
     */
    private function baseTokens(Tenant $tenant): array
    {
        $defaults = [
            'version' => 1,
            'logo_url' => null,
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ];

        $existing = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];

        return array_merge($defaults, $existing);
    }

    private function directory(Tenant $tenant): string
    {
        return 'platform/tenant-branding/'.$tenant->id;
    }

    private function deleteStoredAsset(Tenant $tenant, string $kind, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $prefix = $this->directory($tenant).'/';
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
