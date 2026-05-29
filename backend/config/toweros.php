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
    'tenant_provisioning' => [
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
        'max_size_kb' => (int) env('TOWEROS_TENANT_FILES_MAX_KB', 10240),
        'signed_url_minutes' => (int) env('TOWEROS_TENANT_FILES_SIGNED_URL_MINUTES', 60),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
        ],
    ],

    /**
     * Tenant web app base URL for deep links in emails (gate approvals, rollouts).
     */
    'tenant_app_url' => env('TOWEROS_TENANT_APP_URL', env('FRONTEND_APP_URL', 'http://localhost:3001')),

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
     * Gate approval email transport: smtp (Microsoft 365), ses (AWS production), log (local).
     */
    'gate_approval_mail_mailer' => env('TOWEROS_GATE_APPROVAL_MAIL_MAILER', env('MAIL_MAILER', 'log')),

];
