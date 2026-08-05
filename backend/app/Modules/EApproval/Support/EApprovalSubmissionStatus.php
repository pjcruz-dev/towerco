<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalSubmissionStatus
{
    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const RETURNED = 'returned';

    public const AWAITING_DCF = 'awaiting_dcf';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::RETURNED,
            self::AWAITING_DCF,
        ];
    }

    /**
     * @return list<string>
     */
    public static function open(): array
    {
        return [self::PENDING, self::RETURNED, self::AWAITING_DCF];
    }

    public static function label(string $status): string
    {
        return match (strtolower(trim($status))) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Needs revision',
            self::AWAITING_DCF => 'Awaiting document control',
            default => self::titleCaseStatus($status),
        };
    }

    /**
     * Resolve submission status codes that match a free-text search needle
     * (raw codes, friendly labels, and common aliases).
     *
     * @return list<string>
     */
    public static function statusesMatching(string $search): array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '' || mb_strlen($needle) < 2) {
            return [];
        }

        $aliases = [
            self::DRAFT => ['draft'],
            self::PENDING => ['pending', 'in progress', 'awaiting approval'],
            self::APPROVED => ['approved', 'complete', 'completed'],
            self::REJECTED => ['rejected', 'reject'],
            self::CANCELLED => ['cancelled', 'canceled', 'cancel'],
            self::RETURNED => ['returned', 'needs revision', 'revision', 'revise', 'revise request'],
            self::AWAITING_DCF => [
                'awaiting_dcf',
                'awaiting dcf',
                'document control',
                'dcf',
                'awaiting document control',
            ],
        ];

        $matched = [];
        foreach (self::all() as $code) {
            $candidates = array_values(array_unique([
                $code,
                mb_strtolower(self::label($code)),
                ...($aliases[$code] ?? []),
            ]));

            foreach ($candidates as $candidate) {
                if ($candidate === '' || mb_strlen($candidate) < 2) {
                    continue;
                }
                if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                    $matched[] = $code;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    private static function titleCaseStatus(string $status): string
    {
        $key = strtolower(trim($status));
        if ($key === '') {
            return '';
        }

        $parts = preg_split('/[_\s-]+/', $key) ?: [];
        $titled = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $titled[] = mb_strtoupper(mb_substr($part, 0, 1)).mb_substr($part, 1);
        }

        return implode(' ', $titled);
    }
}
