<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\V1\CentralHealthController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuGroupDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuGroupIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuGroupReorderController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuGroupStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuGroupUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuIconDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuIconStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuPlaceController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuPublicIconController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuPublicIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuReorderController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuSettingsUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuSyncDefaultsController;
use App\Modules\Platform\Http\Controllers\V1\CentralAppMenuUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymPublicIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymSyncDefaultsController;
use App\Modules\Platform\Http\Controllers\V1\CentralOperationalAcronymUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformAuditIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformAuthController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformBillingCatalogUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformBillingInsightsController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformDashboardController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformMfaController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformMicrosoftAuthController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformOperatorDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformOperatorIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformOperatorStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformOperatorUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralPlatformRoleCatalogController;
use App\Modules\Platform\Http\Controllers\V1\CentralPublicClientIpController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseShowController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutCustomPhaseUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPlaybookIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPlaybookPublishController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundlePublishController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleShowController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralRolloutPolicyBundleUpdateController;
use App\Modules\Platform\Http\Controllers\V1\CentralStripeWebhookController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantAuditIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupDownloadController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupRestoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupScheduleRunController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBackupStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBillingAuditIndexController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBillingPortalSessionStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantBrandingAssetStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantDestroyController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantDirectoryController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantEnvironmentStoreController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantImpersonateController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantModulesCatalogController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantPlanCatalogController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantPlaybookAssignController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantProvisioningController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantPublicBrandingController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantSettingsController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantShowController;
use App\Modules\Platform\Http\Controllers\V1\CentralTenantUserIndexController;
use App\Modules\Platform\Support\PlatformRoleCatalog;
use Illuminate\Support\Facades\Route;

Route::get('health', CentralHealthController::class)->name('api.central.v1.health');

Route::get('public/client-ip', CentralPublicClientIpController::class)
    ->middleware('throttle:60,1')
    ->name('api.central.v1.public.client-ip');

Route::post('webhooks/stripe', CentralStripeWebhookController::class)
    ->middleware('throttle:240,1')
    ->name('api.central.v1.webhooks.stripe');

Route::get('public/tenant-branding', [CentralTenantPublicBrandingController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.tenant-branding');
Route::get('public/tenant-branding/{asset}', [CentralTenantPublicBrandingController::class, 'asset'])
    ->where('asset', 'logo|favicon')
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.tenant-branding.asset');

Route::get('public/operational-acronyms', CentralOperationalAcronymPublicIndexController::class)
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.operational-acronyms');

Route::get('public/app-menu', CentralAppMenuPublicIndexController::class)
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.app-menu');
Route::get('public/app-menu-icons/{appMenuTile}', CentralAppMenuPublicIconController::class)
    ->middleware('throttle:120,1')
    ->name('api.central.v1.public.app-menu-icons');

Route::post('platform/login', [CentralPlatformAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.central.v1.platform.login');

Route::prefix('platform/mfa')->middleware('throttle:20,1')->group(function () {
    Route::post('verify', [CentralPlatformMfaController::class, 'verify'])
        ->name('api.central.v1.platform.mfa.verify');
    Route::post('recovery', [CentralPlatformMfaController::class, 'recovery'])
        ->name('api.central.v1.platform.mfa.recovery');
    Route::post('enroll/start', [CentralPlatformMfaController::class, 'enrollStart'])
        ->name('api.central.v1.platform.mfa.enroll.start');
    Route::post('enroll/complete', [CentralPlatformMfaController::class, 'enrollComplete'])
        ->name('api.central.v1.platform.mfa.enroll.complete');
});

Route::prefix('platform/auth/microsoft')->group(function () {
    Route::get('redirect', [CentralPlatformMicrosoftAuthController::class, 'redirect'])
        ->name('api.central.v1.platform.auth.microsoft.redirect');
    Route::get('callback', [CentralPlatformMicrosoftAuthController::class, 'callback'])
        ->name('api.central.v1.platform.auth.microsoft.callback');
});

Route::middleware(['auth:api', 'platform.admin', 'platform.mfa'])->prefix('platform')->group(function () {
    Route::get('me', [CentralPlatformAuthController::class, 'me'])->name('api.central.v1.platform.me');
    Route::get('mfa/status', [CentralPlatformMfaController::class, 'status'])
        ->name('api.central.v1.platform.mfa.status');
    Route::post('mfa/enroll/start', [CentralPlatformMfaController::class, 'authenticatedEnrollStart'])
        ->middleware('throttle:10,1')
        ->name('api.central.v1.platform.mfa.authenticated.enroll.start');
    Route::post('mfa/enroll/complete', [CentralPlatformMfaController::class, 'authenticatedEnrollComplete'])
        ->middleware('throttle:10,1')
        ->name('api.central.v1.platform.mfa.authenticated.enroll.complete');
    Route::post('mfa/recovery-codes/regenerate', [CentralPlatformMfaController::class, 'regenerateRecoveryCodes'])
        ->middleware('throttle:5,1')
        ->name('api.central.v1.platform.mfa.recovery_codes.regenerate');
    Route::get('roles/catalog', CentralPlatformRoleCatalogController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_CONSOLE_VIEW)
        ->name('api.central.v1.platform.roles.catalog');
    Route::get('operators', CentralPlatformOperatorIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_OPERATORS_VIEW)
        ->name('api.central.v1.platform.operators.index');
    Route::post('operators', CentralPlatformOperatorStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_OPERATORS_MANAGE)
        ->name('api.central.v1.platform.operators.store');
    Route::patch('operators/{user}', CentralPlatformOperatorUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_OPERATORS_MANAGE)
        ->name('api.central.v1.platform.operators.update');
    Route::delete('operators/{user}', CentralPlatformOperatorDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_OPERATORS_MANAGE)
        ->name('api.central.v1.platform.operators.destroy');
    Route::get('dashboard', CentralPlatformDashboardController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_CONSOLE_VIEW)
        ->name('api.central.v1.platform.dashboard');
    Route::get('billing/plan-catalog', CentralTenantPlanCatalogController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_BILLING_VIEW)
        ->name('api.central.v1.platform.billing.plan_catalog');
    Route::patch('billing/catalog', CentralPlatformBillingCatalogUpdateController::class)
        ->middleware(['throttle:30,1', 'platform.permission:'.PlatformRoleCatalog::PERM_BILLING_MANAGE])
        ->name('api.central.v1.platform.billing.catalog.update');
    Route::get('tenant-modules/catalog', CentralTenantModulesCatalogController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_CONSOLE_VIEW)
        ->name('api.central.v1.platform.tenant_modules.catalog');
    Route::get('billing/insights', CentralPlatformBillingInsightsController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_BILLING_VIEW)
        ->name('api.central.v1.platform.billing.insights');
    Route::get('audit', CentralPlatformAuditIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_AUDIT_VIEW)
        ->name('api.central.v1.platform.audit.index');
    Route::get('tenants', [CentralTenantDirectoryController::class, 'index'])
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.index');
    Route::get('tenants/{tenant}', CentralTenantShowController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.show');
    Route::post('tenants', [CentralTenantProvisioningController::class, 'store'])
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.tenants.store');
    Route::post('tenants/{tenant}/environments', CentralTenantEnvironmentStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.tenants.environments.store');
    Route::patch('tenants/{tenant}', [CentralTenantSettingsController::class, 'update'])
        ->middleware(['throttle:60,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE])
        ->name('api.central.v1.platform.tenants.update');
    Route::post('tenants/{tenant}/branding/{asset}', CentralTenantBrandingAssetStoreController::class)
        ->where('asset', 'logo|favicon')
        ->middleware(['throttle:30,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE])
        ->name('api.central.v1.platform.tenants.branding.asset');
    Route::get('tenants/{tenant}/billing-audit', CentralTenantBillingAuditIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_BILLING_VIEW)
        ->name('api.central.v1.platform.tenants.billing_audit');
    Route::get('tenants/{tenant}/audit', CentralTenantAuditIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.audit');
    Route::get('tenants/{tenant}/users', CentralTenantUserIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.users.index');
    Route::post('tenants/{tenant}/impersonate', CentralTenantImpersonateController::class)
        ->middleware(['throttle:20,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_IMPERSONATE])
        ->name('api.central.v1.platform.tenants.impersonate');
    Route::get('tenants/{tenant}/backups', CentralTenantBackupIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.backups.index');
    Route::post('tenants/{tenant}/backups', CentralTenantBackupStoreController::class)
        ->middleware(['throttle:20,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_BACKUP])
        ->name('api.central.v1.platform.tenants.backups.store');
    Route::post('tenants/{tenant}/backups/schedule-run', CentralTenantBackupScheduleRunController::class)
        ->middleware(['throttle:10,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_BACKUP])
        ->name('api.central.v1.platform.tenants.backups.schedule_run');
    Route::get('tenants/{tenant}/backups/{backup}/download', CentralTenantBackupDownloadController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_VIEW)
        ->name('api.central.v1.platform.tenants.backups.download');
    Route::post('tenants/{tenant}/backups/{backup}/restore', CentralTenantBackupRestoreController::class)
        ->middleware(['throttle:10,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_BACKUP])
        ->name('api.central.v1.platform.tenants.backups.restore');
    Route::delete('tenants/{tenant}/backups/{backup}', CentralTenantBackupDestroyController::class)
        ->middleware(['throttle:20,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_BACKUP])
        ->name('api.central.v1.platform.tenants.backups.destroy');
    Route::post('tenants/{tenant}/billing-portal-session', CentralTenantBillingPortalSessionStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_BILLING_MANAGE)
        ->name('api.central.v1.platform.tenants.billing_portal');
    Route::delete('tenants/{tenant}', CentralTenantDestroyController::class)
        ->middleware(['throttle:10,1', 'platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_DELETE])
        ->name('api.central.v1.platform.tenants.destroy');
    Route::get('rollout-playbooks', CentralRolloutPlaybookIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW)
        ->name('api.central.v1.platform.rollout_playbooks.index');
    Route::post('rollout-playbooks/publish', CentralRolloutPlaybookPublishController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_playbooks.publish');
    Route::get('rollout-policies', CentralRolloutPolicyBundleIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW)
        ->name('api.central.v1.platform.rollout_policies.index');
    Route::post('rollout-policies', CentralRolloutPolicyBundleStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_policies.store');
    Route::get('rollout-policies/{rolloutPolicyBundle}', CentralRolloutPolicyBundleShowController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW)
        ->name('api.central.v1.platform.rollout_policies.show');
    Route::patch('rollout-policies/{rolloutPolicyBundle}', CentralRolloutPolicyBundleUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_policies.update');
    Route::post('rollout-policies/{rolloutPolicyBundle}/publish', CentralRolloutPolicyBundlePublishController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_policies.publish');
    Route::get('rollout-phases', CentralRolloutCustomPhaseIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW)
        ->name('api.central.v1.platform.rollout_phases.index');
    Route::post('rollout-phases', CentralRolloutCustomPhaseStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_phases.store');
    Route::get('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseShowController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW)
        ->name('api.central.v1.platform.rollout_phases.show');
    Route::patch('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_phases.update');
    Route::delete('rollout-phases/{rolloutCustomPhase}', CentralRolloutCustomPhaseDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.rollout_phases.destroy');
    Route::post('tenants/{tenant}/playbook', CentralTenantPlaybookAssignController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_PLAYBOOKS_MANAGE)
        ->name('api.central.v1.platform.tenants.playbook.assign');
    Route::get('operational-acronyms', CentralOperationalAcronymIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.operational-acronyms.index');
    Route::post('operational-acronyms', CentralOperationalAcronymStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.operational-acronyms.store');
    Route::post('operational-acronyms/sync-defaults', CentralOperationalAcronymSyncDefaultsController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.operational-acronyms.sync-defaults');
    Route::patch('operational-acronyms/{operationalAcronym}', CentralOperationalAcronymUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.operational-acronyms.update');
    Route::delete('operational-acronyms/{operationalAcronym}', CentralOperationalAcronymDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.operational-acronyms.destroy');

    Route::get('app-menu', CentralAppMenuIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.index');
    Route::post('app-menu', CentralAppMenuStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.store');
    Route::post('app-menu/sync-defaults', CentralAppMenuSyncDefaultsController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.sync-defaults');
    Route::post('app-menu/reorder', CentralAppMenuReorderController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.reorder');
    Route::post('app-menu/place', CentralAppMenuPlaceController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.place');
    Route::patch('app-menu/settings', CentralAppMenuSettingsUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.settings');
    Route::get('app-menu/groups', CentralAppMenuGroupIndexController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.groups.index');
    Route::post('app-menu/groups', CentralAppMenuGroupStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.groups.store');
    Route::post('app-menu/groups/reorder', CentralAppMenuGroupReorderController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.groups.reorder');
    Route::patch('app-menu/groups/{appMenuGroup}', CentralAppMenuGroupUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.groups.update');
    Route::delete('app-menu/groups/{appMenuGroup}', CentralAppMenuGroupDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.groups.destroy');
    Route::patch('app-menu/{appMenuTile}', CentralAppMenuUpdateController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.update');
    Route::post('app-menu/{appMenuTile}/icon', CentralAppMenuIconStoreController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.icon.store');
    Route::delete('app-menu/{appMenuTile}/icon', CentralAppMenuIconDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.icon.destroy');
    Route::delete('app-menu/{appMenuTile}', CentralAppMenuDestroyController::class)
        ->middleware('platform.permission:'.PlatformRoleCatalog::PERM_TENANTS_MANAGE)
        ->name('api.central.v1.platform.app-menu.destroy');
});
