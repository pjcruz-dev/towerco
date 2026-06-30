<?php

return [

    'api' => [
        'current_version' => 'v1',
        'prefix' => 'api',
    ],

    'queues' => [
        'default' => env('TOWEROS_QUEUE_DEFAULT', 'toweros-default'),
        'tenant' => env('TOWEROS_QUEUE_TENANT', 'toweros-tenant'),
        'notifications' => env('TOWEROS_QUEUE_NOTIFICATIONS', 'toweros-notifications'),
        'integrations' => env('TOWEROS_QUEUE_INTEGRATIONS', 'toweros-integrations'),
        'webhooks' => env('TOWEROS_QUEUE_WEBHOOKS', 'toweros-webhooks'),
    ],

    'logging' => [
        'application_channel' => env('TOWEROS_LOG_CHANNEL', 'stack'),
        'audit_channel' => env('TOWEROS_AUDIT_LOG_CHANNEL', 'audit'),
        /** Mirror platform audit rows to structured JSON logs (Loki / OpenSearch / CloudWatch via sidecar). */
        'audit_structured_enabled' => env('TOWEROS_AUDIT_STRUCTURED_ENABLED', true),
    ],

    'platform_mfa' => [
        'required' => env('TOWEROS_PLATFORM_MFA_REQUIRED', false),
    ],

    /**
     * Explicit browser Origin allowlist for Laravel CORS (merged at runtime).
     */
    'cors' => [
        'allowed_origin_cache_ttl' => (int) env('TOWEROS_CORS_ORIGIN_CACHE_TTL', 3600),
        /** Comma-separated full origins, e.g. https://platform.yourdomain.com */
        'allowed_origin_extras' => env('TOWEROS_CORS_ALLOWED_ORIGINS', ''),
        /** Comma-separated wildcard patterns, e.g. https://*.yourdomain.com */
        'allowed_origin_patterns' => env(
            'TOWEROS_CORS_ALLOWED_ORIGIN_PATTERNS',
            env('APP_ENV') === 'local' ? 'http://*.localhost' : '',
        ),
    ],

    /**
     * When set, every new tenant's initial `admin@{domain}` uses this password (rotate in production).
     * When empty, a random password is generated and returned once from the platform provisioning API.
     */
    'tenant_bootstrap_admin_password' => env('TOWEROS_TENANT_BOOTSTRAP_ADMIN_PASSWORD'),

    'tenant_bootstrap_admin_name' => env('TOWEROS_TENANT_BOOTSTRAP_ADMIN_NAME', 'Tenant administrator'),

    /**
     * When true, platform provisioning responses include the initial admin plaintext password.
     * Default false in production so generated passwords are not returned over the wire.
     */
    'tenant_bootstrap_expose_password_in_api' => env(
        'TOWEROS_TENANT_BOOTSTRAP_EXPOSE_PASSWORD_IN_API',
        env('APP_ENV') !== 'production',
    ),

    /**
     * Central platform super admin (Passport). Seeded in non-production for the platform console.
     */
    'platform_super_admin_email' => env('TOWEROS_PLATFORM_SUPER_ADMIN_EMAIL', 'superadmin@toweros.local'),

    'platform_dev_password' => env('TOWEROS_PLATFORM_DEV_PASSWORD', 'password'),

    /**
     * When true, tenant API routes on central hosts (e.g. localhost) accept `X-Tenant-Id`
     * (tenant UUID) or `X-Tenant-Domain` (hostname registered on the tenant) instead of
     * requiring the API request Host to be the tenant domain. Disable outside trusted local development.
     */
    'allow_tenant_on_central_host' => env(
        'TOWEROS_ALLOW_TENANT_ON_CENTRAL_HOST',
        env('APP_ENV') === 'local',
    ),

    /**
     * When resolving tenant via `X-Tenant-Domain` on a central API host: require HTTPS (recommended in production).
     */
    'tenant_central_domain_header_require_https' => env(
        'TOWEROS_TENANT_CENTRAL_DOMAIN_HEADER_REQUIRE_HTTPS',
        env('APP_ENV') === 'production',
    ),

    /**
     * When true, `X-Tenant-Domain` must match the `Origin` or `Referer` host (mitigates arbitrary header injection
     * from contexts that do not represent that tenant UI). Default on in production.
     */
    'tenant_central_domain_header_require_origin_match' => env(
        'TOWEROS_TENANT_CENTRAL_DOMAIN_HEADER_REQUIRE_ORIGIN',
        env('APP_ENV') === 'production',
    ),

    /**
     * Optional: after central db:seed, auto-provision one tenant when TOWEROS_DEV_DEFAULT_TENANT_DOMAIN
     * is set (any hostname — acme.localhost, test.foo.localhost, etc.). Off by default.
     */
    'seed_dev_default_tenant' => env('TOWEROS_SEED_DEV_DEFAULT_TENANT', false),

    'dev_default_tenant' => [
        'domain' => env('TOWEROS_DEV_DEFAULT_TENANT_DOMAIN'),
        'slug' => env('TOWEROS_DEV_DEFAULT_TENANT_SLUG'),
        'brand_domain' => env('TOWEROS_DEV_DEFAULT_TENANT_BRAND_DOMAIN', 'example.com'),
    ],

    /**
     * Demo tenant seeding (Alliance.localhost sample dataset).
     */
    'demo' => [
        'tenant_domain' => env('TOWEROS_DEMO_TENANT_DOMAIN', 'alliance.localhost'),
        'tenant_id' => env('TOWEROS_DEMO_TENANT_ID'),
        'seed_on_tenant_migrate' => env('TOWEROS_DEMO_SEED', false),
    ],

    /**
     * Automatic rollout bootstrap when provisioning a tenant (platform console).
     */
    /**
     * Tenant MFA: global master switch (env) + per-tenant `tenants.mfa_required` (platform console).
     */
    'tenant_mfa' => [
        'global_required' => env('TENANT_MFA_REQUIRED', false),
    ],

    /**
     * Tenant administrator "view as user" (session override). Requires permission user:impersonate.
     */
    'tenant_impersonation' => [
        'enabled' => env('TOWEROS_TENANT_IMPERSONATION_ENABLED', true),
        'token_ttl_minutes' => (int) env('TOWEROS_TENANT_IMPERSONATION_TTL_MINUTES', 30),
    ],

    /**
     * Superadmin console impersonation into tenant workspaces (support).
     */
    'platform_impersonation' => [
        'enabled' => env('TOWEROS_PLATFORM_IMPERSONATION_ENABLED', true),
        'token_ttl_minutes' => (int) env('TOWEROS_PLATFORM_IMPERSONATION_TTL_MINUTES', 30),
    ],

    'platform_auth' => [
        'microsoft_callback_frontend' => env('FRONTEND_APP_URL', 'http://localhost'),
        /** Auto-create central operators on first Microsoft sign-in when Entra group maps to a role. */
        'entra_auto_provision' => env('TOWEROS_PLATFORM_ENTRA_AUTO_PROVISION', false),
        /**
         * Entra group object ID (lowercase) → platform_role (superadmin|billing|support|viewer).
         * Example: {"aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee":"support"}
         */
        'entra_group_role_map' => array_filter(
            (array) json_decode((string) env('TOWEROS_PLATFORM_ENTRA_GROUP_ROLE_MAP', '{}'), true) ?: [],
            static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        ),
    ],

    /**
     * Tenant authentication defaults (Microsoft SSO, password login, email domain allowlist).
     */
    'tenant_auth' => [
        /** When false, first Microsoft sign-in only works for users already invited in Team & Access. */
        'default_sso_auto_provision' => env('TOWEROS_TENANT_DEFAULT_SSO_AUTO_PROVISION', false),
        /** When Microsoft sign-in is enabled, block password login except break-glass bootstrap admin. */
        'default_disable_password_when_sso' => env('TOWEROS_TENANT_DEFAULT_DISABLE_PASSWORD_WHEN_SSO', true),
        /**
         * Roles assigned on first Microsoft SSO auto-provision (comma-separated).
         * Default: E-Approval requestor + Ticketing contributor (dashboard, submissions, tickets).
         * Legacy TENANT_SSO_DEFAULT_ROLE (single role) is used when TENANT_SSO_DEFAULT_ROLES is unset.
         */
        'default_sso_roles' => (static function (): array {
            $rolesEnv = env('TENANT_SSO_DEFAULT_ROLES');
            if (is_string($rolesEnv) && trim($rolesEnv) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $rolesEnv))));
            }

            $legacyRole = env('TENANT_SSO_DEFAULT_ROLE');
            if (is_string($legacyRole) && trim($legacyRole) !== '') {
                return [trim($legacyRole)];
            }

            return ['e_approval_requestor', 'ticketing_contributor'];
        })(),
    ],

    'billing' => [
        'support_email' => env('TOWEROS_BILLING_SUPPORT_EMAIL', 'support@toweros.local'),
    ],

    'tenant_provisioning' => [
        /**
         * Central tenants.plan_tier for new orgs (gates E-Approval file fields: professional+).
         * Local default is professional so form imports with attachments work without billing setup.
         */
        'default_plan_tier' => env(
            'TOWEROS_TENANT_DEFAULT_PLAN_TIER',
            env('APP_ENV') === 'local' ? 'professional' : 'starter',
        ),
        /** New tenants created via platform console (default off; enable per tenant when ready). */
        'default_mfa_required' => env('TOWEROS_TENANT_DEFAULT_MFA_REQUIRED', false),
        /** `latest` assigns the newest published playbook; `v1` pins to 1.0.0 when present. */
        'default_playbook' => env('TOWEROS_TENANT_DEFAULT_PLAYBOOK', 'latest'),
        /**
         * When true, new tenants are assigned a published rollout policy bundle automatically
         * (gate chains, email notifications, timeline). Uses default_rollout_policy_code when set,
         * otherwise the newest published bundle for the assigned playbook version.
         */
        'auto_assign_rollout_policy' => env('TOWEROS_TENANT_AUTO_ASSIGN_ROLLOUT_POLICY', true),
        /** Optional published policy bundle code (e.g. bts-standard). When empty, picks latest published. */
        'default_rollout_policy_code' => env('TOWEROS_TENANT_DEFAULT_ROLLOUT_POLICY_CODE'),
        'auto_seed_holidays' => env('TOWEROS_TENANT_AUTO_SEED_HOLIDAYS', true),
        'seed_next_holiday_year' => env('TOWEROS_TENANT_SEED_NEXT_HOLIDAY_YEAR', true),
    ],

    /**
     * Tenant-scoped rollout file uploads (SAQ photos, lease docs, CME evidence).
     */
    'tenant_files' => [
        'disk' => env('TOWEROS_TENANT_FILES_DISK', 'tenant_files'),
        'max_size_kb' => (int) env('TOWEROS_TENANT_FILES_MAX_KB', 25600),
        'signed_url_minutes' => (int) env('TOWEROS_TENANT_FILES_SIGNED_URL_MINUTES', 60),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ],

    /**
     * Site binder documents (S3 path: {tenantId}/documents/{siteId}/...).
     */
    'documents' => [
        'max_size_kb' => (int) env('TOWEROS_DOCUMENTS_MAX_KB', 51200),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
            'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/msword',
            'application/vnd.ms-excel',
            'application/dxf',
            'image/vnd.dwg',
            'application/acad',
            'model/vnd.dwf',
        ],
        'cad_extensions' => ['dwg', 'dxf', 'dwf', 'dgn', 'step', 'stp', 'iges', 'igs', 'ifc'],
        'cad_mimes' => [
            'application/octet-stream',
            'application/dxf',
            'image/vnd.dwg',
            'application/acad',
            'application/x-dwg',
            'model/vnd.dwf',
            'application/vnd.dwg',
        ],
        'presigned_upload_enabled' => filter_var(env('TOWEROS_DOCUMENTS_PRESIGNED_UPLOAD', true), FILTER_VALIDATE_BOOLEAN),
        'presigned_upload_ttl_minutes' => (int) env('TOWEROS_DOCUMENTS_PRESIGNED_TTL_MINUTES', 15),
        'presigned_upload_min_kb' => (int) env('TOWEROS_DOCUMENTS_PRESIGNED_MIN_KB', 10240),
        'gate_required_node_keys' => ['saq_phase_1', 'col', 'affidavit'],
        'gate_enforcement' => [
            'enabled' => filter_var(env('TOWEROS_DOCUMENTS_GATE_ENFORCEMENT', true), FILTER_VALIDATE_BOOLEAN),
            'phase_keys' => [
                'moc_col',
                'col_social',
                'pre_assessment',
                'site_license',
            ],
        ],
    ],

    /**
     * Tenant web app base URL for deep links in emails (gate approvals, rollouts).
     */
    'tenant_app_url' => env('TOWEROS_TENANT_APP_URL', env('FRONTEND_APP_URL', 'http://localhost')),

    /**
     * Sanctum SPA stateful domains — tenant hostnames are merged from the central DB at runtime.
     */
    'sanctum' => [
        /** Seconds to cache merged domains; set 0 to rebuild every request (local debugging). */
        'stateful_domain_cache_ttl' => (int) env('TOWEROS_SANCTUM_STATEFUL_CACHE_TTL', 3600),
        /** Optional comma-separated extras (platform console hosts, legacy DNS, etc.). */
        'stateful_domain_extras' => env('SANCTUM_STATEFUL_DOMAINS', ''),
    ],

    /**
     * Module notification email (E-Approval, Project One gate approvals, etc.).
     * Use smtp for Microsoft 365 (smtp.office365.com), ses for AWS, log for local dev.
     * Not the legacy formbuilder Graph sidecar — platform Laravel mail only.
     */
    'notifications_mail_mailer' => env(
        'TOWEROS_NOTIFICATIONS_MAIL_MAILER',
        env('TOWEROS_GATE_APPROVAL_MAIL_MAILER', env('MAIL_MAILER', 'log')),
    ),

    /**
     * @deprecated Use notifications_mail_mailer / TOWEROS_NOTIFICATIONS_MAIL_MAILER.
     */
    'gate_approval_mail_mailer' => env(
        'TOWEROS_GATE_APPROVAL_MAIL_MAILER',
        env('TOWEROS_NOTIFICATIONS_MAIL_MAILER', env('MAIL_MAILER', 'log')),
    ),

    /**
     * Tenant modules enabled for RBAC provisioning and the Team & Access role editor.
     * Keys: core, team_access, project_one, e_approval, ticketing, procurement_one, sites, documents (plus optional gis, tower_one, fiber_one, asset_one).
     */
    'tenant_modules' => [
        'enabled' => array_values(array_filter(array_map(
            static fn (string $m): string => trim($m),
            explode(',', (string) env('TOWEROS_TENANT_ENABLED_MODULES', 'core,team_access,project_one,e_approval,ticketing,procurement_one,finance_one,sites,documents')),
        ))),
    ],

    /**
     * E-Approval module (forms, workflows, legacy import).
     */
    'e_approval' => [
        'legacy_connection' => env('LEGACY_FORMBUILDER_DB_CONNECTION', 'legacy_formbuilder'),
    ],

];
