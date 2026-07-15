<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProcurementRfqVendorInvitationService
{
    public function __construct(
        private readonly TenantAppUrlResolver $tenantUrls,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
    ) {}

    public function portalEnabled(): bool
    {
        return (bool) $this->scoringPolicy->policy()['vendor_portal_enabled'];
    }

    public function publicUrl(string $plainToken): string
    {
        return $this->tenantUrls->urlForCurrentTenant(
            '/public/procurement/rfq-quotes/'.$this->encodeAccessToken($plainToken),
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
                'token' => [__('Invalid quotation link.')],
            ]);
        }

        return $decoded;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function issueToken(ProcurementRfqVendor $invitation, ?ProcurementRfq $rfq = null, bool $rotate = false): array
    {
        $rfq ??= $invitation->rfq;

        if (! $rotate) {
            $existingPlain = $this->decryptStoredToken($invitation);
            if ($existingPlain !== null && $this->plainTokenMatchesHash($invitation, $existingPlain)) {
                $invitation->invitation_token_expires_at = $this->tokenExpiresAt($rfq);
                $invitation->save();

                return [$existingPlain, $this->publicUrl($existingPlain)];
            }
        }

        $secret = Str::random(48);
        $plain = (string) $invitation->id.'.'.$secret;

        $invitation->invitation_token_hash = hash('sha256', $secret);
        $invitation->invitation_token_encrypted = encrypt($plain);
        $invitation->invitation_token_expires_at = $this->tokenExpiresAt($rfq);
        $invitation->save();

        return [$plain, $this->publicUrl($plain)];
    }

    public function resolveActiveInvitation(string $accessToken): ProcurementRfqVendor
    {
        $plainToken = $this->decodeAccessToken($accessToken);
        $parts = explode('.', trim($plainToken), 2);
        if (count($parts) !== 2 || ! Str::isUuid($parts[0]) || $parts[1] === '') {
            throw ValidationException::withMessages([
                'token' => [__('Invalid quotation link.')],
            ]);
        }

        /** @var ProcurementRfqVendor|null $invitation */
        $invitation = ProcurementRfqVendor::query()
            ->with(['vendor', 'rfq.lines'])
            ->find($parts[0]);

        if ($invitation === null || ! hash_equals((string) $invitation->invitation_token_hash, hash('sha256', $parts[1]))) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid quotation link.')],
            ]);
        }

        if ($invitation->invitation_token_expires_at !== null && now()->greaterThan($invitation->invitation_token_expires_at)) {
            throw ValidationException::withMessages([
                'token' => [__('This quotation link has expired.')],
            ]);
        }

        return $invitation;
    }

    public function resolveRfq(ProcurementRfqVendor $invitation): ProcurementRfq
    {
        $rfq = $invitation->rfq;
        if ($rfq === null) {
            throw ValidationException::withMessages([
                'token' => [__('Request for quotation not found.')],
            ]);
        }

        return $rfq;
    }

    public function quoteSubmissionBlockedReason(ProcurementRfq $rfq): ?string
    {
        if ((string) $rfq->status === ProcurementRfqStatus::DRAFT) {
            return __('Bidding has not opened yet. The buyer will publish this RFQ before quotes can be submitted.');
        }

        if ((string) $rfq->status !== ProcurementRfqStatus::OPEN) {
            return __('This RFQ is not accepting quotations.');
        }

        if ($rfq->bidding_opens_at !== null && now()->lessThan($rfq->bidding_opens_at)) {
            return __('Bidding has not opened yet.');
        }

        if ($rfq->bidding_closes_at !== null && now()->greaterThan($rfq->bidding_closes_at)) {
            return __('The bidding window has closed.');
        }

        return null;
    }

    public function assertAcceptingQuotes(ProcurementRfqVendor $invitation): ProcurementRfq
    {
        $rfq = $this->resolveRfq($invitation);

        $blockedReason = $this->quoteSubmissionBlockedReason($rfq);
        if ($blockedReason !== null) {
            throw ValidationException::withMessages([
                'rfq' => [$blockedReason],
            ]);
        }

        return $rfq;
    }

    public function markOpened(ProcurementRfqVendor $invitation): void
    {
        if ($invitation->invitation_opened_at !== null) {
            return;
        }

        $invitation->invitation_opened_at = now();
        $invitation->save();
    }

    private function tokenExpiresAt(?ProcurementRfq $rfq): ?\Illuminate\Support\Carbon
    {
        if ($rfq?->bidding_closes_at !== null) {
            return $rfq->bidding_closes_at->copy();
        }

        return now()->addDays(30);
    }

    private function decryptStoredToken(ProcurementRfqVendor $invitation): ?string
    {
        $encrypted = $invitation->invitation_token_encrypted;
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

    private function plainTokenMatchesHash(ProcurementRfqVendor $invitation, string $plainToken): bool
    {
        $parts = explode('.', trim($plainToken), 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            return false;
        }

        $hash = (string) ($invitation->invitation_token_hash ?? '');

        return $hash !== '' && hash_equals($hash, hash('sha256', $parts[1]));
    }
}
