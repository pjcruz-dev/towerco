<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Local/ops check: APP_KEY usability + server clock (TOTP depends on both).
 */
final class TowerosMfaHealthCommand extends Command
{
    protected $signature = 'toweros:mfa-health
        {--json : Emit machine-readable JSON}';

    protected $description = 'Check APP_KEY fingerprint and server UTC time for MFA/TOTP diagnostics.';

    public function handle(): int
    {
        $key = (string) config('app.key');
        $keyPresent = $key !== '';
        $keyLooksValid = $keyPresent && (str_starts_with($key, 'base64:') || strlen($key) >= 32);
        $fingerprint = $keyPresent
            ? substr(hash('sha256', $key), 0, 12)
            : null;

        $roundTripOk = false;
        $roundTripError = null;
        if ($keyLooksValid) {
            try {
                $probe = 'toweros-mfa-health-'.Str::random(8);
                $roundTripOk = Crypt::decryptString(Crypt::encryptString($probe)) === $probe;
            } catch (\Throwable $e) {
                $roundTripError = $e->getMessage();
            }
        }

        $utc = now()->utc()->toIso8601String();
        $unix = time();

        $payload = [
            'ok' => $keyLooksValid && $roundTripOk,
            'app_key_present' => $keyPresent,
            'app_key_looks_valid' => $keyLooksValid,
            'app_key_fingerprint' => $fingerprint,
            'encrypt_round_trip_ok' => $roundTripOk,
            'encrypt_round_trip_error' => $roundTripError,
            'server_utc' => $utc,
            'server_unix' => $unix,
            'hints' => [
                'Pin APP_KEY in backend/.env (or Secrets Manager). Never regenerate after enrollments exist.',
                'TOTP allows ±30s; large host/container clock skew causes Invalid MFA code.',
                'Recovery codes survive APP_KEY rotation; authenticator secrets do not — users must re-enroll after key restore/re-encrypt.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $payload['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('TowerOS MFA health');
        $this->line('  APP_KEY present: '.($keyPresent ? 'yes' : 'no'));
        $this->line('  APP_KEY valid shape: '.($keyLooksValid ? 'yes' : 'no'));
        $this->line('  APP_KEY fingerprint: '.($fingerprint ?? 'n/a').' (compare across deploys; must stay stable)');
        $this->line('  Encrypt round-trip: '.($roundTripOk ? 'ok' : 'FAILED'));
        if ($roundTripError !== null) {
            $this->warn('  Encrypt error: '.$roundTripError);
        }
        $this->line('  Server UTC: '.$utc);
        $this->line('  Server unix: '.$unix);
        $this->newLine();
        foreach ($payload['hints'] as $hint) {
            $this->comment('  · '.$hint);
        }

        return $payload['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
