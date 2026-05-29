<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1, int $period = 30): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / $period);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->at($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function at(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $binaryTime = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $chunk = substr($hash, $offset, 4);
        $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
        $mod = $value % 1_000_000;

        return str_pad((string) $mod, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        $alphabet = array_flip(str_split(self::BASE32_ALPHABET));

        foreach (str_split($secret) as $char) {
            if (! isset($alphabet[$char])) {
                continue;
            }

            $buffer = ($buffer << 5) | $alphabet[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}

