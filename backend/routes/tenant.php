<?php

declare(strict_types=1);

use App\Core\Http\Middleware\InitializeTenancyForTenantRequest;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    InitializeTenancyForTenantRequest::class,
])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => __('Tenant application'),
            'tenant_id' => tenant('id'),
        ]);
    });
});

Route::middleware([
    'api',
    InitializeTenancyForTenantRequest::class,
    'tenant.subscription',
])->prefix('api/'.config('toweros.api.current_version', 'v1'))->group(base_path('routes/api/v1/tenant.php'));
