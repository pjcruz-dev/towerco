<?php

declare(strict_types=1);

use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavAdminShowController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemDestroyController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavReorderController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavSeedController;
use App\Modules\AdminOne\Http\Controllers\V1\WorkspaceSidebarShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\AtcExecutiveDashboardController;
use App\Modules\DynamicEntities\Http\Controllers\V1\AtcTicketingBoardController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookToggleController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityHookUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFinanceReportsController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportDuplicateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportRenderController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynInvoiceAgingAdjustController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynPdfFormDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynPdfFormDownloadController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynPdfFormIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynPdfFormStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynPdfFormUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordBulkController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordExportController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordImportAnalyzeController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordImportController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordImportTemplateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRecordWorkflowActionController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipGraphController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipLayoutController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderAiBuildController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderPreviewController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderSaveController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskRunController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskSyncController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskToggleController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynSearchIndexActionController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynSearchIndexStatusController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\PurchaseMonitoringReportController;

Route::get('workspace/sidebar', WorkspaceSidebarShowController::class)->name('api.tenant.v1.workspace.sidebar');

Route::middleware('tenant.module:dynamic_entities')->group(function () {
    Route::get('dynamic-entities/relationship-graph', DynRelationshipGraphController::class)
        ->name('api.tenant.v1.dynamic_entities.relationship_graph');
    Route::put('dynamic-entities/relationship-graph/layout', DynRelationshipLayoutController::class)
        ->name('api.tenant.v1.dynamic_entities.relationship_graph.layout');
    Route::post('dynamic-entities/relationship-graph/edges', DynRelationshipEdgeStoreController::class)
        ->name('api.tenant.v1.dynamic_entities.relationship_graph.edges.store');
    Route::patch('dynamic-entities/relationship-graph/edges/{field}', DynRelationshipEdgeUpdateController::class)
        ->name('api.tenant.v1.dynamic_entities.relationship_graph.edges.update');
    Route::delete('dynamic-entities/relationship-graph/edges/{field}', DynRelationshipEdgeDestroyController::class)
        ->name('api.tenant.v1.dynamic_entities.relationship_graph.edges.destroy');
    Route::get('dynamic-entities/entities', DynEntityIndexController::class)->name('api.tenant.v1.dynamic_entities.entities.index');
    Route::post('dynamic-entities/entities', DynEntityStoreController::class)->name('api.tenant.v1.dynamic_entities.entities.store');
    Route::get('dynamic-entities/entities/{entity}', DynEntityShowController::class)->name('api.tenant.v1.dynamic_entities.entities.show');
    Route::patch('dynamic-entities/entities/{entity}', DynEntityUpdateController::class)->name('api.tenant.v1.dynamic_entities.entities.update');
    Route::post('dynamic-entities/entities/{entity}/fields', DynFieldStoreController::class)->name('api.tenant.v1.dynamic_entities.fields.store');
    Route::post('dynamic-entities/entities/{entity}/field-groups', DynFieldGroupStoreController::class)->name('api.tenant.v1.dynamic_entities.field_groups.store');
    Route::patch('dynamic-entities/field-groups/{group}', DynFieldGroupUpdateController::class)->name('api.tenant.v1.dynamic_entities.field_groups.update');
    Route::delete('dynamic-entities/field-groups/{group}', DynFieldGroupDestroyController::class)->name('api.tenant.v1.dynamic_entities.field_groups.destroy');
    Route::patch('dynamic-entities/fields/{field}', DynFieldUpdateController::class)->name('api.tenant.v1.dynamic_entities.fields.update');
    Route::get('dynamic-entities/reports/purchase-monitoring', PurchaseMonitoringReportController::class)
        ->name('api.tenant.v1.dynamic_entities.reports.purchase_monitoring');
    Route::get('dynamic-entities/reports/executive-dashboard', AtcExecutiveDashboardController::class)
        ->name('api.tenant.v1.dynamic_entities.reports.executive_dashboard');
    Route::get('dynamic-entities/reports/ticketing-board', AtcTicketingBoardController::class)
        ->name('api.tenant.v1.dynamic_entities.reports.ticketing_board');
    // One {report} route only — duplicate URIs overwrite each other in Laravel's route collection.
    Route::get('dynamic-entities/reports/{report}', DynFinanceReportsController::class)
        ->whereIn('report', DynFinanceReportsController::reportKeys())
        ->name('api.tenant.v1.dynamic_entities.reports.show');
    Route::post('dynamic-entities/reports/aging-invoice-adjustments/adjust', DynInvoiceAgingAdjustController::class)
        ->name('api.tenant.v1.dynamic_entities.reports.aging_invoice.adjust');
    Route::get('dynamic-entities/entities/{entity}/records', DynRecordIndexController::class)->name('api.tenant.v1.dynamic_entities.records.index');
    Route::post('dynamic-entities/entities/{entity}/records', DynRecordStoreController::class)->name('api.tenant.v1.dynamic_entities.records.store');
    Route::post('dynamic-entities/entities/{entity}/records/bulk', DynRecordBulkController::class)->name('api.tenant.v1.dynamic_entities.records.bulk');
    Route::get('dynamic-entities/entities/{entity}/records/export', DynRecordExportController::class)->name('api.tenant.v1.dynamic_entities.records.export');
    Route::get('dynamic-entities/entities/{entity}/records/import/template', DynRecordImportTemplateController::class)->name('api.tenant.v1.dynamic_entities.records.import.template');
    Route::post('dynamic-entities/entities/{entity}/records/import/analyze', DynRecordImportAnalyzeController::class)->name('api.tenant.v1.dynamic_entities.records.import.analyze');
    Route::post('dynamic-entities/entities/{entity}/records/import', DynRecordImportController::class)->name('api.tenant.v1.dynamic_entities.records.import');
    Route::get('dynamic-entities/records/{record}', DynRecordShowController::class)->name('api.tenant.v1.dynamic_entities.records.show');
    Route::patch('dynamic-entities/records/{record}', DynRecordUpdateController::class)->name('api.tenant.v1.dynamic_entities.records.update');
    Route::post('dynamic-entities/records/{record}/workflow-actions/{action}', DynRecordWorkflowActionController::class)
        ->name('api.tenant.v1.dynamic_entities.records.workflow_action');
    Route::delete('dynamic-entities/records/{record}', DynRecordDestroyController::class)->name('api.tenant.v1.dynamic_entities.records.destroy');
    Route::get('dynamic-entities/pdf-forms', DynPdfFormIndexController::class)->name('api.tenant.v1.dynamic_entities.pdf_forms.index');
    Route::post('dynamic-entities/pdf-forms', DynPdfFormStoreController::class)->name('api.tenant.v1.dynamic_entities.pdf_forms.store');
    Route::patch('dynamic-entities/pdf-forms/{form}', DynPdfFormUpdateController::class)->name('api.tenant.v1.dynamic_entities.pdf_forms.update');
    Route::delete('dynamic-entities/pdf-forms/{form}', DynPdfFormDestroyController::class)->name('api.tenant.v1.dynamic_entities.pdf_forms.destroy');
    Route::get('dynamic-entities/pdf-forms/{form}/file', DynPdfFormDownloadController::class)->name('api.tenant.v1.dynamic_entities.pdf_forms.file');
    Route::get('dynamic-entities/html-reports', DynHtmlReportIndexController::class)->name('api.tenant.v1.dynamic_entities.html_reports.index');
    Route::post('dynamic-entities/html-reports', DynHtmlReportStoreController::class)->name('api.tenant.v1.dynamic_entities.html_reports.store');
    Route::get('dynamic-entities/html-reports/render/{slug}', DynHtmlReportRenderController::class)
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('api.tenant.v1.dynamic_entities.html_reports.render');
    Route::get('dynamic-entities/html-reports/{report}', DynHtmlReportShowController::class)->name('api.tenant.v1.dynamic_entities.html_reports.show');
    Route::patch('dynamic-entities/html-reports/{report}', DynHtmlReportUpdateController::class)->name('api.tenant.v1.dynamic_entities.html_reports.update');
    Route::delete('dynamic-entities/html-reports/{report}', DynHtmlReportDestroyController::class)->name('api.tenant.v1.dynamic_entities.html_reports.destroy');
    Route::post('dynamic-entities/html-reports/{report}/duplicate', DynHtmlReportDuplicateController::class)->name('api.tenant.v1.dynamic_entities.html_reports.duplicate');
    Route::post('dynamic-entities/report-builder/preview', DynReportBuilderPreviewController::class)->name('api.tenant.v1.dynamic_entities.report_builder.preview');
    Route::post('dynamic-entities/report-builder/save', DynReportBuilderSaveController::class)->name('api.tenant.v1.dynamic_entities.report_builder.save');
    Route::post('dynamic-entities/report-builder/ai-build', DynReportBuilderAiBuildController::class)->name('api.tenant.v1.dynamic_entities.report_builder.ai_build');
    Route::get('dynamic-entities/email-templates', DynEmailTemplateIndexController::class)->name('api.tenant.v1.dynamic_entities.email_templates.index');
    Route::post('dynamic-entities/email-templates', DynEmailTemplateStoreController::class)->name('api.tenant.v1.dynamic_entities.email_templates.store');
    Route::get('dynamic-entities/email-templates/{template}', DynEmailTemplateShowController::class)->name('api.tenant.v1.dynamic_entities.email_templates.show');
    Route::patch('dynamic-entities/email-templates/{template}', DynEmailTemplateUpdateController::class)->name('api.tenant.v1.dynamic_entities.email_templates.update');
    Route::delete('dynamic-entities/email-templates/{template}', DynEmailTemplateDestroyController::class)->name('api.tenant.v1.dynamic_entities.email_templates.destroy');
    Route::get('dynamic-entities/scheduled-tasks', DynScheduledTaskIndexController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.index');
    Route::post('dynamic-entities/scheduled-tasks', DynScheduledTaskStoreController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.store');
    Route::post('dynamic-entities/scheduled-tasks/sync', DynScheduledTaskSyncController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.sync');
    Route::patch('dynamic-entities/scheduled-tasks/{task}', DynScheduledTaskUpdateController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.update');
    Route::delete('dynamic-entities/scheduled-tasks/{task}', DynScheduledTaskDestroyController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.destroy');
    Route::post('dynamic-entities/scheduled-tasks/{task}/run', DynScheduledTaskRunController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.run');
    Route::post('dynamic-entities/scheduled-tasks/{task}/toggle', DynScheduledTaskToggleController::class)->name('api.tenant.v1.dynamic_entities.scheduled_tasks.toggle');
    Route::get('dynamic-entities/workflows', DynWorkflowIndexController::class)->name('api.tenant.v1.dynamic_entities.workflows.index');
    Route::post('dynamic-entities/workflows', DynWorkflowStoreController::class)->name('api.tenant.v1.dynamic_entities.workflows.store');
    Route::get('dynamic-entities/workflows/{workflow}', DynWorkflowShowController::class)->name('api.tenant.v1.dynamic_entities.workflows.show');
    Route::patch('dynamic-entities/workflows/{workflow}', DynWorkflowUpdateController::class)->name('api.tenant.v1.dynamic_entities.workflows.update');
    Route::delete('dynamic-entities/workflows/{workflow}', DynWorkflowDestroyController::class)->name('api.tenant.v1.dynamic_entities.workflows.destroy');
    Route::get('dynamic-entities/hooks', DynEntityHookIndexController::class)->name('api.tenant.v1.dynamic_entities.hooks.index');
    Route::post('dynamic-entities/hooks', DynEntityHookStoreController::class)->name('api.tenant.v1.dynamic_entities.hooks.store');
    Route::get('dynamic-entities/hooks/{hook}', DynEntityHookShowController::class)->name('api.tenant.v1.dynamic_entities.hooks.show');
    Route::patch('dynamic-entities/hooks/{hook}', DynEntityHookUpdateController::class)->name('api.tenant.v1.dynamic_entities.hooks.update');
    Route::delete('dynamic-entities/hooks/{hook}', DynEntityHookDestroyController::class)->name('api.tenant.v1.dynamic_entities.hooks.destroy');
    Route::post('dynamic-entities/hooks/{hook}/toggle', DynEntityHookToggleController::class)->name('api.tenant.v1.dynamic_entities.hooks.toggle');
    Route::get('dynamic-entities/search-index', DynSearchIndexStatusController::class)->name('api.tenant.v1.dynamic_entities.search_index.status');
    Route::post('dynamic-entities/search-index/actions', DynSearchIndexActionController::class)->name('api.tenant.v1.dynamic_entities.search_index.actions');
});

Route::get('admin/sidebar', SidebarNavAdminShowController::class)->name('api.tenant.v1.admin.sidebar.show');
Route::post('admin/sidebar/items', SidebarNavItemStoreController::class)->name('api.tenant.v1.admin.sidebar.items.store');
Route::patch('admin/sidebar/items/{item}', SidebarNavItemUpdateController::class)->name('api.tenant.v1.admin.sidebar.items.update');
Route::delete('admin/sidebar/items/{item}', SidebarNavItemDestroyController::class)->name('api.tenant.v1.admin.sidebar.items.destroy');
Route::post('admin/sidebar/reorder', SidebarNavReorderController::class)->name('api.tenant.v1.admin.sidebar.reorder');
Route::post('admin/sidebar/seed', SidebarNavSeedController::class)->name('api.tenant.v1.admin.sidebar.seed');
