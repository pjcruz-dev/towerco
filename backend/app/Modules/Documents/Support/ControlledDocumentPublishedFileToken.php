<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

/**
 * A link token for one controlled document. It names the document, not a revision,
 * so opening it always resolves the revision that is published now.
 */
final class ControlledDocumentPublishedFileToken
{
    public static function sign(string $documentId, ?string $tenantId = null, ?string $key = null): string
    {
        return hash_hmac('sha256', self::payload($documentId, $tenantId), self::key($key));
    }

    public static function matches(string $documentId, string $token, ?string $tenantId = null, ?string $key = null): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return hash_equals(self::sign($documentId, $tenantId, $key), $token);
    }

    private static function payload(string $documentId, ?string $tenantId): string
    {
        $tenantId ??= (string) (tenant()?->getTenantKey() ?? '');

        return $tenantId.'|'.$documentId;
    }

    private static function key(?string $key): string
    {
        return $key ?? (string) config('app.key');
    }
}
