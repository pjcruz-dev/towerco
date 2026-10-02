<?php

declare(strict_types=1);

use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskRunController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskSyncController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskToggleController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskUpdateController;

Route::get('automation/scheduled-tasks', DynScheduledTaskIndexController::class)->name('api.tenant.v1.automation.scheduled_tasks.index');
Route::post('automation/scheduled-tasks', DynScheduledTaskStoreController::class)->name('api.tenant.v1.automation.scheduled_tasks.store');
Route::post('automation/scheduled-tasks/sync', DynScheduledTaskSyncController::class)->name('api.tenant.v1.automation.scheduled_tasks.sync');
Route::patch('automation/scheduled-tasks/{task}', DynScheduledTaskUpdateController::class)->name('api.tenant.v1.automation.scheduled_tasks.update');
Route::delete('automation/scheduled-tasks/{task}', DynScheduledTaskDestroyController::class)->name('api.tenant.v1.automation.scheduled_tasks.destroy');
Route::post('automation/scheduled-tasks/{task}/run', DynScheduledTaskRunController::class)->name('api.tenant.v1.automation.scheduled_tasks.run');
Route::post('automation/scheduled-tasks/{task}/toggle', DynScheduledTaskToggleController::class)->name('api.tenant.v1.automation.scheduled_tasks.toggle');
