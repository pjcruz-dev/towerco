/**
 * Maps TowerOS Spatie permissions onto Metacoresoft-style Role Management tabs.
 * Entity matrix columns share module-level keys (TowerOS has no per-entity ACL yet).
 */

export type DataAccessColumn = "view" | "view_own" | "create" | "edit" | "delete" | "export";

export type DataAccessRow = {
  id: string;
  label: string;
  /** Permission name per column; omit when not available in TowerOS. */
  columns: Partial<Record<DataAccessColumn, string>>;
};

export type WorkflowAction = {
  id: string;
  label: string;
  permission: string;
};

export type WorkflowGroup = {
  id: string;
  label: string;
  actions: WorkflowAction[];
};

/** Global / system cards for the General System tab (order matters). */
export const GENERAL_SYSTEM_PERMISSIONS: Array<{
  permission: string;
  title: string;
  description: string;
}> = [
  {
    permission: "dashboard:view",
    title: "View Dashboard",
    description: "Open the workspace home and operational overview.",
  },
  {
    permission: "user:manage",
    title: "Manage Users",
    description: "Invite, deactivate, and assign roles to users.",
  },
  {
    permission: "role:manage",
    title: "Manage Roles",
    description: "Create and edit custom roles and their permissions.",
  },
  {
    permission: "tenant:manage",
    title: "Tenant Settings",
    description: "Manage organization settings, modules, and branding.",
  },
  {
    permission: "user:impersonate",
    title: "Impersonate Users",
    description: "Sign in as another user for support (audited).",
  },
  {
    permission: "workspace:audit:view",
    title: "View Audit Trail",
    description: "Read workspace activity and security events.",
  },
  {
    permission: "workspace:environments:switch",
    title: "Switch Environments",
    description: "Move between staging and production workspaces.",
  },
  {
    permission: "sidebar:manage",
    title: "Manage Sidebar",
    description: "Allow user to manage the sidebar menu.",
  },
  {
    permission: "notifications:manage",
    title: "Manage Notifications",
    description: "Manage automation rules for notifications.",
  },
  {
    permission: "printables:manage",
    title: "Manage Printables",
    description: "Allow user to manage printable templates.",
  },
  {
    permission: "api_keys:manage",
    title: "Manage REST API",
    description: "Mint and revoke integration API keys; view developer docs.",
  },
  {
    permission: "system:manage",
    title: "Manage System",
    description: "Configure branding, theme, localization, support, and integrations.",
  },
  {
    permission: "html_reports:manage",
    title: "Manage HTML Reports",
    description: "Create and edit dynamic HTML/CSS/JS reports.",
  },
  {
    permission: "workflows:manage",
    title: "Manage Workflows",
    description: "Create and manage database-driven transactional workflow steps.",
  },
  {
    permission: "email_templates:manage",
    title: "Email Templates",
    description: "Create and edit reusable email subject/body templates for workflows.",
  },
  {
    permission: "automation:manage",
    title: "Automation & Cron Jobs",
    description: "Manage scheduled tasks and run Laravel artisan jobs on a cron.",
  },
  {
    permission: "search_index:manage",
    title: "Search Index",
    description: "Inspect and rebuild the workspace search / filter index.",
  },
  {
    permission: "ai_assistant:prompts:manage",
    title: "Manage AI Prompts",
    description: "Edit modular AI system prompt modules assembled by intent.",
  },
  {
    permission: "dynamic_entities:fields:manage",
    title: "Manage Fields",
    description: "Configure Dynamic Entity fields, groups, and form layout.",
  },
  {
    permission: "dynamic_entities:entities:manage",
    title: "Manage Entities",
    description: "Create and configure Dynamic Entity definitions.",
  },
  {
    permission: "billing:view",
    title: "View Billing",
    description: "See subscription and billing status.",
  },
  {
    permission: "billing:manage",
    title: "Manage Billing",
    description: "Change plans and billing settings.",
  },
];

export const DATA_ACCESS_ROWS: DataAccessRow[] = [
  {
    id: "dynamic_entities",
    label: "Dynamic Entities (all packs)",
    columns: {
      view: "dynamic_entities:view",
      create: "dynamic_entities:records:manage",
      edit: "dynamic_entities:records:manage",
      delete: "dynamic_entities:records:manage",
    },
  },
  {
    id: "e_approval",
    label: "E-Approval",
    columns: {
      view: "e_approval:view",
      create: "e_approval:submissions:create",
      edit: "e_approval:forms:manage",
      delete: "e_approval:forms:manage",
      export: "e_approval:submissions:view",
    },
  },
  {
    id: "ticketing",
    label: "Ticketing",
    columns: {
      view: "ticketing:view",
      create: "ticketing:tickets:create",
      edit: "ticketing:tickets:manage",
      delete: "ticketing:tickets:manage",
    },
  },
];

export const DATA_ACCESS_COLUMNS: Array<{ id: DataAccessColumn; label: string }> = [
  { id: "view", label: "View" },
  { id: "view_own", label: "View Own" },
  { id: "create", label: "Create" },
  { id: "edit", label: "Edit" },
  { id: "delete", label: "Delete" },
  { id: "export", label: "Export" },
];

export const WORKFLOW_GROUPS: WorkflowGroup[] = [
  {
    id: "e_approval",
    label: "E-Approval",
    actions: [
      { id: "ea_approve", label: "Approve submissions", permission: "e_approval:approve" },
      { id: "ea_audit", label: "View approval audit", permission: "e_approval:audit:view" },
      { id: "ea_settings", label: "Manage approval settings", permission: "e_approval:settings:manage" },
    ],
  },
];

/** Permissions that belong on General System / Workflow tabs (not the entity matrix). */
export function isGeneralOrWorkflowPermission(permission: string): boolean {
  if (GENERAL_SYSTEM_PERMISSIONS.some((p) => p.permission === permission)) return true;
  if (WORKFLOW_GROUPS.some((g) => g.actions.some((a) => a.permission === permission))) return true;
  return false;
}
