/**
 * Human-readable guides for tenant roles (Add user / Team & Access).
 */
export type TenantRoleGuide = {
  summary: string;
  assignWhen: string;
};

export const TENANT_ROLE_GUIDES: Record<string, TenantRoleGuide> = {
  viewer: {
    summary: "Dashboard only — no module menus. Add a module role for operational access.",
    assignWhen: "Landing access only, or combine with per-module roles below.",
  },
  manager: {
    summary: "Cross-module operations lead (legacy broad role). Prefer per-module operator roles for new users.",
    assignWhen: "Leads who need multiple modules in one role.",
  },
  tenant_admin: {
    summary:
      "Workspace owner. Full enabled permissions across Team & Access and every on module. Entity access matrix stays empty (fail-open). Do not pair with a restricted job role that has a matrix — missing entity slugs deny.",
    assignWhen: "IT admins or tenant super-users only. One owner role — not Operations admin or Dynamic Entities admin.",
  },
  admin: {
    summary:
      "ATC / Metacoresoft operations admin — a job-title role, not the SaaS owner. Scope is the permissions assigned on this role, not tenant_admin.",
    assignWhen: "Day-to-day operations leads who should not own users, roles, or tenant settings.",
  },
  dynamic_entities_admin: {
    summary:
      "Dynamic Entities module admin: packs, records, fields, entities, printables, reports, and workflows. Not Team & Access owner.",
    assignWhen: "People who configure Dynamic Entities without owning the tenant.",
  },
  dynamic_entities_contributor: {
    summary: "Create and edit Dynamic Entities records.",
    assignWhen: "Staff who work records but do not change entity definitions.",
  },
  dynamic_entities_viewer: {
    summary: "View Dynamic Entities records only.",
    assignWhen: "Observers and auditors for Dynamic Entities packs.",
  },
  billing: {
    summary: "SaaS billing only: plan, seats, usage, and payment portal — not users or roles.",
    assignWhen: "Finance or ops contacts who manage subscription without full tenant admin.",
  },
  ticketing_viewer: {
    summary: "View tickets and queues only.",
    assignWhen: "Observers and auditors.",
  },
  ticketing_contributor: {
    summary: "View tickets and create new tickets.",
    assignWhen: "Staff who raise issues but do not manage queues.",
  },
  ticketing_operator: {
    summary: "Create, assign, and manage tickets.",
    assignWhen: "Service desk operators.",
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
    assignWhen: "Staff uploading evidence.",
  },
  documents_operator: {
    summary: "Upload, organize, and manage document files.",
    assignWhen: "Document controllers per site.",
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
  ai_assistant_user: {
    summary: "Ask the in-app help assistant for how-to and workflow guidance.",
    assignWhen: "Operational users who need in-app help.",
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
