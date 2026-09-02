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
    summary: "Dashboard only — no module menus. Add a module role (e.g. dynamic_entities_viewer) for module access.",
    assignWhen: "Landing access only, or combine with per-module roles below.",
  },
  admin: {
    summary:
      "ATC Admin — broad operational access across Dynamic Entities, ticketing, and E-Approval (not full tenant settings).",
    assignWhen: "Senior operations leads (Metacoresoft Admin equivalent).",
  },
  administrator: {
    summary: "Full tenant administrator — users, roles, settings, billing, and all enabled modules.",
    assignWhen: "IT admins / Metacoresoft Administrator equivalent (one full-admin role).",
  },
  commercial_sales_officer: {
    summary: "Commercial and sales transactions, Dynamic Entity records, and related finance views.",
    assignWhen: "Commercial / Sales Officer job role.",
  },
  finance_officer: {
    summary: "Finance operations via Dynamic Entities: ledger, payments, contracts, and approvals.",
    assignWhen: "Finance Officer job role.",
  },
  procurement_officer: {
    summary: "Procurement via Dynamic Entities: purchase requests, vendors, inventory, and related approvals.",
    assignWhen: "Procurement Officer job role.",
  },
  project_manager: {
    summary: "Site and construction Dynamic Entities, ticketing, and E-Approval workflows.",
    assignWhen: "Project Manager job role.",
  },
  sa_officer: {
    summary: "Site acquisition field work: SAQ Dynamic Entities and related site records.",
    assignWhen: "SA Officer job role.",
  },
  sales: {
    summary: "Sales transactions and related Dynamic Entity records.",
    assignWhen: "Sales users.",
  },
  staff: {
    summary: "Basic staff access: view Dynamic Entities, raise tickets and E-Approval submissions.",
    assignWhen: "General staff.",
  },
  billing: {
    summary: "SaaS billing only: plan, seats, usage, and payment portal — not users or roles.",
    assignWhen: "Finance or ops contacts who manage subscription without full tenant admin.",
  },
  finance: {
    summary: "Legacy finance role — prefer Dynamic Entities access via finance_officer or ATC admin roles.",
    assignWhen: "Commercial analysts migrating from legacy Finance-One.",
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
  ai_assistant_user: {
    summary: "Ask TowerOS help assistant for how-to and workflow guidance.",
    assignWhen: "All operational users who need in-app help.",
  },
  ai_assistant_admin: {
    summary: "Configure AI prompts and audit assistant conversations.",
    assignWhen: "Tenant admins who own AI Assistant settings.",
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
    summary: "Forms, policies, audit, and E-Approval settings.",
    assignWhen: "Process owners.",
  },
};

export function getTenantRoleGuide(roleName: string): TenantRoleGuide | null {
  return TENANT_ROLE_GUIDES[roleName] ?? null;
}
