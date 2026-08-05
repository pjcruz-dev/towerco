<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Decrypt MFA TOTP secrets; map APP_KEY / ciphertext failures to actionable API errors.
 */
final class MfaSecretCipher
{
    public static function decryptOrFail(string $encrypted, string $context = 'mfa'): string
    {
        try {
            return decrypt($encrypted);
        } catch (DecryptException $e) {
            Log::warning('MFA secret decrypt failed; APP_KEY may have rotated or ciphertext is corrupt.', [
                'context' => $context,
                'exception' => $e::class,
            ]);

            throw ValidationException::withMessages([
                'code' => [__(
                    'MFA authenticator secret is unreadable (encryption key mismatch). Use a recovery code, then re-enroll MFA in Security settings.'
                )],
            ]);
        }
    }
}
