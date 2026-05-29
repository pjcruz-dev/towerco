<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

final class RolloutEmailNotificationRecipients
{
    public const CURRENT_APPROVER = 'current_approver';

    public const REQUESTER = 'requester';

    public const PMO_OWNER = 'pmo_owner';

    public const SAQ_OWNER = 'saq_owner';

    public const CME_LEAD = 'cme_lead';

    /** @var list<string> */
    public const ALL = [
        self::CURRENT_APPROVER,
        self::REQUESTER,
        self::PMO_OWNER,
        self::SAQ_OWNER,
        self::CME_LEAD,
    ];

    /**
     * @param  list<mixed>  $recipients
     * @return list<string>
     */
    public static function sanitize(array $recipients): array
    {
        $allowed = array_fill_keys(self::ALL, true);
        $out = [];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            $key = strtolower(trim($recipient));
            if ($key === '' || ! isset($allowed[$key])) {
                continue;
            }

            if (! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }
}
