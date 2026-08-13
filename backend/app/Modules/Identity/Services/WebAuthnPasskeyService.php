<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\WebAuthnCredential;
use App\Modules\Identity\Support\WebAuthnRelyingParty;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use stdClass;

final class WebAuthnPasskeyService
{
    public function __construct(
        private readonly WebAuthnRelyingParty $relyingParty,
        private readonly WebAuthnChallengeStore $challenges,
        private readonly AuthAuditService $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(TenantUser $user): array
    {
        return WebAuthnCredential::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (WebAuthnCredential $row) => $row->toPublicRow())
            ->values()
            ->all();
    }

    /**
     * @return array{challenge_id: string, publicKey: array<string, mixed>, rp_id: string}
     */
    public function beginRegistration(TenantUser $user, ?string $label = null): array
    {
        $webauthn = $this->client(preferResidentKey: true);
        $exclude = WebAuthnCredential::query()
            ->where('user_id', $user->id)
            ->pluck('credential_id')
            ->map(static fn (string $id): string => self::base64UrlDecode($id))
            ->all();

        $args = $webauthn->getCreateArgs(
            (string) $user->id,
            (string) $user->email,
            (string) $user->name,
            60,
            true,
            'preferred',
            false,
            $exclude,
        );

        $challengeBinary = $webauthn->getChallenge()->getBinaryString();
        $challengeId = $this->challenges->put('register', [
            'user_id' => (string) $user->id,
            'challenge' => base64_encode($challengeBinary),
            'label' => $label,
            'rp_id' => $this->relyingParty->rpId(),
        ]);

        return [
            'challenge_id' => $challengeId,
            'publicKey' => $this->publicKeyToArray($args->publicKey),
            'rp_id' => $this->relyingParty->rpId(),
        ];
    }

    /**
     * @param  array<string, mixed>  $credential  Browser PublicKeyCredential JSON (create)
     */
    public function completeRegistration(TenantUser $user, string $challengeId, array $credential, ?string $label = null): WebAuthnCredential
    {
        $stored = $this->challenges->pull('register', $challengeId);
        if ($stored === null || ($stored['user_id'] ?? null) !== (string) $user->id) {
            throw ValidationException::withMessages([
                'challenge_id' => [__('Passkey registration challenge expired or is invalid. Start again.')],
            ]);
        }

        $response = is_array($credential['response'] ?? null) ? $credential['response'] : [];
        $clientDataJSON = $this->decodeClientBinary($response['clientDataJSON'] ?? null);
        $attestationObject = $this->decodeClientBinary($response['attestationObject'] ?? null);
        $challengeBinary = base64_decode((string) $stored['challenge'], true);
        if ($clientDataJSON === null || $attestationObject === null || $challengeBinary === false) {
            throw ValidationException::withMessages([
                'credential' => [__('Invalid passkey registration payload.')],
            ]);
        }

        try {
            $webauthn = $this->client();
            $this->assertChallengeRpId($stored);
            $this->assertClientDataOrigin($clientDataJSON);
            $data = $webauthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challengeBinary,
                false,
                true,
                false,
                false,
            );
        } catch (WebAuthnException $e) {
            $this->audit->log('auth.webauthn.register.failed', (string) $user->id, null, [
                'reason' => $e->getMessage(),
            ], 'medium');
            throw ValidationException::withMessages([
                'credential' => [__('Passkey registration failed: :reason', ['reason' => $e->getMessage()])],
            ]);
        }

        $credentialId = self::base64UrlEncode((string) $data->credentialId);
        if (WebAuthnCredential::query()->where('credential_id', $credentialId)->exists()) {
            throw ValidationException::withMessages([
                'credential' => [__('This passkey is already registered.')],
            ]);
        }

        $resolvedLabel = trim((string) ($label ?? $stored['label'] ?? ''));
        if ($resolvedLabel === '') {
            $resolvedLabel = 'Passkey';
        }

        $transports = [];
        if (isset($response['transports']) && is_array($response['transports'])) {
            $transports = array_values(array_filter($response['transports'], 'is_string'));
        }

        $row = WebAuthnCredential::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) $user->id,
            'credential_id' => $credentialId,
            'public_key' => (string) $data->credentialPublicKey,
            'sign_count' => (int) ($data->signatureCounter ?? 0),
            'transports' => $transports !== [] ? $transports : null,
            'attestation_format' => is_string($data->attestationFormat ?? null) ? $data->attestationFormat : null,
            'aaguid' => self::normalizeAaguid($data->AAGUID ?? null),
            'label' => Str::limit($resolvedLabel, 120, ''),
        ]);

        $this->audit->log('auth.webauthn.register', (string) $user->id, null, [
            'credential_id' => $row->id,
            'label' => $row->label,
        ]);

        return $row;
    }

    /**
     * @return array{challenge_id: string, publicKey: array<string, mixed>, rp_id: string}
     */
    public function beginLogin(?string $email = null): array
    {
        $credentialIds = [];
        $userId = null;

        if ($email !== null && trim($email) !== '') {
            $user = TenantUser::findByEmail(TenantUser::normalizeEmail($email));
            if ($user === null || ! $user->isActive()) {
                throw ValidationException::withMessages([
                    'email' => [__('No active account found for that email.')],
                ]);
            }
            $userId = (string) $user->id;
            $credentialIds = WebAuthnCredential::query()
                ->where('user_id', $user->id)
                ->pluck('credential_id')
                ->map(static fn (string $id): string => self::base64UrlDecode($id))
                ->all();

            if ($credentialIds === []) {
                throw ValidationException::withMessages([
                    'email' => [__('No passkey is registered for this account. Sign in with password or Microsoft first, then enroll a passkey.')],
                ]);
            }
        }

        $webauthn = $this->client();
        $args = $webauthn->getGetArgs(
            $credentialIds,
            60,
            true,
            true,
            true,
            true,
            true,
            'preferred',
        );

        $challengeBinary = $webauthn->getChallenge()->getBinaryString();
        $challengeId = $this->challenges->put('login', [
            'user_id' => $userId,
            'challenge' => base64_encode($challengeBinary),
            'rp_id' => $this->relyingParty->rpId(),
        ]);

        return [
            'challenge_id' => $challengeId,
            'publicKey' => $this->publicKeyToArray($args->publicKey),
            'rp_id' => $this->relyingParty->rpId(),
        ];
    }

    /**
     * @param  array<string, mixed>  $credential  Browser PublicKeyCredential JSON (get)
     * @return array{user: TenantUser, credential: WebAuthnCredential}
     */
    public function completeLogin(string $challengeId, array $credential): array
    {
        $stored = $this->challenges->pull('login', $challengeId);
        if ($stored === null) {
            throw ValidationException::withMessages([
                'challenge_id' => [__('Passkey login challenge expired or is invalid. Start again.')],
            ]);
        }

        $rawId = $credential['rawId'] ?? $credential['id'] ?? null;
        if (! is_string($rawId) || $rawId === '') {
            throw ValidationException::withMessages([
                'credential' => [__('Invalid passkey assertion.')],
            ]);
        }

        $credentialId = self::containsOnlyBase64Url($rawId)
            ? $rawId
            : self::base64UrlEncode(self::base64UrlDecode($rawId));

        // Browser may send standard base64 in id; normalize to base64url storage form.
        $credentialId = self::base64UrlEncode(self::base64UrlDecode($credentialId));

        $row = WebAuthnCredential::query()->where('credential_id', $credentialId)->first();
        if ($row === null) {
            // Try alternate encoding of rawId if client sent binary-as-base64.
            $binary = self::tryDecodeClientId($rawId);
            if ($binary !== null) {
                $row = WebAuthnCredential::query()
                    ->where('credential_id', self::base64UrlEncode($binary))
                    ->first();
            }
        }

        if ($row === null) {
            $this->audit->log('auth.webauthn.login.failed', null, null, [
                'reason' => 'unknown_credential',
            ], 'medium');
            throw ValidationException::withMessages([
                'credential' => [__('Unknown passkey. Sign in with password or Microsoft and enroll again.')],
            ]);
        }

        if (isset($stored['user_id']) && is_string($stored['user_id']) && $stored['user_id'] !== '' && $stored['user_id'] !== (string) $row->user_id) {
            throw ValidationException::withMessages([
                'credential' => [__('Passkey does not match the requested account.')],
            ]);
        }

        $user = TenantUser::query()->find($row->user_id);
        if (! $user instanceof TenantUser || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'credential' => [__('This account has been deactivated. Contact your administrator.')],
            ]);
        }

        $response = is_array($credential['response'] ?? null) ? $credential['response'] : [];
        $clientDataJSON = $this->decodeClientBinary($response['clientDataJSON'] ?? null);
        $authenticatorData = $this->decodeClientBinary($response['authenticatorData'] ?? null);
        $signature = $this->decodeClientBinary($response['signature'] ?? null);
        $challengeBinary = base64_decode((string) $stored['challenge'], true);

        if ($clientDataJSON === null || $authenticatorData === null || $signature === null || $challengeBinary === false) {
            throw ValidationException::withMessages([
                'credential' => [__('Invalid passkey assertion payload.')],
            ]);
        }

        try {
            $webauthn = $this->client();
            $this->assertChallengeRpId($stored);
            $this->assertClientDataOrigin($clientDataJSON);
            $webauthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $row->public_key,
                $challengeBinary,
                (int) $row->sign_count,
                false,
                true,
            );
            $newCount = $webauthn->getSignatureCounter();
        } catch (WebAuthnException $e) {
            $this->audit->log('auth.webauthn.login.failed', (string) $user->id, null, [
                'reason' => $e->getMessage(),
                'credential_row_id' => $row->id,
            ], 'medium');
            throw ValidationException::withMessages([
                'credential' => [__('Passkey verification failed: :reason', ['reason' => $e->getMessage()])],
            ]);
        }

        $row->sign_count = is_int($newCount) ? $newCount : (int) $row->sign_count;
        $row->last_used_at = now();
        $row->save();

        $this->audit->log('auth.webauthn.login', (string) $user->id, null, [
            'credential_id' => $row->id,
        ]);

        return ['user' => $user, 'credential' => $row];
    }

    public function revoke(TenantUser $actor, string $credentialRowId): void
    {
        $row = WebAuthnCredential::query()
            ->where('id', $credentialRowId)
            ->where('user_id', $actor->id)
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'id' => [__('Passkey not found.')],
            ]);
        }

        $row->delete();
        $this->audit->log('auth.webauthn.revoke', (string) $actor->id, null, [
            'credential_id' => $credentialRowId,
        ], 'medium');
    }

    /**
     * Admin: remove every passkey for a user (recovery / device loss).
     */
    public function revokeAllForUser(TenantUser $actor, TenantUser $target): int
    {
        $ids = WebAuthnCredential::query()
            ->where('user_id', $target->id)
            ->pluck('id')
            ->all();

        $count = WebAuthnCredential::query()
            ->where('user_id', $target->id)
            ->delete();

        $this->audit->log('auth.admin.webauthn_revoked', (string) $target->id, null, [
            'revoked_by' => (string) $actor->id,
            'revoked_count' => $count,
            'credential_ids' => $ids,
        ], 'high');

        return $count;
    }

    private function assertChallengeRpId(array $stored): void
    {
        $expected = $this->relyingParty->rpId();
        $storedRp = $stored['rp_id'] ?? null;
        if (! is_string($storedRp) || strtolower($storedRp) !== strtolower($expected)) {
            throw ValidationException::withMessages([
                'challenge_id' => [__('Passkey challenge is not valid for this organization host. Start again.')],
            ]);
        }
    }

    private function assertClientDataOrigin(string $clientDataJSON): void
    {
        $decoded = json_decode($clientDataJSON, true);
        $origin = is_array($decoded) ? ($decoded['origin'] ?? null) : null;
        if (! is_string($origin) || $origin === '') {
            throw ValidationException::withMessages([
                'credential' => [__('Passkey response is missing a browser origin.')],
            ]);
        }

        $allowed = $this->relyingParty->allowedOrigins();
        $normalized = rtrim($origin, '/');
        foreach ($allowed as $candidate) {
            if (strcasecmp(rtrim($candidate, '/'), $normalized) === 0) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'credential' => [__('Passkey origin is not allowed for this organization.')],
        ]);
    }

    private function client(bool $preferResidentKey = false): WebAuthn
    {
        unset($preferResidentKey);

        // Base64url encoding matches browser WebAuthn JSON expectations.
        return new WebAuthn(
            $this->relyingParty->rpName(),
            $this->relyingParty->rpId(),
            ['none', 'packed', 'apple', 'android-key', 'tpm', 'fido-u2f'],
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function publicKeyToArray(stdClass $publicKey): array
    {
        $encoded = json_encode($publicKey, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function decodeClientBinary(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $binary = self::tryDecodeClientId($value);

        return $binary;
    }

    private static function tryDecodeClientId(string $value): ?string
    {
        $asUrl = self::base64UrlDecode($value);
        if ($asUrl !== '') {
            return $asUrl;
        }

        $std = base64_decode($value, true);

        return $std === false ? null : $std;
    }

    private static function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private static function containsOnlyBase64Url(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-_]+$/', $value);
    }

    /**
     * Persist AAGUID as a UUID hex string (binary is not valid for MySQL utf8mb4 columns).
     */
    private static function normalizeAaguid(mixed $aaguid): ?string
    {
        if (! is_string($aaguid) || $aaguid === '') {
            return null;
        }

        // Already UUID-like
        if (preg_match('/^[0-9a-fA-F-]{32,36}$/', $aaguid) === 1) {
            return strtolower($aaguid);
        }

        $hex = bin2hex($aaguid);
        if (strlen($hex) !== 32) {
            return self::base64UrlEncode($aaguid);
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
