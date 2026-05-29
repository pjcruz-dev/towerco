<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

final class RolloutFileContext
{
    public const CANDIDATE_PHOTO = 'candidate_photo';

    public const HUNTING_LOG = 'hunting_log';

    public const CME_REPORT = 'cme_report';

    public const LEASE_DOCUMENT = 'lease_document';

    public const APPROVAL_ATTACHMENT = 'approval_attachment';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CANDIDATE_PHOTO,
            self::HUNTING_LOG,
            self::CME_REPORT,
            self::LEASE_DOCUMENT,
            self::APPROVAL_ATTACHMENT,
        ];
    }

    public static function permissionFor(string $context): string
    {
        return match ($context) {
            self::CME_REPORT => 'project_one:cme:manage',
            self::APPROVAL_ATTACHMENT => 'project_one:manage',
            default => 'project_one:saq:manage',
        };
    }
}
