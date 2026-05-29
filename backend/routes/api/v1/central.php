<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\V1\CentralHealthController;
use App\Modules\Identity\Http\Controllers\V1\MicrosoftAuthController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformAuthController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseShowController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundlePublishController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleShowController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPlaybookPublishController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPlaybookIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantPlaybookAssignController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantDirectoryController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantSettingsController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantEnvironmentStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantProvisioningController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymPublicIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymSyncDefaultsController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantPublicBrandingController;
use Illuminate\Support\Facades\Route;

Route::get('health', CentralHealthController::class)->name('api.central.v1.health');

Route::get('public/tenant-branding', [CentralTenantPublicBrandingController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.tenant-branding');

Route::get('public/operational-acronyms', CentralOperationalAcronymPublicIndexController::class)
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.operational-acronyms');

Route::post('platform/login', [CentralPlatformAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.central.v1.platform.login');

Route::middleware(['auth:api', 'platform.admin'])->prefix('platform')->group(function () {
    Route::get('me', [CentralPlatformAuthController::class, 'me'])->name('api.central.v1.platform.me');
    Route::get('tenants', [CentralTenantDirectoryController::class, 'index'])
        ->name('api.central.v1.platform.tenants.index');
    Route::post('tenants', [CentralTenantProvisioningController::class, 'store'])
        ->name('api.central.v1.platform.tenants.store');
    Route::post('tenants/{tenant}/environments', CentralTenantEnvironmentStoreController::class)
        ->name('api.central.v1.platform.tenants.environments.store');
    Route::patch('tenants/{tenant}', [CentralTenantSettingsController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('api.central.v1.platform.tenants.update');
    Route::delete('tenants/{tenant}', CentralTenantDestroyController::class)
        ->middleware('throttle:10,1')
        ->name('api.central.v1.platform.tenants.destroy');
    Route::get('rollout-playbooks', CentralRolloutPlaybookIndexController::class)
        ->name('api.central.v1.platform.rollout_playbooks.index');
    Route::post('rollout-playbooks/publish', CentralRolloutPlaybookPublishController::class)
        ->name('api.central.v1.platform.rollout_playbooks.publish');
    Route::get('rollout-policies', CentralRolloutPolicyBundleIndexController::class)
        ->name('api.central.v1.platform.rollout_policies.index');
    Route::post('rollout-policies', CentralRolloutPolicyBundleStoreController::class)
        ->name('api.central.v1.platform.rollout_policies.store');
    Route::get('rollout-policies/{rolloutPolicyBundle}', CentralRolloutPolicyBundleShowController::class)
        ->name('api.central.v1.platform.rollout_policies.show');
    Route::patch('rollout-policies/{rolloutPolicyBundle}', CentralRolloutPolicyBundleUpdateController::class)
        ->name('api.central.v1.platform.rollout_policies.update');
    Route::post('rollout-policies/{rolloutPolicyBundle}/publish', CentralRolloutPolicyBundlePublishController::class)
        ->name('api.central.v1.platform.rollout_policies.publish');
    Route::get('rollout-phases', CentralRolloutCustomPhaseIndexController::class)
        ->name('api.central.v1.platform.rollout_phases.index');
    Route::post('rollout-phases', CentralRolloutCustomPhaseStoreController::class)
        ->name('api.central.v1.platform.rollout_phases.store');
    Route::get('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseShowController::class)
        ->name('api.central.v1.platform.rollout_phases.show');
    Route::patch('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseUpdateController::class)
        ->name('api.central.v1.platform.rollout_phases.update');
    Route::delete('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseDestroyController::class)
        ->name('api.central.v1.platform.rollout_phases.destroy');
    Route::post('tenants/{tenant}/playbook', CentralTenantPlaybookAssignController::class)
        ->name('api.central.v1.platform.tenants.playbook.assign');
    Route::get('operational-acronyms', CentralOperationalAcronymIndexController::class)
        ->name('api.central.v1.platform.operational-acronyms.index');
    Route::post('operational-acronyms', CentralOperationalAcronymStoreController::class)
        ->name('api.central.v1.platform.operational-acronyms.store');
    Route::post('operational-acronyms/sync-defaults', CentralOperationalAcronymSyncDefaultsController::class)
        ->name('api.central.v1.platform.operational-acronyms.sync-defaults');
    Route::patch('operational-acronyms/{operationalAcronym}', CentralOperationalAcronymUpdateController::class)
        ->name('api.central.v1.platform.operational-acronyms.update');
    Route::delete('operational-acronyms/{operationalAcronym}', CentralOperationalAcronymDestroyController::class)
        ->name('api.central.v1.platform.operational-acronyms.destroy');
});

Route::prefix('auth/microsoft')->group(function () {
    Route::get('redirect', [MicrosoftAuthController::class, 'redirect'])->name('api.central.v1.auth.microsoft.redirect');
    Route::get('callback', [MicrosoftAuthController::class, 'callback'])->name('api.central.v1.auth.microsoft.callback');
});
