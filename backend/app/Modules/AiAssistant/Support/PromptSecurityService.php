<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Redacts likely secrets from user text before prompting or logging.
 */
final class PromptSecurityService
{
    private const SECRET_PATTERNS = [
        '/(?i)\b(api[_-]?key|secret|password|passwd|token|bearer|authorization)\b\s*[:=]\s*[\'"]?[^\s\'"]{8,}/',
        '/(?i)\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/',
        '/(?i)\bsk-[A-Za-z0-9]{16,}/',
        '/(?i)\bAKIA[0-9A-Z]{16}/',
    ];

    public function sanitizeUserText(string $text): string
    {
        $sanitized = $text;
        foreach (self::SECRET_PATTERNS as $pattern) {
            $sanitized = preg_replace($pattern, '[REDACTED]', $sanitized) ?? $sanitized;
        }

        return trim($sanitized);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeLogPayload(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $out[$key] = $this->sanitizeUserText($value);

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $out[$key] = $this->sanitizeLogPayload($value);

                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
