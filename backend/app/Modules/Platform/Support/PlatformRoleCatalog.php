<?php



declare(strict_types=1);



namespace App\Modules\Platform\Support;



final class PlatformRoleCatalog

{

    public const ROLE_SUPERADMIN = 'superadmin';



    public const ROLE_BILLING = 'billing';



    public const ROLE_SUPPORT = 'support';



    public const ROLE_VIEWER = 'viewer';



    public const PERM_CONSOLE_VIEW = 'platform.console.view';



    public const PERM_TENANTS_VIEW = 'platform.tenants.view';



    public const PERM_TENANTS_MANAGE = 'platform.tenants.manage';



    public const PERM_TENANTS_DELETE = 'platform.tenants.delete';



    public const PERM_TENANTS_IMPERSONATE = 'platform.tenants.impersonate';



    public const PERM_BILLING_VIEW = 'platform.billing.view';



    public const PERM_BILLING_MANAGE = 'platform.billing.manage';



    public const PERM_PLAYBOOKS_VIEW = 'platform.playbooks.view';



    public const PERM_PLAYBOOKS_MANAGE = 'platform.playbooks.manage';



    public const PERM_OPERATORS_VIEW = 'platform.operators.view';



    public const PERM_OPERATORS_MANAGE = 'platform.operators.manage';



    public const PERM_AUDIT_VIEW = 'platform.audit.view';



    /**

     * @return list<string>

     */

    public function roles(): array

    {

        return [

            self::ROLE_SUPERADMIN,

            self::ROLE_BILLING,

            self::ROLE_SUPPORT,

            self::ROLE_VIEWER,

        ];

    }



    /**

     * @return list<string>

     */

    public function allPermissions(): array

    {

        return [

            self::PERM_CONSOLE_VIEW,

            self::PERM_TENANTS_VIEW,

            self::PERM_TENANTS_MANAGE,

            self::PERM_TENANTS_DELETE,

            self::PERM_TENANTS_IMPERSONATE,

            self::PERM_BILLING_VIEW,

            self::PERM_BILLING_MANAGE,

            self::PERM_PLAYBOOKS_VIEW,

            self::PERM_PLAYBOOKS_MANAGE,

            self::PERM_OPERATORS_VIEW,

            self::PERM_OPERATORS_MANAGE,

            self::PERM_AUDIT_VIEW,

        ];

    }



    /**

     * @return list<string>

     */

    public function permissionsForRole(string $role): array

    {

        return match ($this->normalizeRole($role)) {

            self::ROLE_SUPERADMIN => $this->allPermissions(),

            self::ROLE_BILLING => [

                self::PERM_CONSOLE_VIEW,

                self::PERM_TENANTS_VIEW,

                self::PERM_TENANTS_MANAGE,

                self::PERM_BILLING_VIEW,

                self::PERM_BILLING_MANAGE,

            ],

            self::ROLE_SUPPORT => [

                self::PERM_CONSOLE_VIEW,

                self::PERM_TENANTS_VIEW,

                self::PERM_TENANTS_MANAGE,

                self::PERM_TENANTS_IMPERSONATE,

                self::PERM_PLAYBOOKS_VIEW,

                self::PERM_AUDIT_VIEW,

            ],

            self::ROLE_VIEWER => [

                self::PERM_CONSOLE_VIEW,

                self::PERM_TENANTS_VIEW,

                self::PERM_AUDIT_VIEW,

            ],

            default => [],

        };

    }



    public function roleHasPermission(string $role, string $permission): bool

    {

        $normalizedRole = $this->normalizeRole($role);



        if ($normalizedRole === self::ROLE_SUPERADMIN) {

            return true;

        }



        $permissions = $this->permissionsForRole($normalizedRole);



        if (in_array($permission, $permissions, true)) {

            return true;

        }



        return $this->managePermissionImpliesView($permissions, $permission);

    }



    public function normalizeRole(?string $role): string

    {

        $normalized = strtolower(trim((string) $role));



        return in_array($normalized, $this->roles(), true)

            ? $normalized

            : self::ROLE_VIEWER;

    }



    /**

     * @param  list<string>  $permissions

     */

    private function managePermissionImpliesView(array $permissions, string $permission): bool

    {

        $impliedBy = match ($permission) {

            self::PERM_TENANTS_VIEW => self::PERM_TENANTS_MANAGE,

            self::PERM_BILLING_VIEW => self::PERM_BILLING_MANAGE,

            self::PERM_PLAYBOOKS_VIEW => self::PERM_PLAYBOOKS_MANAGE,

            default => null,

        };



        return $impliedBy !== null && in_array($impliedBy, $permissions, true);

    }

}


