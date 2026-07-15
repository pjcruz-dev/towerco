<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProcurementVendorInboxTokenService
{
    public function __construct(
        private readonly TenantAppUrlResolver $tenantUrls,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
    ) {}

    public function inboxEnabled(): bool
    {
        return (bool) ($this->scoringPolicy->policy()['vendor_inbox_enabled'] ?? true)
            && (bool) $this->scoringPolicy->policy()['vendor_portal_enabled'];
    }

    public function publicUrl(string $plainToken): string
    {
        return $this->tenantUrls->urlForCurrentTenant(
            '/public/procurement/vendor-inbox/'.$this->encodeAccessToken($plainToken),
        );
    }

    public function encodeAccessToken(string $plainToken): string
    {
        return rtrim(strtr(base64_encode($plainToken), '+/', '-_'), '=');
    }

    public function decodeAccessToken(string $accessToken): string
    {
        $normalized = strtr($accessToken, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false || $decoded === '') {
            throw ValidationException::withMessages([
                'token' => [__('Invalid vendor inbox link.')],
            ]);
        }

        return $decoded;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function ensureInboxUrl(ProcurementVendor $vendor): array
    {
        if (! $this->inboxEnabled()) {
            return ['', ''];
        }

        $existingPlain = $this->decryptStoredToken($vendor);
        if ($existingPlain !== null && $this->plainTokenMatchesHash($vendor, $existingPlain)) {
            return [$existingPlain, $this->publicUrl($existingPlain)];
        }

        $secret = Str::random(48);
        $plain = (string) $vendor->id.'.'.$secret;

        $vendor->portal_inbox_token_hash = hash('sha256', $secret);
        $vendor->portal_inbox_token_encrypted = encrypt($plain);
        $vendor->save();

        return [$plain, $this->publicUrl($plain)];
    }

    public function resolveVendor(string $accessToken): ProcurementVendor
    {
        if (! $this->inboxEnabled()) {
            throw ValidationException::withMessages([
                'token' => [__('Vendor inbox is not available.')],
            ]);
        }

        $plainToken = $this->decodeAccessToken($accessToken);
        $parts = explode('.', trim($plainToken), 2);
        if (count($parts) !== 2 || ! Str::isUuid($parts[0]) || $parts[1] === '') {
            throw ValidationException::withMessages([
                'token' => [__('Invalid vendor inbox link.')],
            ]);
        }

        /** @var ProcurementVendor|null $vendor */
        $vendor = ProcurementVendor::query()->find($parts[0]);
        if ($vendor === null || ! hash_equals((string) ($vendor->portal_inbox_token_hash ?? ''), hash('sha256', $parts[1]))) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid vendor inbox link.')],
            ]);
        }

        if (! $vendor->is_active) {
            throw ValidationException::withMessages([
                'token' => [__('This supplier account is inactive.')],
            ]);
        }

        return $vendor;
    }

    public function markOpened(ProcurementVendor $vendor): void
    {
        if ($vendor->portal_inbox_opened_at !== null) {
            return;
        }

        $vendor->portal_inbox_opened_at = now();
        $vendor->save();
    }

    private function decryptStoredToken(ProcurementVendor $vendor): ?string
    {
        $encrypted = $vendor->portal_inbox_token_encrypted;
        if ($encrypted === null || trim((string) $encrypted) === '') {
            return null;
        }

        try {
            $plain = decrypt((string) $encrypted);
        } catch (\Throwable) {
            return null;
        }

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    private function plainTokenMatchesHash(ProcurementVendor $vendor, string $plainToken): bool
    {
        $parts = explode('.', trim($plainToken), 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            return false;
        }

        $hash = (string) ($vendor->portal_inbox_token_hash ?? '');

        return $hash !== '' && hash_equals($hash, hash('sha256', $parts[1]));
    }
}
