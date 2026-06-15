<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class PlatformTenantAuditEventType
{
    public const TENANT_PROVISIONED = 'tenant.provisioned';

    public const TENANT_ENVIRONMENT_PROVISIONED = 'tenant.environment_provisioned';

    public const TENANT_DELETED = 'tenant.deleted';

    public const TENANT_MFA_UPDATED = 'tenant.mfa.updated';

    public const TENANT_BRANDING_UPDATED = 'tenant.branding.updated';

    public const TENANT_MODULES_UPDATED = 'tenant.modules.updated';

    public const TENANT_BILLING_UPDATED = 'tenant.billing.updated';

    public const TENANT_PLAYBOOK_ASSIGNED = 'tenant.playbook.assigned';

    public const TENANT_IMPERSONATION_STARTED = 'tenant.impersonation.started';

    public const TENANT_ACCESS_UPDATED = 'tenant.access.updated';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TENANT_PROVISIONED,
            self::TENANT_ENVIRONMENT_PROVISIONED,
            self::TENANT_DELETED,
            self::TENANT_MFA_UPDATED,
            self::TENANT_BRANDING_UPDATED,
            self::TENANT_MODULES_UPDATED,
            self::TENANT_BILLING_UPDATED,
            self::TENANT_PLAYBOOK_ASSIGNED,
            self::TENANT_IMPERSONATION_STARTED,
            self::TENANT_ACCESS_UPDATED,
        ];
    }
}
