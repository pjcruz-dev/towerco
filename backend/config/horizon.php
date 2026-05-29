<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:'.env('TOWEROS_QUEUE_DEFAULT', 'toweros-default') => 60,
        'redis:'.env('TOWEROS_QUEUE_TENANT', 'toweros-tenant') => 120,
        'redis:'.env('TOWEROS_QUEUE_NOTIFICATIONS', 'toweros-notifications') => 60,
        'redis:'.env('TOWEROS_QUEUE_INTEGRATIONS', 'toweros-integrations') => 120,
        'redis:'.env('TOWEROS_QUEUE_WEBHOOKS', 'toweros-webhooks') => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => [
                env('TOWEROS_QUEUE_DEFAULT', 'toweros-default'),
            ],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'supervisor-tenant' => [
            'connection' => 'redis',
            'queue' => [
                env('TOWEROS_QUEUE_TENANT', 'toweros-tenant'),
            ],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
            'nice' => 0,
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => [
                env('TOWEROS_QUEUE_NOTIFICATIONS', 'toweros-notifications'),
            ],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-tenant' => [
                'maxProcesses' => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 5,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 3,
            ],
            'supervisor-tenant' => [
                'maxProcesses' => 3,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 2,
            ],
        ],

        '*' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
            'supervisor-tenant' => [
                'maxProcesses' => 2,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 1,
            ],
        ],
    ],
];
