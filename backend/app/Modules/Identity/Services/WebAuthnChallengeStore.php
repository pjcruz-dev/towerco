<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived WebAuthn ceremony challenges (register / login), tenant-scoped via CacheTenancyBootstrapper.
 */
final class WebAuthnChallengeStore
{
    private const TTL_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $purpose, array $payload): string
    {
        $id = (string) Str::uuid();
        Cache::put($this->key($purpose, $id), $payload, self::TTL_SECONDS);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(string $purpose, string $challengeId): ?array
    {
        $key = $this->key($purpose, $challengeId);
        $payload = Cache::pull($key);

        return is_array($payload) ? $payload : null;
    }

    private function key(string $purpose, string $challengeId): string
    {
        return 'toweros:webauthn:'.$purpose.':'.$challengeId;
    }
}
