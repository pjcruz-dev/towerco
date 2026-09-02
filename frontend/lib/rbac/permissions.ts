import type { AuthUser } from "@/types/auth";

export const permissions = {
  dashboardView: "dashboard:view",
  workspaceAuditView: "workspace:audit:view",
  workspaceEnvironmentsSwitch: "workspace:environments:switch",
  sidebarManage: "sidebar:manage",
  notificationsManage: "notifications:manage",
  printablesManage: "printables:manage",
  apiKeysManage: "api_keys:manage",
  systemManage: "system:manage",
  htmlReportsManage: "html_reports:manage",
  workflowsManage: "workflows:manage",
  emailTemplatesManage: "email_templates:manage",
  automationManage: "automation:manage",
  searchIndexManage: "search_index:manage",
  tenantManage: "tenant:manage",
  billingView: "billing:view",
  billingManage: "billing:manage",
  userManage: "user:manage",
  userImpersonate: "user:impersonate",
  roleManage: "role:manage",
  dynamicEntitiesView: "dynamic_entities:view",
  dynamicEntitiesRecordsManage: "dynamic_entities:records:manage",
  dynamicEntitiesFieldsManage: "dynamic_entities:fields:manage",
  dynamicEntitiesEntitiesManage: "dynamic_entities:entities:manage",
  eApprovalView: "e_approval:view",
  eApprovalFormsManage: "e_approval:forms:manage",
  eApprovalSubmissionsCreate: "e_approval:submissions:create",
  eApprovalSubmissionsView: "e_approval:submissions:view",
  eApprovalApprove: "e_approval:approve",
  eApprovalAuditView: "e_approval:audit:view",
  eApprovalSettingsManage: "e_approval:settings:manage",
  ticketingView: "ticketing:view",
  ticketingTicketsCreate: "ticketing:tickets:create",
  ticketingTicketsManage: "ticketing:tickets:manage",
  ticketingSettingsManage: "ticketing:settings:manage",
  aiAssistantUse: "ai_assistant:use",
  aiAssistantToolsUse: "ai_assistant:tools:use",
  aiAssistantActionsExecute: "ai_assistant:actions:execute",
  aiAssistantKnowledgeManage: "ai_assistant:knowledge:manage",
  aiAssistantPromptsManage: "ai_assistant:prompts:manage",
  aiAssistantConversationsAudit: "ai_assistant:conversations:audit",
} as const;

export function hasPermission(
  user: AuthUser | null,
  requiredPermissions: string[] = [],
): boolean {
  if (!user) return false;
  if (requiredPermissions.length === 0) return true;

  return requiredPermissions.every((permission) =>
    user.permissions.includes(permission),
  );
}
