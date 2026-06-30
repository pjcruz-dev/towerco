<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class DocumentsNotificationCategory
{
    public static function forType(string $type): string
    {
        return match ($type) {
            'document_expiring' => 'action',
            default => 'update',
        };
    }

    public static function hrefFor(?string $subjectId, ?string $siteId = null): string
    {
        if ($siteId !== null && $siteId !== '') {
            return '/sites/'.$siteId;
        }

        return '/documents';
    }
}
