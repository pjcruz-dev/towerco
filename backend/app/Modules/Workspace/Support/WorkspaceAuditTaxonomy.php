<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Support;

/**
 * Operational taxonomy for workspace audit events.
 *
 * Categories: security | access | data_change | lifecycle
 * Severities: low | medium | high | critical
 */
final class WorkspaceAuditTaxonomy
{
    public const CATEGORY_SECURITY = 'security';

    public const CATEGORY_ACCESS = 'access';

    public const CATEGORY_DATA_CHANGE = 'data_change';

    public const CATEGORY_LIFECYCLE = 'lifecycle';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_SECURITY,
            self::CATEGORY_ACCESS,
            self::CATEGORY_DATA_CHANGE,
            self::CATEGORY_LIFECYCLE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function severities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ];
    }

    /**
     * @return array{category: string, severity: string}
     */
    public static function classify(string $module, string $action): array
    {
        $action = trim($action);
        $module = trim($module);

        if (str_starts_with($action, 'auth.') || str_contains($action, 'impersonation') || str_contains($action, 'mfa')) {
            return [
                'category' => self::CATEGORY_SECURITY,
                'severity' => match (true) {
                    str_contains($action, 'impersonation'),
                    str_contains($action, 'recovery_codes'),
                    str_contains($action, 'recovery.verified') => self::SEVERITY_CRITICAL,
                    str_contains($action, 'failed'),
                    str_contains($action, 'revoked'),
                    str_contains($action, 'logout_all') => self::SEVERITY_HIGH,
                    default => self::SEVERITY_MEDIUM,
                },
            ];
        }

        if (str_starts_with($action, 'rbac.') || str_contains($action, 'access_updated') || ($module === 'team_access' && str_contains($action, 'role'))) {
            return [
                'category' => self::CATEGORY_ACCESS,
                'severity' => match (true) {
                    str_contains($action, 'deleted'),
                    str_contains($action, 'deactivated') => self::SEVERITY_HIGH,
                    str_contains($action, 'permissions_updated'),
                    str_contains($action, 'user_updated') => self::SEVERITY_MEDIUM,
                    default => self::SEVERITY_LOW,
                },
            ];
        }

        if (
            str_contains($action, 'updated')
            || str_contains($action, 'metadata')
            || str_contains($action, 'form_updated')
            || str_contains($action, 'form_logo')
        ) {
            return [
                'category' => self::CATEGORY_DATA_CHANGE,
                'severity' => self::SEVERITY_LOW,
            ];
        }

        // Default: operational lifecycle
        $severity = match (true) {
            str_contains($action, 'rejected'),
            str_contains($action, 'voided'),
            str_contains($action, 'cancelled') && $module === 'procurement_one' => self::SEVERITY_HIGH,
            str_contains($action, 'approved_final'),
            str_contains($action, 'resolved'),
            str_contains($action, 'obsolete') => self::SEVERITY_MEDIUM,
            default => self::SEVERITY_LOW,
        };

        return [
            'category' => self::CATEGORY_LIFECYCLE,
            'severity' => $severity,
        ];
    }

    public static function actionFamily(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return 'other';
        }

        if (str_contains($action, '.')) {
            return explode('.', $action, 2)[0];
        }

        if (str_contains($action, '_')) {
            return explode('_', $action, 2)[0];
        }

        return $action;
    }

    public static function normalizeCategory(?string $category): ?string
    {
        if ($category === null || $category === '' || $category === 'all') {
            return null;
        }

        return in_array($category, self::categories(), true) ? $category : null;
    }

    public static function normalizeSeverity(?string $severity): ?string
    {
        if ($severity === null || $severity === '' || $severity === 'all') {
            return null;
        }

        return in_array($severity, self::severities(), true) ? $severity : null;
    }
}
