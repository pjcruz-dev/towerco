<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Who may start a request for a form. Missing config means everyone.
 *
 * Stored on e_approval_forms.metadata_json.request_access:
 * { "mode": "all"|"selected", "user_ids": ["uuid", ...] }
 */
final class EApprovalFormRequestAccessSupport
{
    public const MODE_ALL = 'all';

    public const MODE_SELECTED = 'selected';

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{mode: string, user_ids: list<string>}
     */
    public static function normalize(?array $metadata): array
    {
        $raw = is_array($metadata['request_access'] ?? null) ? $metadata['request_access'] : [];
        $mode = ($raw['mode'] ?? self::MODE_ALL) === self::MODE_SELECTED
            ? self::MODE_SELECTED
            : self::MODE_ALL;

        $ids = [];
        foreach (is_array($raw['user_ids'] ?? null) ? $raw['user_ids'] : [] as $id) {
            $value = trim((string) $id);
            if ($value === '' || ! Str::isUuid($value)) {
                continue;
            }
            $ids[$value] = $value;
        }

        return [
            'mode' => $mode,
            'user_ids' => array_values($ids),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null || ! array_key_exists('request_access', $metadata)) {
            return $metadata;
        }

        $access = self::normalize($metadata);
        $metadata['request_access'] = $access['mode'] === self::MODE_ALL
            ? ['mode' => self::MODE_ALL, 'user_ids' => []]
            : $access;

        return $metadata;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function viewerCanStart(TenantUser $viewer, ?array $metadata): bool
    {
        $access = self::normalize($metadata);
        if ($access['mode'] !== self::MODE_SELECTED) {
            return true;
        }

        return in_array((string) $viewer->id, $access['user_ids'], true);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function constrainVisibleTo(Builder $query, string $userId): void
    {
        $query->where(static function (Builder $outer) use ($userId): void {
            $outer->whereNull('metadata_json')
                ->orWhere('metadata_json->request_access->mode', self::MODE_ALL)
                ->orWhereNull('metadata_json->request_access->mode')
                ->orWhere(static function (Builder $selected) use ($userId): void {
                    $selected->where('metadata_json->request_access->mode', self::MODE_SELECTED)
                        ->whereJsonContains('metadata_json->request_access->user_ids', $userId);
                });
        });
    }
}
