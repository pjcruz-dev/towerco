<?php

declare(strict_types=1);

use App\Models\Tenant;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Database\Models\Domain;

$useCacheTenancyBootstrapper = match (strtolower((string) env('TENANCY_CACHE_BOOTSTRAPPER', ''))) {
    '1', 'true', 'yes', 'on' => true,
    '0', 'false', 'no', 'off' => false,
    default => in_array((string) env('CACHE_STORE', 'database'), ['redis', 'memcached'], true),
};

$tenancyBootstrappers = [
    DatabaseTenancyBootstrapper::class,
];

if ($useCacheTenancyBootstrapper) {
    $tenancyBootstrappers[] = CacheTenancyBootstrapper::class;
}

$tenancyBootstrappers[] = FilesystemTenancyBootstrapper::class;
$tenancyBootstrappers[] = QueueTenancyBootstrapper::class;

return [
    'tenant_model' => Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => Domain::class,

    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_DOMAINS', '127.0.0.1,localhost'))
    ))),

    'bootstrappers' => $tenancyBootstrappers,

    'database' => [
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),
        'template_tenant_connection' => null,
        'prefix' => env('TENANCY_DATABASE_PREFIX', 'tenant'),
        'suffix' => env('TENANCY_DATABASE_SUFFIX', ''),
        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'mariadb' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [
            // 'default',
        ],
    ],

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
    ],

    'routes' => true,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
    ],
];
