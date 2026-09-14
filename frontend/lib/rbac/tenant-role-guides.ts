/**
 * Human-readable guides for tenant roles (Add user / Team & Access).
 */
export type TenantRoleGuide = {
  summary: string;
  gateSteps?: string;
  assignWhen: string;
  alsoSetOnRollout?: string;
};

export const TENANT_ROLE_GUIDES: Record<string, TenantRoleGuide> = {
  viewer: {
    summary: "Dashboard only — no module menus. Add a module role (e.g. e_approval_viewer) for module access.",
    assignWhen: "Landing access only, or combine with per-module roles below.",
  },
  admin: {
    summary: "ATC Admin — broad operational access across Dynamic Entities and workspace modules (not full tenant settings).",
    assignWhen: "Senior operations leads (Metacoresoft Admin equivalent).",
  },
  administrator: {
    summary: "Full tenant administrator — users, roles, settings, and all enabled modules.",
    assignWhen: "IT admins / Metacoresoft Administrator equivalent.",
  },
  commercial_sales_officer: {
    summary: "Commercial and sales transactions, Dynamic Entity records, and related finance views.",
    assignWhen: "Commercial / Sales Officer job role.",
  },
  finance_officer: {
    summary: "Finance operations across Dynamic Entities and related approvals.",
    assignWhen: "Finance Officer job role.",
  },
  procurement_officer: {
    summary: "Procurement Dynamic Entity records, vendors, and related approvals.",
    assignWhen: "Procurement Officer job role.",
  },
  project_manager: {
    summary: "Construction / PM Dynamic Entities, ticketing, and related approvals.",
    assignWhen: "Project Manager job role.",
  },
  sa_officer: {
    summary: "Site acquisition field work via Dynamic Entities and documents.",
    assignWhen: "SA Officer job role.",
  },
  sales: {
    summary: "Sales transactions and related Dynamic Entity records.",
    assignWhen: "Sales users.",
  },
  staff: {
    summary: "Basic staff access: view Dynamic Entities, raise tickets and E-Forms submissions.",
    assignWhen: "General staff.",
  },
  manager: {
    summary: "Cross-module operations lead (legacy broad role). Prefer per-module operator roles for new users.",
    assignWhen: "Senior leads who need multiple modules in one role.",
  },
  tenant_admin: {
    summary: "Full tenant access: users, roles, settings, and all modules (includes billing).",
    assignWhen: "IT admins or tenant super-users only.",
  },
  billing: {
    summary: "SaaS billing only: plan, seats, usage, and payment portal — not users or roles.",
    assignWhen: "Finance or ops contacts who manage subscription without full tenant admin.",
  },
  finance: {
    summary: "Legacy finance role — prefer module-specific operator roles for new users.",
    assignWhen: "Commercial analysts with broad read access.",
  },
  ticketing_viewer: {
    summary: "View tickets and queues only.",
    assignWhen: "NOC observers and auditors.",
  },
  ticketing_contributor: {
    summary: "View tickets and create new tickets.",
    assignWhen: "Staff who raise issues but do not manage queues.",
  },
  ticketing_operator: {
    summary: "Create, assign, and manage tickets.",
    assignWhen: "Service desk and NOC operators.",
  },
  ticketing_admin: {
    summary: "Full ticketing plus module settings.",
    assignWhen: "Ticketing module owners.",
  },
  documents_viewer: {
    summary: "Browse site documents only.",
    assignWhen: "Compliance viewers.",
  },
  documents_contributor: {
    summary: "View and upload site documents.",
    assignWhen: "Field staff uploading evidence.",
  },
  documents_operator: {
    summary: "Upload, organize, and manage document files.",
    assignWhen: "Document controllers per program.",
  },
  documents_approver: {
    summary: "View and manage site documents.",
    assignWhen: "Document reviewers who do not manage controlled documents.",
  },
  documents_admin: {
    summary: "Full site document management plus binder templates.",
    assignWhen: "Records management leads.",
  },
  dcf_viewer: {
    summary: "View the Controlled Document Register and download files — read-only.",
    assignWhen: "Staff who need read access to controlled documents.",
  },
  dcf_author: {
    summary: "Submit new controlled documents and revisions via E-Forms.",
    assignWhen: "Process owners and document authors.",
  },
  dcf_approver: {
    summary: "Approve controlled-document E-Forms workflow steps.",
    assignWhen: "Department heads and designated approvers.",
  },
  dcf_controller: {
    summary: "Full DCF operations: publish, obsolete, manage metadata and revisions.",
    assignWhen: "Document controllers and quality managers.",
  },
  dcf_admin: {
    summary: "Full DCF control plus bulk import, E-Forms form management, and audit.",
    assignWhen: "Quality system administrators.",
  },
  sites_viewer: {
    summary: "Sites map and registry: view only.",
    assignWhen: "Network planning viewers.",
  },
  ai_assistant_user: {
    summary: "Ask INFRA SUITE help assistant for how-to and workflow guidance.",
    assignWhen: "All operational users who need in-app help.",
  },
  ai_assistant_admin: {
    summary: "Manage assistant knowledge sources and audit conversations.",
    assignWhen: "Tenant process owners and knowledge admins.",
  },
  e_approval_viewer: {
    summary: "View submissions and status — no create or approve.",
    assignWhen: "Auditors tracking approval status.",
  },
  e_approval_requestor: {
    summary: "Create submissions and resubmit returned forms.",
    assignWhen: "Staff who only submit forms.",
  },
  e_approval_approver: {
    summary: "Approval inbox: review and decide.",
    assignWhen: "Line managers and approvers.",
  },
  e_approval_admin: {
    summary: "Forms, policies, audit, and E-Forms settings.",
    assignWhen: "Process owners.",
  },
};

export function getTenantRoleGuide(roleName: string): TenantRoleGuide | null {
  return TENANT_ROLE_GUIDES[roleName] ?? null;
}
