<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantPersonalAccessToken;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Long-lived integration API keys (Sanctum tokens with ability "integration").
 * Keys inherit the minting user's Spatie permissions for Dynamic Entities access.
 */
final class TenantIntegrationApiKeyService
{
    public const ABILITY = 'integration';

    public const NAME_PREFIX = 'integration:';

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   token_preview: string,
     *   created_by_id: string,
     *   created_by_name: string|null,
     *   created_by_email: string|null,
     *   created_at: string|null,
     *   last_used_at: string|null,
     *   expires_at: string|null,
     *   status: string
     * }>
     */
    public function listForTenant(): array
    {
        $tokens = TenantPersonalAccessToken::query()
            ->where('tokenable_type', TenantUser::class)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TenantPersonalAccessToken $token): bool => $this->isIntegrationToken($token))
            ->values();

        $userIds = $tokens->pluck('tokenable_id')->unique()->filter()->values()->all();
        /** @var Collection<string, TenantUser> $users */
        $users = TenantUser::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy(fn (TenantUser $u): string => (string) $u->id);

        return $tokens->map(function (TenantPersonalAccessToken $token) use ($users): array {
            $owner = $users->get((string) $token->tokenable_id);
            $displayName = $this->displayName($token);

            return [
                'id' => (int) $token->id,
                'name' => $displayName,
                'token_preview' => $this->preview($token),
                'created_by_id' => (string) $token->tokenable_id,
                'created_by_name' => $owner?->name,
                'created_by_email' => $owner?->email,
                'created_at' => optional($token->created_at)?->toIso8601String(),
                'last_used_at' => optional($token->last_used_at)?->toIso8601String(),
                'expires_at' => optional($token->expires_at)?->toIso8601String(),
                'status' => $this->status($token, $owner),
            ];
        })->all();
    }

    /**
     * @return array{id: int, name: string, plain_text_token: string, token_preview: string, created_at: string|null}
     */
    public function create(TenantUser $actor, string $name): array
    {
        if (! $actor->isActive()) {
            throw ValidationException::withMessages([
                'name' => [__('Cannot mint an API key for an inactive user.')],
            ]);
        }

        $label = trim($name);
        if ($label === '') {
            throw ValidationException::withMessages([
                'name' => [__('Token identity is required.')],
            ]);
        }
        if (mb_strlen($label) > 120) {
            throw ValidationException::withMessages([
                'name' => [__('Token identity must be 120 characters or fewer.')],
            ]);
        }

        /** @var NewAccessToken $new */
        $new = $actor->createToken(
            self::NAME_PREFIX.$label,
            [self::ABILITY],
        );

        /** @var TenantPersonalAccessToken $token */
        $token = $new->accessToken;

        return [
            'id' => (int) $token->id,
            'name' => $label,
            'plain_text_token' => $new->plainTextToken,
            'token_preview' => $this->preview($token),
            'created_at' => optional($token->created_at)?->toIso8601String(),
        ];
    }

    public function revoke(int $tokenId): void
    {
        $token = TenantPersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('tokenable_type', TenantUser::class)
            ->firstOrFail();

        if (! $this->isIntegrationToken($token)) {
            throw ValidationException::withMessages([
                'token' => [__('That token is not an integration API key.')],
            ]);
        }

        $token->delete();
    }

    public function isIntegrationToken(TenantPersonalAccessToken $token): bool
    {
        $abilities = $token->abilities ?? [];
        if (! is_array($abilities)) {
            return false;
        }

        return in_array(self::ABILITY, $abilities, true);
    }

    public function revokeSessionTokensOnly(TenantUser $user): void
    {
        $user->tokens->each(function ($token): void {
            if ($token instanceof TenantPersonalAccessToken && $this->isIntegrationToken($token)) {
                return;
            }
            $token->delete();
        });
    }

    private function displayName(TenantPersonalAccessToken $token): string
    {
        $name = (string) $token->name;
        if (str_starts_with($name, self::NAME_PREFIX)) {
            return substr($name, strlen(self::NAME_PREFIX));
        }

        return $name;
    }

    private function preview(TenantPersonalAccessToken $token): string
    {
        $hash = (string) $token->token;
        $prefix = substr($hash, 0, 8);

        return $prefix !== '' ? $prefix.'••••••••' : '••••••••';
    }

    private function status(TenantPersonalAccessToken $token, ?TenantUser $owner): string
    {
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return 'expired';
        }
        if ($owner === null || ! $owner->isActive()) {
            return 'owner_inactive';
        }

        return 'operational';
    }
}
