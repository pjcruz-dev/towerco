<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;
use Laravel\Sanctum\Contracts\HasAbilities;

final class TenantImpersonationContextResolver
{
    public function fromRequest(Request $request): ?TenantImpersonationContext
    {

        $user = $request->user();

        if (! $user instanceof TenantUser) {

            return null;

        }

        $token = $user->currentAccessToken();

        if (! $token instanceof HasAbilities) {

            return null;

        }

        $abilities = $this->normalizeAbilities($token->abilities ?? null);

        $platformImpersonatorId = $this->abilityValue($abilities, 'platform_impersonator:');

        $tenantImpersonatorId = $this->abilityValue($abilities, 'impersonator:');

        $sessionId = (string) $request->attributes->get('auth_session_id', '');

        if ($sessionId === '') {

            $sessionId = $this->abilityValue($abilities, 'session:') ?? '';

        }

        if ($sessionId === '') {

            $sessionId = trim((string) $request->header('X-Session-Id', ''));

        }

        if ($sessionId === '') {

            return null;

        }

        if ($platformImpersonatorId !== null) {

            /** @var User|null $platformActor */
            $platformActor = User::query()->find($platformImpersonatorId);

            if (! $platformActor) {

                return null;

            }

            return new TenantImpersonationContext(

                $sessionId,

                null,

                [

                    'id' => (string) $platformActor->id,

                    'name' => (string) $platformActor->name,

                    'email' => (string) $platformActor->email,

                    'source' => 'platform',

                ],

            );

        }

        if ($tenantImpersonatorId === null) {

            return null;

        }

        /** @var TenantUser|null $impersonator */
        $impersonator = TenantUser::query()->find($tenantImpersonatorId);

        if (! $impersonator) {

            return null;

        }

        return new TenantImpersonationContext($sessionId, $impersonator);

    }

    /**
     * @param  list<string>  $abilities
     */
    private function abilityValue(array $abilities, string $prefix): ?string
    {
        foreach ($abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, $prefix)) {
                $value = substr($ability, strlen($prefix));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Sanctum may return false or "*" for tokens without explicit ability lists.
     *
     * @return list<string>
     */
    private function normalizeAbilities(mixed $abilities): array
    {
        if (is_array($abilities)) {
            return $abilities;
        }

        if ($abilities === '*' || $abilities === true) {
            return ['*'];
        }

        return [];
    }
}
