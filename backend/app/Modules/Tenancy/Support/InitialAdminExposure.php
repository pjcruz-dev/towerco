<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * Shapes initial-admin payloads for HTTP/CLI so production defaults do not leak generated passwords.
 */
final class InitialAdminExposure
{
    /**
     * @param  array{email: string, password: string, password_generated: bool}  $internal
     * @return array<string, mixed>
     */
    public static function forTransport(array $internal): array
    {
        $expose = (bool) config('toweros.tenant_bootstrap_expose_password_in_api');

        $payload = [
            'email' => $internal['email'],
            'password_generated' => $internal['password_generated'],
        ];

        if ($expose) {
            $payload['password'] = $internal['password'];

            return $payload;
        }

        if ($internal['password_generated']) {
            $payload['password'] = null;
            $payload['password_redacted'] = true;
            $payload['hint'] = __(
                'A random password was set. It is not returned in this environment. Use a password reset flow, '.
                'temporarily set TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API=true, or set TOWEROS_TENANT_BOOTSTRAP_ADMIN_PASSWORD for a known bootstrap secret.',
            );
        } else {
            $payload['password'] = null;
            $payload['password_from_environment'] = true;
            $payload['hint'] = __(
                'Password was taken from TOWEROS_TENANT_BOOTSTRAP_ADMIN_PASSWORD and is not repeated in the API response.',
            );
        }

        return $payload;
    }
}
