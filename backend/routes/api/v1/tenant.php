<?php

declare(strict_types=1);

use App\Modules\AdminOne\Http\Controllers\V1\SystemConfigBrandingUploadController;
use App\Modules\AdminOne\Http\Controllers\V1\SystemConfigShowController;
use App\Modules\AdminOne\Http\Controllers\V1\SystemConfigUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\IntegrationApiDocsMetaController;
use App\Modules\AdminOne\Http\Controllers\V1\IntegrationApiKeyDestroyController;
use App\Modules\AdminOne\Http\Controllers\V1\IntegrationApiKeyIndexController;
use App\Modules\AdminOne\Http\Controllers\V1\IntegrationApiKeyStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\AdminSettingsShowController;
use App\Modules\AdminOne\Http\Controllers\V1\AdminSettingsUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleCloneController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleCompareController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleDestroyController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleIndexController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleShowController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\RoleUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavAdminShowController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemDestroyController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavItemUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavReorderController;
use App\Modules\AdminOne\Http\Controllers\V1\SidebarNavSeedController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBackupDownloadController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBackupIndexController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBillingCheckoutSessionStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBillingPortalSessionStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBillingShowController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantBillingUsageShowController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantSecuritySettingsShowController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantSecuritySettingsUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserActivityIndexController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserBulkAssignRoleController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserBulkDeactivateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserBulkResetPasswordController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserDeactivateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserDestroyController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserEntraOrgSyncController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserExportController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserIdsController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserImpersonateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserImportController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserIndexController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserOrgChartController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserReactivateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserRevokePasskeysController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserRevokeSessionsController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserSeatUsageController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserStoreController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantUserUpdateController;
use App\Modules\AdminOne\Http\Controllers\V1\TenantWorkspaceDashboardController;
use App\Modules\AdminOne\Http\Controllers\V1\WorkspaceSidebarShowController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantActionCancelController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantActionConfirmController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantAskController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantMetaShowController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantConversationIndexController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantConversationShowController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantFeedbackStoreController;
use App\Modules\AiAssistant\Http\Controllers\V1\AiPromptModuleIndexController;
use App\Modules\AiAssistant\Http\Controllers\V1\AiPromptModuleResetController;
use App\Modules\AiAssistant\Http\Controllers\V1\AiPromptModuleShowController;
use App\Modules\AiAssistant\Http\Controllers\V1\AiPromptModuleUpdateController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeArchiveController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeDestroyController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeIndexController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgePublishController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeReindexController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeShowController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeStoreController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantKnowledgeUpdateController;
use App\Modules\AiAssistant\Http\Controllers\V1\AssistantRetrieveController;
use App\Modules\DynamicEntities\Http\Controllers\V1\AtcExecutiveDashboardController;
use App\Modules\DynamicEntities\Http\Controllers\V1\AtcTicketingBoardController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEntityUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipGraphController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipLayoutController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynRelationshipEdgeDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldGroupUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFieldUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynInvoiceAgingAdjustController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynFinanceReportsController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskRunController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskSyncController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskToggleController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynScheduledTaskUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynEmailTemplateUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportDuplicateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportRenderController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynHtmlReportUpdateController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderAiBuildController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderPreviewController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynReportBuilderSaveController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynSearchIndexActionController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynSearchIndexStatusController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowDestroyController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowIndexController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowShowController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowStoreController;
use App\Modules\DynamicEntities\Http\Controllers\V1\DynWorkflowUpdateController;
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
use App\Modules\DynamicEntities\Http\Controllers\V1\PurchaseMonitoringReportController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalAnalyticsShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalDecideController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalPolicyPublishController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalPolicyShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalPolicyUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalApprovalRerouteController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalAssignableUsersController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalAttachmentDownloadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalAuditIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalCashAdvanceOpenController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDashboardController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDelegationDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDelegationIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDelegationStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDocumentLinkDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalDocumentLinkStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalExportHistoryDownloadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalExportHistoryIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalExportHistoryShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormExportController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormImportController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormLogoShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormLogoStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormMyDraftController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormOutboundFileDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormOutboundFileIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormOutboundFileStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormPublicShareUrlController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormPublishController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormRevisionRestoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormRevisionsIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateCustomDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateCustomShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateCustomStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateCustomUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateFinanceBundleStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplateStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormTemplatesIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormValidateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormWorkflowPreviewController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormWorkspaceExportController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormWorkspaceIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormWorkspaceShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalFormWorkspaceSubmissionsController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalHealthController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalManagerLookupTestController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataLookupController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataRowBulkStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataRowDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataRowIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataRowStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataRowUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataSetDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataSetIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataSetStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMasterDataSetUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMeAttachmentDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMeAttachmentStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMeProfileController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMeSignatureUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalMetadataController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalNotificationIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalNotificationMarkAllReadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalNotificationMarkReadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalNotificationUnreadCountController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPdfLayoutDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPdfLayoutShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPdfLayoutUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormLinkIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormLinkRevealController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormLinkRevokeController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormLinkRotateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormLinkStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicFormShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicPackageDownloadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicRevisionAttachmentStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicRevisionResubmitController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicRevisionShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicSharedAttachmentDownloadController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicSharedSubmissionShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicSubmissionAttachmentStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPublicSubmissionStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalPurchaseRequisitionOpenController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalReportDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalReportIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalReportRunController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalReportStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalReportUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSettingsPublicController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSettingsShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSettingsTestEmailController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSettingsTestWebhookController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSettingsUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionAttachmentDestroyController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionAttachmentStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionCancelController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionCommentIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionCommentStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionDcfResubmitController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionDraftUpdateController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionExportColumnsController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionExportController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionManualFollowUpController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionPrintDataController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionResubmitController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionRevisionController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionShareLinkIndexController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionShareLinkRevokeController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionShareLinkStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionShowController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionStoreController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionSubmitDraftController;
use App\Modules\EApproval\Http\Controllers\V1\EApprovalSubmissionWorkflowPreviewController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideAdminIndexController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideAdminPublishController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideAdminShowController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideAdminUnpublishController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideAdminUpdateController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideIndexController;
use App\Modules\Help\Http\Controllers\V1\HelpGuideShowController;
use App\Modules\Identity\Http\Controllers\V1\TenantAuthController;
use App\Modules\Identity\Http\Controllers\V1\TenantEnvironmentHandoffRedeemController;
use App\Modules\Identity\Http\Controllers\V1\TenantHealthController;
use App\Modules\Identity\Http\Controllers\V1\TenantImpersonationStopController;
use App\Modules\Identity\Http\Controllers\V1\TenantSsoAzureStatusController;
use App\Modules\Identity\Http\Controllers\V1\TenantSsoConfigController;
use App\Modules\Identity\Http\Controllers\V1\TenantSsoController;
use App\Modules\Identity\Http\Controllers\V1\TenantWebAuthnController;
use App\Modules\Notifications\Http\Controllers\V1\TenantNotificationIndexController;
use App\Modules\Notifications\Http\Controllers\V1\TenantNotificationMarkAllReadController;
use App\Modules\Notifications\Http\Controllers\V1\TenantNotificationMarkReadController;
use App\Modules\Notifications\Http\Controllers\V1\TenantNotificationUnreadCountController;
use App\Modules\Tenancy\Http\Controllers\V1\TenantEnvironmentHandoffMintController;
use App\Modules\Tenancy\Http\Controllers\V1\TenantLinkedEnvironmentsController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingAssignableUsersController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingDirectoryUsersController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingAttachmentDownloadController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingAttachmentStoreController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingCommentStoreController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingDashboardController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingMetadataController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingSettingsShowController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingSettingsTestEmailController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingSettingsTestWebhookController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingSettingsUpdateController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingTicketIndexController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingTicketShowController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingTicketStoreController;
use App\Modules\Ticketing\Http\Controllers\V1\TicketingTicketUpdateController;
use App\Modules\Workspace\Http\Controllers\V1\WorkspaceAuditEntityIndexController;
use App\Modules\Workspace\Http\Controllers\V1\WorkspaceAuditExportController;
use App\Modules\Workspace\Http\Controllers\V1\WorkspaceAuditIndexController;
use App\Modules\Workspace\Http\Controllers\V1\WorkspaceSearchController;
use Illuminate\Support\Facades\Route;

Route::get('health', TenantHealthController::class)->name('api.tenant.v1.health');

Route::prefix('auth')->group(function () {
    Route::post('login', [TenantAuthController::class, 'login'])->name('api.tenant.v1.auth.login');
    Route::post('refresh', [TenantAuthController::class, 'refresh'])->name('api.tenant.v1.auth.refresh');
    Route::post('mfa/challenge', [TenantAuthController::class, 'mfaChallenge'])->name('api.tenant.v1.auth.mfa.challenge');
    Route::post('mfa/verify', [TenantAuthController::class, 'mfaVerify'])->name('api.tenant.v1.auth.mfa.verify');
    Route::post('mfa/recovery', [TenantAuthController::class, 'mfaRecovery'])->name('api.tenant.v1.auth.mfa.recovery');
    Route::match(['GET', 'POST'], 'webauthn/login/options', [TenantWebAuthnController::class, 'loginOptions'])
        ->middleware('throttle:20,1')
        ->name('api.tenant.v1.auth.webauthn.login.options');
    Route::post('webauthn/login/verify', [TenantWebAuthnController::class, 'loginVerify'])
        ->middleware('throttle:15,1')
        ->name('api.tenant.v1.auth.webauthn.login.verify');
    Route::get('sso/azure/status', TenantSsoAzureStatusController::class)->name('api.tenant.v1.auth.sso.azure.status');
    Route::get('sso/azure/redirect', [TenantSsoController::class, 'redirect'])->name('api.tenant.v1.auth.sso.azure.redirect');
    Route::get('sso/azure/callback', [TenantSsoController::class, 'callback'])->name('api.tenant.v1.auth.sso.azure.callback');
    Route::post('environment-handoff/redeem', TenantEnvironmentHandoffRedeemController::class)
        ->middleware('throttle:20,1')
        ->name('api.tenant.v1.auth.environment_handoff.redeem');
});

Route::middleware(['throttle:e-approval-public'])->prefix('public/e-approval')->group(function () {
    Route::get('forms/{token}', EApprovalPublicFormShowController::class)->name('api.tenant.v1.e_approval.public.forms.show');
    Route::post('forms/{token}/submissions', EApprovalPublicSubmissionStoreController::class)->name('api.tenant.v1.e_approval.public.submissions.store');
    Route::post('forms/{token}/submissions/{submission}/attachments', EApprovalPublicSubmissionAttachmentStoreController::class)
        ->name('api.tenant.v1.e_approval.public.submissions.attachments.store');
    Route::get('submissions/{submission}/revise', EApprovalPublicRevisionShowController::class)
        ->name('api.tenant.v1.e_approval.public.submissions.revise.show');
    Route::put('submissions/{submission}/resubmit', EApprovalPublicRevisionResubmitController::class)
        ->name('api.tenant.v1.e_approval.public.submissions.resubmit');
    Route::post('submissions/{submission}/attachments', EApprovalPublicRevisionAttachmentStoreController::class)
        ->name('api.tenant.v1.e_approval.public.submissions.attachments.token_store');
    Route::get('package-downloads/{token}', EApprovalPublicPackageDownloadController::class)
        ->name('api.tenant.v1.e_approval.public.package_downloads.show');
    Route::get('shared/{token}', EApprovalPublicSharedSubmissionShowController::class)
        ->name('api.tenant.v1.e_approval.public.shared.show');
    Route::get('shared/{token}/attachments/{attachment}', EApprovalPublicSharedAttachmentDownloadController::class)
        ->name('api.tenant.v1.e_approval.public.shared.attachments.show');
});

Route::middleware(['tenant.sanctum', 'auth:sanctum', 'auth.session', 'auth.mfa', 'auth.passkey'])->group(function () {
    Route::get('me', [TenantAuthController::class, 'me'])->name('api.tenant.v1.auth.me');
    Route::get('workspace/environments', TenantLinkedEnvironmentsController::class)
        ->name('api.tenant.v1.workspace.environments');
    Route::post('workspace/environments/handoff', TenantEnvironmentHandoffMintController::class)
        ->middleware('throttle:20,1')
        ->name('api.tenant.v1.workspace.environments.handoff');
    Route::post('auth/logout', [TenantAuthController::class, 'logout'])->name('api.tenant.v1.auth.logout');
    Route::post('auth/logout-all', [TenantAuthController::class, 'logoutAll'])->name('api.tenant.v1.auth.logout_all');
    Route::get('auth/sessions', [TenantAuthController::class, 'sessions'])->name('api.tenant.v1.auth.sessions');
    Route::delete('auth/sessions/{sessionId}', [TenantAuthController::class, 'revokeSession'])->name('api.tenant.v1.auth.sessions.revoke');
    Route::get('auth/webauthn/credentials', [TenantWebAuthnController::class, 'index'])->name('api.tenant.v1.auth.webauthn.credentials.index');
    // GET allowed too: some reverse proxies turn POST→GET on HTTP→HTTPS redirects.
    Route::match(['GET', 'POST'], 'auth/webauthn/register/options', [TenantWebAuthnController::class, 'registerOptions'])
        ->middleware('throttle:10,1')
        ->name('api.tenant.v1.auth.webauthn.register.options');
    Route::post('auth/webauthn/register/verify', [TenantWebAuthnController::class, 'registerVerify'])
        ->middleware('throttle:10,1')
        ->name('api.tenant.v1.auth.webauthn.register.verify');
    Route::delete('auth/webauthn/credentials/{credentialId}', [TenantWebAuthnController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('api.tenant.v1.auth.webauthn.credentials.destroy');
    Route::post('auth/impersonation/stop', TenantImpersonationStopController::class)->name('api.tenant.v1.auth.impersonation.stop');
    Route::get('notifications', TenantNotificationIndexController::class)->name('api.tenant.v1.notifications.index');
    Route::get('notifications/unread-count', TenantNotificationUnreadCountController::class)->name('api.tenant.v1.notifications.unread_count');
    Route::post('notifications/mark-all-read', TenantNotificationMarkAllReadController::class)->name('api.tenant.v1.notifications.mark_all_read');
    Route::post('notifications/{notification}/read', TenantNotificationMarkReadController::class)->name('api.tenant.v1.notifications.read');
    Route::get('dashboard', TenantWorkspaceDashboardController::class)->name('api.tenant.v1.dashboard');
    Route::get('workspace/search', WorkspaceSearchController::class)->name('api.tenant.v1.workspace.search');
    Route::get('workspace/sidebar', WorkspaceSidebarShowController::class)->name('api.tenant.v1.workspace.sidebar');
    Route::get('workspace/audit/export', WorkspaceAuditExportController::class)->name('api.tenant.v1.workspace.audit.export');
    Route::get('workspace/audit/entity', WorkspaceAuditEntityIndexController::class)->name('api.tenant.v1.workspace.audit.entity');
    Route::get('workspace/audit', WorkspaceAuditIndexController::class)->name('api.tenant.v1.workspace.audit.index');
    Route::middleware(['tenant.module:ai_assistant', 'throttle:assistant'])->group(function () {
        Route::get('assistant/meta', AssistantMetaShowController::class)->name('api.tenant.v1.assistant.meta');
        Route::post('assistant/ask', AssistantAskController::class)->name('api.tenant.v1.assistant.ask');
        Route::post('assistant/retrieve', AssistantRetrieveController::class)->name('api.tenant.v1.assistant.retrieve');
        Route::get('assistant/conversations', AssistantConversationIndexController::class)->name('api.tenant.v1.assistant.conversations.index');
        Route::get('assistant/conversations/{conversation}', AssistantConversationShowController::class)->name('api.tenant.v1.assistant.conversations.show');
        Route::post('assistant/feedback', AssistantFeedbackStoreController::class)->name('api.tenant.v1.assistant.feedback.store');
        Route::post('assistant/actions/confirm', AssistantActionConfirmController::class)->name('api.tenant.v1.assistant.actions.confirm');
        Route::post('assistant/actions/{proposal}/cancel', AssistantActionCancelController::class)->name('api.tenant.v1.assistant.actions.cancel');

        Route::get('assistant/knowledge', AssistantKnowledgeIndexController::class)->name('api.tenant.v1.assistant.knowledge.index');
        Route::post('assistant/knowledge', AssistantKnowledgeStoreController::class)->name('api.tenant.v1.assistant.knowledge.store');
        Route::get('assistant/knowledge/{source}', AssistantKnowledgeShowController::class)->name('api.tenant.v1.assistant.knowledge.show');
        Route::put('assistant/knowledge/{source}', AssistantKnowledgeUpdateController::class)->name('api.tenant.v1.assistant.knowledge.update');
        Route::post('assistant/knowledge/{source}/publish', AssistantKnowledgePublishController::class)->name('api.tenant.v1.assistant.knowledge.publish');
        Route::post('assistant/knowledge/{source}/archive', AssistantKnowledgeArchiveController::class)->name('api.tenant.v1.assistant.knowledge.archive');
        Route::post('assistant/knowledge/{source}/reindex', AssistantKnowledgeReindexController::class)->name('api.tenant.v1.assistant.knowledge.reindex');
        Route::delete('assistant/knowledge/{source}', AssistantKnowledgeDestroyController::class)->name('api.tenant.v1.assistant.knowledge.destroy');

        Route::get('assistant/prompt-modules', AiPromptModuleIndexController::class)->name('api.tenant.v1.assistant.prompt_modules.index');
        Route::get('assistant/prompt-modules/{module}', AiPromptModuleShowController::class)->name('api.tenant.v1.assistant.prompt_modules.show');
        Route::patch('assistant/prompt-modules/{module}', AiPromptModuleUpdateController::class)->name('api.tenant.v1.assistant.prompt_modules.update');
        Route::post('assistant/prompt-modules/{module}/reset', AiPromptModuleResetController::class)->name('api.tenant.v1.assistant.prompt_modules.reset');
    });
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
        Route::get('dynamic-entities/search-index', DynSearchIndexStatusController::class)->name('api.tenant.v1.dynamic_entities.search_index.status');
        Route::post('dynamic-entities/search-index/actions', DynSearchIndexActionController::class)->name('api.tenant.v1.dynamic_entities.search_index.actions');
    });
    Route::get('ticketing/dashboard', TicketingDashboardController::class)->name('api.tenant.v1.ticketing.dashboard');
    Route::get('ticketing/settings', TicketingSettingsShowController::class)->name('api.tenant.v1.ticketing.settings.show');
    Route::put('ticketing/settings', TicketingSettingsUpdateController::class)->name('api.tenant.v1.ticketing.settings.update');
    Route::post('ticketing/settings/test-email', TicketingSettingsTestEmailController::class)->name('api.tenant.v1.ticketing.settings.test_email');
    Route::post('ticketing/settings/test-webhook', TicketingSettingsTestWebhookController::class)->name('api.tenant.v1.ticketing.settings.test_webhook');
    Route::get('ticketing/metadata', TicketingMetadataController::class)->name('api.tenant.v1.ticketing.metadata');
    Route::get('ticketing/assignable-users', TicketingAssignableUsersController::class)->name('api.tenant.v1.ticketing.assignable_users');
    Route::get('ticketing/directory-users', TicketingDirectoryUsersController::class)->name('api.tenant.v1.ticketing.directory_users');
    Route::get('ticketing/tickets', TicketingTicketIndexController::class)->name('api.tenant.v1.ticketing.tickets.index');
    Route::post('ticketing/tickets', TicketingTicketStoreController::class)->name('api.tenant.v1.ticketing.tickets.store');
    Route::get('ticketing/tickets/{ticket}', TicketingTicketShowController::class)->name('api.tenant.v1.ticketing.tickets.show');
    Route::patch('ticketing/tickets/{ticket}', TicketingTicketUpdateController::class)->name('api.tenant.v1.ticketing.tickets.update');
    Route::post('ticketing/tickets/{ticket}/comments', TicketingCommentStoreController::class)->name('api.tenant.v1.ticketing.tickets.comments.store');
    Route::post('ticketing/tickets/{ticket}/attachments', TicketingAttachmentStoreController::class)->name('api.tenant.v1.ticketing.tickets.attachments.store');
    Route::get('ticketing/attachments/{attachment}', TicketingAttachmentDownloadController::class)->name('api.tenant.v1.ticketing.attachments.show');
    Route::get('e-approval/health', EApprovalHealthController::class)->name('api.tenant.v1.e_approval.health');
    Route::get('e-approval/assignable-users', EApprovalAssignableUsersController::class)->name('api.tenant.v1.e_approval.assignable_users');
    Route::post('e-approval/workflow/test-manager-lookup', EApprovalManagerLookupTestController::class)->name('api.tenant.v1.e_approval.workflow.test_manager_lookup');
    Route::get('e-approval/dashboard', EApprovalDashboardController::class)->name('api.tenant.v1.e_approval.dashboard');
    Route::get('e-approval/workspaces', EApprovalFormWorkspaceIndexController::class)->name('api.tenant.v1.e_approval.workspaces.index');
    Route::get('e-approval/workspaces/{slug}', EApprovalFormWorkspaceShowController::class)->name('api.tenant.v1.e_approval.workspaces.show');
    Route::get('e-approval/workspaces/{slug}/export', EApprovalFormWorkspaceExportController::class)->name('api.tenant.v1.e_approval.workspaces.export');
    Route::get('e-approval/workspaces/{slug}/submissions', EApprovalFormWorkspaceSubmissionsController::class)->name('api.tenant.v1.e_approval.workspaces.submissions');
    Route::get('e-approval/form-templates', EApprovalFormTemplatesIndexController::class)->name('api.tenant.v1.e_approval.form_templates.index');
    Route::post('e-approval/form-templates', EApprovalFormTemplateStoreController::class)->name('api.tenant.v1.e_approval.form_templates.store');
    Route::post('e-approval/form-templates/finance-procurement-bundle', EApprovalFormTemplateFinanceBundleStoreController::class)->name('api.tenant.v1.e_approval.form_templates.finance_bundle.store');
    Route::post('e-approval/form-templates/custom', EApprovalFormTemplateCustomStoreController::class)->name('api.tenant.v1.e_approval.form_templates.custom.store');
    Route::get('e-approval/form-templates/custom/{templateId}', EApprovalFormTemplateCustomShowController::class)->name('api.tenant.v1.e_approval.form_templates.custom.show');
    Route::put('e-approval/form-templates/custom/{templateId}', EApprovalFormTemplateCustomUpdateController::class)->name('api.tenant.v1.e_approval.form_templates.custom.update');
    Route::delete('e-approval/form-templates/custom/{templateId}', EApprovalFormTemplateCustomDestroyController::class)->name('api.tenant.v1.e_approval.form_templates.custom.destroy');
    Route::get('e-approval/forms', EApprovalFormIndexController::class)->name('api.tenant.v1.e_approval.forms.index');
    Route::post('e-approval/forms', EApprovalFormStoreController::class)->name('api.tenant.v1.e_approval.forms.store');
    Route::post('e-approval/forms/import', EApprovalFormImportController::class)->name('api.tenant.v1.e_approval.forms.import');
    Route::post('e-approval/forms/validate', EApprovalFormValidateController::class)->name('api.tenant.v1.e_approval.forms.validate');
    Route::get('e-approval/audit', EApprovalAuditIndexController::class)->name('api.tenant.v1.e_approval.audit.index');
    Route::get('e-approval/submissions/export/columns', EApprovalSubmissionExportColumnsController::class)->name('api.tenant.v1.e_approval.submissions.export.columns');
    Route::get('e-approval/submissions/export', EApprovalSubmissionExportController::class)->name('api.tenant.v1.e_approval.submissions.export');
    Route::get('e-approval/reports', EApprovalReportIndexController::class)->name('api.tenant.v1.e_approval.reports.index');
    Route::get('e-approval/reports/analytics', EApprovalAnalyticsShowController::class)->name('api.tenant.v1.e_approval.reports.analytics');
    Route::post('e-approval/reports', EApprovalReportStoreController::class)->name('api.tenant.v1.e_approval.reports.store');
    Route::put('e-approval/reports/{report}', EApprovalReportUpdateController::class)->name('api.tenant.v1.e_approval.reports.update');
    Route::delete('e-approval/reports/{report}', EApprovalReportDestroyController::class)->name('api.tenant.v1.e_approval.reports.destroy');
    Route::post('e-approval/reports/{report}/run', EApprovalReportRunController::class)->name('api.tenant.v1.e_approval.reports.run');
    Route::get('e-approval/export-history', EApprovalExportHistoryIndexController::class)->name('api.tenant.v1.e_approval.export_history.index');
    Route::get('e-approval/export-history/{history}', EApprovalExportHistoryShowController::class)->name('api.tenant.v1.e_approval.export_history.show');
    Route::get('e-approval/export-history/{history}/download', EApprovalExportHistoryDownloadController::class)->name('api.tenant.v1.e_approval.export_history.download');
    Route::get('e-approval/forms/{form}/revisions', EApprovalFormRevisionsIndexController::class)->name('api.tenant.v1.e_approval.forms.revisions');
    Route::post('e-approval/forms/{form}/revisions/{revision}/restore', EApprovalFormRevisionRestoreController::class)->name('api.tenant.v1.e_approval.forms.revisions.restore');
    Route::get('e-approval/forms/{form}/my-draft', EApprovalFormMyDraftController::class)->name('api.tenant.v1.e_approval.forms.my_draft');
    Route::post('e-approval/forms/{form}/workflow-preview', EApprovalFormWorkflowPreviewController::class)->name('api.tenant.v1.e_approval.forms.workflow_preview');
    Route::get('e-approval/forms/{form}', EApprovalFormShowController::class)->name('api.tenant.v1.e_approval.forms.show');
    Route::put('e-approval/forms/{form}', EApprovalFormUpdateController::class)->name('api.tenant.v1.e_approval.forms.update');
    Route::delete('e-approval/forms/{form}', EApprovalFormDestroyController::class)->name('api.tenant.v1.e_approval.forms.destroy');
    Route::post('e-approval/forms/{form}/publish', EApprovalFormPublishController::class)->name('api.tenant.v1.e_approval.forms.publish');
    Route::get('e-approval/forms/{form}/public-links', EApprovalPublicFormLinkIndexController::class)->name('api.tenant.v1.e_approval.forms.public_links.index');
    Route::post('e-approval/forms/{form}/public-links', EApprovalPublicFormLinkStoreController::class)->name('api.tenant.v1.e_approval.forms.public_links.store');
    Route::get('e-approval/forms/{form}/public-share-url', EApprovalFormPublicShareUrlController::class)->name('api.tenant.v1.e_approval.forms.public_share_url');
    Route::get('e-approval/forms/{form}/outbound-files', EApprovalFormOutboundFileIndexController::class)->name('api.tenant.v1.e_approval.forms.outbound_files.index');
    Route::post('e-approval/forms/{form}/outbound-files', EApprovalFormOutboundFileStoreController::class)->name('api.tenant.v1.e_approval.forms.outbound_files.store');
    Route::delete('e-approval/outbound-files/{outboundFile}', EApprovalFormOutboundFileDestroyController::class)->name('api.tenant.v1.e_approval.outbound_files.destroy');
    Route::post('e-approval/public-links/{publicLink}/revoke', EApprovalPublicFormLinkRevokeController::class)->name('api.tenant.v1.e_approval.public_links.revoke');
    Route::post('e-approval/public-links/{publicLink}/rotate', EApprovalPublicFormLinkRotateController::class)->name('api.tenant.v1.e_approval.public_links.rotate');
    Route::post('e-approval/public-links/{publicLink}/reveal', EApprovalPublicFormLinkRevealController::class)->name('api.tenant.v1.e_approval.public_links.reveal');
    Route::get('e-approval/forms/{form}/logo', EApprovalFormLogoShowController::class)->name('api.tenant.v1.e_approval.forms.logo.show');
    Route::post('e-approval/forms/{form}/logo', EApprovalFormLogoStoreController::class)->name('api.tenant.v1.e_approval.forms.logo');
    Route::get('e-approval/forms/{form}/export', EApprovalFormExportController::class)->name('api.tenant.v1.e_approval.forms.export');
    Route::get('e-approval/pdf-layout/{formId}', EApprovalPdfLayoutShowController::class)->name('api.tenant.v1.e_approval.pdf_layout.show');
    Route::put('e-approval/pdf-layout/{formId}', EApprovalPdfLayoutUpdateController::class)->name('api.tenant.v1.e_approval.pdf_layout.update');
    Route::delete('e-approval/pdf-layout/{formId}', EApprovalPdfLayoutDestroyController::class)->name('api.tenant.v1.e_approval.pdf_layout.destroy');
    Route::get('e-approval/submissions', EApprovalSubmissionIndexController::class)->name('api.tenant.v1.e_approval.submissions.index');
    Route::post('e-approval/submissions', EApprovalSubmissionStoreController::class)->name('api.tenant.v1.e_approval.submissions.store');
    Route::get('e-approval/submissions/{submission}/print', EApprovalSubmissionPrintDataController::class)->name('api.tenant.v1.e_approval.submissions.print');
    Route::get('e-approval/submissions/{submission}/workflow-preview', EApprovalSubmissionWorkflowPreviewController::class)->name('api.tenant.v1.e_approval.submissions.workflow_preview');
    Route::get('e-approval/submissions/{submission}', EApprovalSubmissionShowController::class)->name('api.tenant.v1.e_approval.submissions.show');
    Route::put('e-approval/submissions/{submission}/draft', EApprovalSubmissionDraftUpdateController::class)->name('api.tenant.v1.e_approval.submissions.draft.update');
    Route::post('e-approval/submissions/{submission}/submit', EApprovalSubmissionSubmitDraftController::class)->name('api.tenant.v1.e_approval.submissions.submit_draft');
    Route::post('e-approval/submissions/{submission}/cancel', EApprovalSubmissionCancelController::class)->name('api.tenant.v1.e_approval.submissions.cancel');
    Route::put('e-approval/submissions/{submission}/resubmit', EApprovalSubmissionResubmitController::class)->name('api.tenant.v1.e_approval.submissions.resubmit');
    Route::get('e-approval/submissions/{submission}/comments', EApprovalSubmissionCommentIndexController::class)->name('api.tenant.v1.e_approval.submissions.comments.index');
    Route::post('e-approval/submissions/{submission}/comments', EApprovalSubmissionCommentStoreController::class)->name('api.tenant.v1.e_approval.submissions.comments.store');
    Route::post('e-approval/submissions/{submission}/attachments', EApprovalSubmissionAttachmentStoreController::class)->name('api.tenant.v1.e_approval.submissions.attachments.store');
    Route::get('e-approval/attachments/{attachment}', EApprovalAttachmentDownloadController::class)->name('api.tenant.v1.e_approval.attachments.show');
    Route::delete('e-approval/attachments/{attachment}', EApprovalSubmissionAttachmentDestroyController::class)->name('api.tenant.v1.e_approval.attachments.destroy');
    Route::get('e-approval/approvals', EApprovalApprovalIndexController::class)->name('api.tenant.v1.e_approval.approvals.index');
    Route::post('e-approval/approvals/{approval}/decide', EApprovalApprovalDecideController::class)->name('api.tenant.v1.e_approval.approvals.decide');
    Route::post('e-approval/approvals/{approval}/reroute', EApprovalApprovalRerouteController::class)->name('api.tenant.v1.e_approval.approvals.reroute');
    Route::get('e-approval/notifications', EApprovalNotificationIndexController::class)->name('api.tenant.v1.e_approval.notifications.index');
    Route::get('e-approval/notifications/unread-count', EApprovalNotificationUnreadCountController::class)->name('api.tenant.v1.e_approval.notifications.unread_count');
    Route::post('e-approval/notifications/mark-all-read', EApprovalNotificationMarkAllReadController::class)->name('api.tenant.v1.e_approval.notifications.mark_all_read');
    Route::post('e-approval/notifications/{notification}/read', EApprovalNotificationMarkReadController::class)->name('api.tenant.v1.e_approval.notifications.read');
    Route::get('e-approval/metadata', EApprovalMetadataController::class)->name('api.tenant.v1.e_approval.metadata');
    Route::get('e-approval/cash-advances/open', EApprovalCashAdvanceOpenController::class)->name('api.tenant.v1.e_approval.cash_advances.open');
    Route::get('e-approval/purchase-requisitions/open', EApprovalPurchaseRequisitionOpenController::class)->name('api.tenant.v1.e_approval.purchase_requisitions.open');
    Route::get('e-approval/settings', EApprovalSettingsShowController::class)->name('api.tenant.v1.e_approval.settings.show');
    Route::post('e-approval/settings/test-webhook', EApprovalSettingsTestWebhookController::class)
        ->name('api.tenant.v1.e_approval.settings.test_webhook');
    Route::post('e-approval/settings/test-email', EApprovalSettingsTestEmailController::class)
        ->name('api.tenant.v1.e_approval.settings.test_email');
    Route::put('e-approval/settings', EApprovalSettingsUpdateController::class)->name('api.tenant.v1.e_approval.settings.update');
    Route::get('e-approval/approval-policies', EApprovalApprovalPolicyShowController::class)->name('api.tenant.v1.e_approval.approval_policies.show');
    Route::put('e-approval/approval-policies', EApprovalApprovalPolicyUpdateController::class)->name('api.tenant.v1.e_approval.approval_policies.update');
    Route::post('e-approval/approval-policies/publish', EApprovalApprovalPolicyPublishController::class)->name('api.tenant.v1.e_approval.approval_policies.publish');
    Route::get('e-approval/settings/public', EApprovalSettingsPublicController::class)->name('api.tenant.v1.e_approval.settings.public');

    Route::get('help/guides', HelpGuideIndexController::class)->name('api.tenant.v1.help.guides.index');
    Route::get('help/guides/{slug}', HelpGuideShowController::class)->name('api.tenant.v1.help.guides.show');
    Route::get('help/admin/guides', HelpGuideAdminIndexController::class)->name('api.tenant.v1.help.admin.guides.index');
    Route::get('help/admin/guides/{slug}', HelpGuideAdminShowController::class)->name('api.tenant.v1.help.admin.guides.show');
    Route::put('help/admin/guides/{slug}', HelpGuideAdminUpdateController::class)->name('api.tenant.v1.help.admin.guides.update');
    Route::post('help/admin/guides/{slug}/publish', HelpGuideAdminPublishController::class)->name('api.tenant.v1.help.admin.guides.publish');
    Route::post('help/admin/guides/{slug}/unpublish', HelpGuideAdminUnpublishController::class)->name('api.tenant.v1.help.admin.guides.unpublish');

    Route::get('e-approval/master-data/{key}', EApprovalMasterDataLookupController::class)->name('api.tenant.v1.e_approval.master_data.lookup');
    Route::get('e-approval/master-data-sets', EApprovalMasterDataSetIndexController::class)->name('api.tenant.v1.e_approval.master_data_sets.index');
    Route::post('e-approval/master-data-sets', EApprovalMasterDataSetStoreController::class)->name('api.tenant.v1.e_approval.master_data_sets.store');
    Route::put('e-approval/master-data-sets/{set}', EApprovalMasterDataSetUpdateController::class)->name('api.tenant.v1.e_approval.master_data_sets.update');
    Route::delete('e-approval/master-data-sets/{set}', EApprovalMasterDataSetDestroyController::class)->name('api.tenant.v1.e_approval.master_data_sets.destroy');
    Route::get('e-approval/master-data-sets/{set}/rows', EApprovalMasterDataRowIndexController::class)->name('api.tenant.v1.e_approval.master_data_rows.index');
    Route::post('e-approval/master-data-sets/{set}/rows', EApprovalMasterDataRowStoreController::class)->name('api.tenant.v1.e_approval.master_data_rows.store');
    Route::post('e-approval/master-data-sets/{set}/rows/bulk', EApprovalMasterDataRowBulkStoreController::class)->name('api.tenant.v1.e_approval.master_data_rows.bulk');
    Route::put('e-approval/master-data-rows/{row}', EApprovalMasterDataRowUpdateController::class)->name('api.tenant.v1.e_approval.master_data_rows.update');
    Route::delete('e-approval/master-data-rows/{row}', EApprovalMasterDataRowDestroyController::class)->name('api.tenant.v1.e_approval.master_data_rows.destroy');
    Route::get('e-approval/delegations', EApprovalDelegationIndexController::class)->name('api.tenant.v1.e_approval.delegations.index');
    Route::post('e-approval/delegations', EApprovalDelegationStoreController::class)->name('api.tenant.v1.e_approval.delegations.store');
    Route::delete('e-approval/delegations/{delegation}', EApprovalDelegationDestroyController::class)->name('api.tenant.v1.e_approval.delegations.destroy');
    Route::get('e-approval/me/profile', EApprovalMeProfileController::class)->name('api.tenant.v1.e_approval.me.profile');
    Route::put('e-approval/me/signature', EApprovalMeSignatureUpdateController::class)->name('api.tenant.v1.e_approval.me.signature');
    Route::post('e-approval/me/attachments', EApprovalMeAttachmentStoreController::class)->name('api.tenant.v1.e_approval.me.attachments.store');
    Route::delete('e-approval/me/attachments/{attachment}', EApprovalMeAttachmentDestroyController::class)->name('api.tenant.v1.e_approval.me.attachments.destroy');
    Route::post('e-approval/submissions/{submission}/revision', EApprovalSubmissionRevisionController::class)->name('api.tenant.v1.e_approval.submissions.revision');
    Route::put('e-approval/submissions/{submission}/dcf-resubmit', EApprovalSubmissionDcfResubmitController::class)->name('api.tenant.v1.e_approval.submissions.dcf_resubmit');
    Route::post('e-approval/submissions/{submission}/manual-follow-up', EApprovalSubmissionManualFollowUpController::class)->name('api.tenant.v1.e_approval.submissions.manual_follow_up');
    Route::get('e-approval/submissions/{submission}/share-links', EApprovalSubmissionShareLinkIndexController::class)->name('api.tenant.v1.e_approval.submissions.share_links.index');
    Route::post('e-approval/submissions/{submission}/share-links', EApprovalSubmissionShareLinkStoreController::class)->name('api.tenant.v1.e_approval.submissions.share_links.store');
    Route::post('e-approval/share-links/{shareLink}/revoke', EApprovalSubmissionShareLinkRevokeController::class)->name('api.tenant.v1.e_approval.share_links.revoke');
    Route::post('e-approval/submissions/{submission}/document-links', EApprovalDocumentLinkStoreController::class)->name('api.tenant.v1.e_approval.document_links.store');
    Route::delete('e-approval/document-links/{link}', EApprovalDocumentLinkDestroyController::class)->name('api.tenant.v1.e_approval.document_links.destroy');
    Route::post('auth/mfa/recovery-codes/regenerate', [TenantAuthController::class, 'mfaRecoveryCodesRegenerate'])->name('api.tenant.v1.auth.mfa.recovery_codes.regenerate');

    Route::prefix('admin')->group(function () {
        Route::get('sso/config', [TenantSsoConfigController::class, 'show'])->name('api.tenant.v1.admin.sso.config.show');
        Route::put('sso/config', [TenantSsoConfigController::class, 'update'])->name('api.tenant.v1.admin.sso.config.update');
        Route::post('sso/test-connection', [TenantSsoConfigController::class, 'testConnection'])->name('api.tenant.v1.admin.sso.test');
        Route::get('security', TenantSecuritySettingsShowController::class)->name('api.tenant.v1.admin.security.show');
        Route::patch('security', TenantSecuritySettingsUpdateController::class)->name('api.tenant.v1.admin.security.update');
        Route::get('users', TenantUserIndexController::class)->name('api.tenant.v1.admin.users.index');
        Route::get('users/ids', TenantUserIdsController::class)->name('api.tenant.v1.admin.users.ids');
        Route::get('users/org-chart', TenantUserOrgChartController::class)->name('api.tenant.v1.admin.users.org_chart');
        Route::post('users/entra-org-sync', TenantUserEntraOrgSyncController::class)
            ->middleware('throttle:10,1')
            ->name('api.tenant.v1.admin.users.entra_org_sync');
        Route::get('users/seat-usage', TenantUserSeatUsageController::class)->name('api.tenant.v1.admin.users.seat_usage');
        Route::get('users/export', TenantUserExportController::class)->name('api.tenant.v1.admin.users.export');
        Route::post('users', TenantUserStoreController::class)->name('api.tenant.v1.admin.users.store');
        Route::post('users/import', TenantUserImportController::class)->name('api.tenant.v1.admin.users.import');
        Route::post('users/bulk-deactivate', TenantUserBulkDeactivateController::class)->name('api.tenant.v1.admin.users.bulk_deactivate');
        Route::post('users/bulk-assign-role', TenantUserBulkAssignRoleController::class)->name('api.tenant.v1.admin.users.bulk_assign_role');
        Route::post('users/bulk-reset-password', TenantUserBulkResetPasswordController::class)->name('api.tenant.v1.admin.users.bulk_reset_password');
        Route::patch('users/{user}', TenantUserUpdateController::class)->name('api.tenant.v1.admin.users.update');
        Route::get('users/{user}/activity', TenantUserActivityIndexController::class)->name('api.tenant.v1.admin.users.activity');
        Route::post('users/{user}/revoke-sessions', TenantUserRevokeSessionsController::class)->name('api.tenant.v1.admin.users.revoke_sessions');
        Route::post('users/{user}/revoke-passkeys', TenantUserRevokePasskeysController::class)
            ->middleware('throttle:20,1')
            ->name('api.tenant.v1.admin.users.revoke_passkeys');
        Route::post('users/{user}/impersonate', TenantUserImpersonateController::class)
            ->middleware('throttle:10,1')
            ->name('api.tenant.v1.admin.users.impersonate');
        Route::post('users/{user}/deactivate', TenantUserDeactivateController::class)->name('api.tenant.v1.admin.users.deactivate');
        Route::post('users/{user}/reactivate', TenantUserReactivateController::class)->name('api.tenant.v1.admin.users.reactivate');
        Route::delete('users/{user}', TenantUserDestroyController::class)->name('api.tenant.v1.admin.users.destroy');
        Route::get('roles', RoleIndexController::class)->name('api.tenant.v1.admin.roles.index');
        Route::get('roles/compare', RoleCompareController::class)->name('api.tenant.v1.admin.roles.compare');
        Route::get('roles/{role}', RoleShowController::class)->name('api.tenant.v1.admin.roles.show');
        Route::post('roles', RoleStoreController::class)->name('api.tenant.v1.admin.roles.store');
        Route::post('roles/{role}/clone', RoleCloneController::class)->name('api.tenant.v1.admin.roles.clone');
        Route::patch('roles/{role}', RoleUpdateController::class)->name('api.tenant.v1.admin.roles.update');
        Route::delete('roles/{role}', RoleDestroyController::class)->name('api.tenant.v1.admin.roles.destroy');
        Route::get('billing', TenantBillingShowController::class)->name('api.tenant.v1.admin.billing.show');
        Route::get('billing/usage', TenantBillingUsageShowController::class)->name('api.tenant.v1.admin.billing.usage');
        Route::post('billing/checkout-session', TenantBillingCheckoutSessionStoreController::class)
            ->middleware('throttle:20,1')
            ->name('api.tenant.v1.admin.billing.checkout');
        Route::post('billing/portal-session', TenantBillingPortalSessionStoreController::class)
            ->middleware('throttle:20,1')
            ->name('api.tenant.v1.admin.billing.portal');
        Route::get('settings', AdminSettingsShowController::class)->name('api.tenant.v1.admin.settings.show');
        Route::patch('settings', AdminSettingsUpdateController::class)->name('api.tenant.v1.admin.settings.update');
        Route::get('sidebar', SidebarNavAdminShowController::class)->name('api.tenant.v1.admin.sidebar.show');
        Route::post('sidebar/items', SidebarNavItemStoreController::class)->name('api.tenant.v1.admin.sidebar.items.store');
        Route::patch('sidebar/items/{item}', SidebarNavItemUpdateController::class)->name('api.tenant.v1.admin.sidebar.items.update');
        Route::delete('sidebar/items/{item}', SidebarNavItemDestroyController::class)->name('api.tenant.v1.admin.sidebar.items.destroy');
        Route::post('sidebar/reorder', SidebarNavReorderController::class)->name('api.tenant.v1.admin.sidebar.reorder');
        Route::post('sidebar/seed', SidebarNavSeedController::class)->name('api.tenant.v1.admin.sidebar.seed');
        Route::get('api-keys', IntegrationApiKeyIndexController::class)->name('api.tenant.v1.admin.api_keys.index');
        Route::get('api-keys/docs-meta', IntegrationApiDocsMetaController::class)
            ->name('api.tenant.v1.admin.api_keys.docs_meta');
        Route::post('api-keys', IntegrationApiKeyStoreController::class)
            ->middleware('throttle:20,1')
            ->name('api.tenant.v1.admin.api_keys.store');
        Route::delete('api-keys/{token}', IntegrationApiKeyDestroyController::class)
            ->whereNumber('token')
            ->name('api.tenant.v1.admin.api_keys.destroy');
        Route::get('system', SystemConfigShowController::class)->name('api.tenant.v1.admin.system.show');
        Route::patch('system', SystemConfigUpdateController::class)->name('api.tenant.v1.admin.system.update');
        Route::post('system/branding/{asset}', SystemConfigBrandingUploadController::class)
            ->whereIn('asset', ['logo', 'favicon'])
            ->middleware('throttle:20,1')
            ->name('api.tenant.v1.admin.system.branding.upload');
        Route::get('backups', TenantBackupIndexController::class)->name('api.tenant.v1.admin.backups.index');
        Route::get('backups/{backup}/download', TenantBackupDownloadController::class)
            ->middleware('throttle:30,1')
            ->name('api.tenant.v1.admin.backups.download');
    });
});

/*
|--------------------------------------------------------------------------
| Integration API (machine keys — no interactive session / MFA / passkey)
|--------------------------------------------------------------------------
| Auth: Bearer token, X-API-Key, or ?api_key= with Sanctum ability "integration".
| Authorization: inherits the minting user's Spatie + DynRoleAccess permissions.
*/
Route::middleware([
    'integration.api_key',
    'tenant.sanctum',
    'auth:sanctum',
    'integration.token',
    'throttle:120,1',
])->prefix('integration')->group(function () {
    Route::middleware('tenant.module:dynamic_entities')->group(function () {
        Route::get('dynamic-entities/entities', DynEntityIndexController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.entities.index');
        Route::get('dynamic-entities/entities/{entity}', DynEntityShowController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.entities.show');
        Route::get('dynamic-entities/entities/{entity}/records', DynRecordIndexController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.index');
        Route::post('dynamic-entities/entities/{entity}/records', DynRecordStoreController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.store');
        Route::get('dynamic-entities/records/{record}', DynRecordShowController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.show');
        Route::patch('dynamic-entities/records/{record}', DynRecordUpdateController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.update');
        Route::delete('dynamic-entities/records/{record}', DynRecordDestroyController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.destroy');
        Route::post('dynamic-entities/records/{record}/workflow-actions/{action}', DynRecordWorkflowActionController::class)
            ->name('api.tenant.v1.integration.dynamic_entities.records.workflow_action');
    });
});

Route::middleware(['tenant.sanctum', 'auth:sanctum', 'auth.session'])->group(function () {
    Route::post('auth/mfa/enroll/start', [TenantAuthController::class, 'mfaEnrollStart'])->name('api.tenant.v1.auth.mfa.enroll.start');
    Route::post('auth/mfa/enroll/complete', [TenantAuthController::class, 'mfaEnrollComplete'])->name('api.tenant.v1.auth.mfa.enroll.complete');
});
