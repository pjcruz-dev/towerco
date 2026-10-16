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

export type GeneralPermissionCard = {
  permission: string;
  title: string;
  description: string;
  /** Destructive or wide-reaching. Shown with a warning mark. */
  danger?: boolean;
};

export type GeneralPermissionGroup = {
  id: string;
  title: string;
  description: string;
  cards: GeneralPermissionCard[];
};

/**
 * General System groups, in Cloudworkz order.
 * Cards whose permission is not in the tenant catalog are hidden by the page.
 */
export const GENERAL_SYSTEM_GROUPS: GeneralPermissionGroup[] = [
  {
    id: "access",
    title: "Access Control",
    description: "Who administers the system and who can see other people's data.",
    cards: [
      {
        permission: "user:manage",
        title: "Manage Users",
        description: "Create users, reset them and assign their roles.",
      },
      {
        permission: "role:manage",
        title: "Manage Roles",
        description: "Define roles and assign the permissions on this page.",
      },
      {
        permission: "organization:view",
        title: "View Organization",
        description: "Look up the organization directory without being able to change it.",
      },
      {
        permission: "organization:manage",
        title: "Manage Organization",
        description: "Edit organization structure and who belongs where.",
      },
      {
        permission: "user:impersonate",
        title: "Impersonate Users",
        description: "Sign in as another user for support. This is audited.",
        danger: true,
      },
      {
        permission: "dashboard:view",
        title: "View Dashboard",
        description: "Open the workspace home and operational overview.",
      },
    ],
  },
  {
    id: "data_tools",
    title: "Data Tools",
    description: "Power tools that change many records at once. Grant these deliberately.",
    cards: [
      {
        permission: "documents:controlled:import",
        title: "Bulk Import (documents)",
        description: "Upload a spreadsheet and create or update controlled documents in bulk.",
        danger: true,
      },
      {
        permission: "assets:import",
        title: "Bulk Import (assets)",
        description: "Upload a spreadsheet and create or update asset records in bulk.",
        danger: true,
      },
      {
        permission: "doc-extract:run",
        title: "Smart Scan",
        description: "Pull fields out of photos, PDFs and documents with DocExtract.",
        danger: true,
      },
      {
        permission: "ai_assistant:use",
        title: "AI Assistant",
        description: "Open the assistant. What it may do is set on the AI Assistant tab.",
      },
      {
        permission: "ai_assistant:tools:use",
        title: "AI Look-ups",
        description: "Let the assistant read live records for this role.",
      },
      {
        permission: "ai_assistant:actions:execute",
        title: "AI Actions",
        description: "Let the assistant create and update records when the AI Assistant tab allows it.",
        danger: true,
      },
      {
        permission: "workspace:audit:view",
        title: "View Audit Logs",
        description: "Read the audit trail of who changed what.",
      },
      {
        permission: "ai_assistant:conversations:audit",
        title: "Audit AI Conversations",
        description: "Read assistant conversations in this workspace.",
      },
    ],
  },
  {
    id: "customization",
    title: "Customization",
    description: "Changing how the system is built, rather than what is in it.",
    cards: [
      {
        permission: "dynamic_entities:entities:manage",
        title: "Manage Entities",
        description: "Add, rename and remove entities.",
      },
      {
        permission: "dynamic_entities:fields:manage",
        title: "Manage Fields",
        description: "Add and configure the fields on an entity, and the form tools.",
      },
      {
        permission: "sidebar:manage",
        title: "Manage Sidebar",
        description: "Decide what appears in the navigation menu.",
      },
      {
        permission: "html_reports:manage",
        title: "Manage Reports",
        description: "Create and edit HTML reports and dashboards.",
      },
      {
        permission: "printables:manage",
        title: "Manage Printables",
        description: "Design printable templates and letterheads.",
      },
      {
        permission: "notifications:manage",
        title: "Manage Notifications",
        description: "Set up the rules that raise alerts automatically.",
      },
      {
        permission: "workflows:manage",
        title: "Manage Workflows",
        description: "Create and manage workflow steps.",
      },
      {
        permission: "email_templates:manage",
        title: "Email Templates",
        description: "Create and edit reusable email subject and body templates.",
      },
      {
        permission: "ai_assistant:prompts:manage",
        title: "AI System Prompts",
        description: "Edit the prompt modules the assistant is assembled from.",
      },
    ],
  },
  {
    id: "system",
    title: "System Core",
    description: "Settings and maintenance that affect the whole installation.",
    cards: [
      {
        permission: "tenant:manage",
        title: "System Settings",
        description: "Organization settings, modules and branding.",
        danger: true,
      },
      {
        permission: "system:manage",
        title: "Manage System",
        description: "Branding, theme, localization, support and integrations.",
        danger: true,
      },
      {
        permission: "workspace:environments:switch",
        title: "Switch Environments",
        description: "Move between linked workspaces.",
        danger: true,
      },
      {
        permission: "billing:manage",
        title: "Manage Billing",
        description: "Change plans and billing settings.",
        danger: true,
      },
      {
        permission: "automation:manage",
        title: "Automation & Cron Jobs",
        description: "Manage scheduled tasks.",
        danger: true,
      },
      {
        permission: "entity_hooks:manage",
        title: "Manage Entity Hooks",
        description: "Configure lifecycle hooks for Dynamic Entities.",
        danger: true,
      },
      {
        permission: "api_keys:manage",
        title: "Manage REST API",
        description: "Mint and revoke integration API keys.",
      },
      {
        permission: "search_index:manage",
        title: "Search Index",
        description: "Inspect and rebuild the workspace search index.",
      },
    ],
  },
  {
    id: "other",
    title: "Other",
    description: "Permissions delivered outside the groups above.",
    cards: [
      {
        permission: "billing:view",
        title: "View Billing",
        description: "See subscription and billing status.",
      },
    ],
  },
];

/** Flat list kept for callers that only need the permission names. */
export const GENERAL_SYSTEM_PERMISSIONS: GeneralPermissionCard[] = GENERAL_SYSTEM_GROUPS.flatMap(
  (group) => group.cards,
);

/** Module permissions shown on Data Access when Dynamic Entities is off. */
export const MODULE_ACCESS_ROWS: Array<DataAccessRow & { module: string }> = [
  {
    id: "ticketing",
    module: "ticketing",
    label: "Ticketing",
    columns: {
      view: "ticketing:view",
      create: "ticketing:tickets:create",
      edit: "ticketing:tickets:manage",
    },
  },
  {
    id: "e_approval",
    module: "e_approval",
    label: "E-Forms",
    columns: {
      view: "e_approval:view",
      create: "e_approval:submissions:create",
      edit: "e_approval:forms:manage",
      export: "e_approval:submissions:view",
    },
  },
  {
    id: "doc_extract",
    module: "doc_extract",
    label: "DocExtract",
    columns: {
      view: "doc-extract:view",
      create: "doc-extract:run",
      edit: "doc-extract:templates:manage",
      export: "doc-extract:export",
    },
  },
  {
    id: "document_register",
    module: "document_register",
    label: "Document register",
    columns: {
      view: "documents:controlled:view",
      create: "documents:controlled:create",
      edit: "documents:controlled:manage",
    },
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
  ...MODULE_ACCESS_ROWS,
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
