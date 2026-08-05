<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Support\MfaSecretCipher;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MfaSecretCipherTest extends TestCase
{
    #[Test]
    public function decrypt_or_fail_returns_plaintext_for_valid_ciphertext(): void
    {
        $plain = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $encrypted = encrypt($plain);

        $this->assertSame($plain, MfaSecretCipher::decryptOrFail($encrypted, 'test'));
    }

    #[Test]
    public function decrypt_or_fail_maps_corrupt_ciphertext_to_actionable_validation_error(): void
    {
        try {
            MfaSecretCipher::decryptOrFail('not-a-valid-laravel-payload', 'test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $messages = $e->errors()['code'] ?? [];
            $this->assertNotEmpty($messages);
            $this->assertStringContainsString('unreadable', strtolower((string) $messages[0]));
            $this->assertStringContainsString('recovery', strtolower((string) $messages[0]));
        }
    }
}
