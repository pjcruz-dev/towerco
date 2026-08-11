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
    summary: "Dashboard only — no module menus. Add a module role (e.g. procurement_viewer) for module access.",
    assignWhen: "Landing access only, or combine with per-module roles below.",
  },
  manager: {
    summary: "Cross-module operations lead (legacy broad role). Prefer per-module operator roles for new users.",
    gateSteps: "Can act on saq, pmo, cme gates when assigned on rollouts.",
    assignWhen: "Senior rollout leads who need multiple modules in one role.",
    alsoSetOnRollout: "Set SAQ / PMO / CME owner on each rollout.",
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
    summary: "Project profitability plus read-only Finance-One reports.",
    assignWhen: "Commercial analysts — use finance_viewer / finance_operator for Finance-One only.",
  },
  project_one_viewer: {
    summary: "Project-One & rollouts: view only.",
    assignWhen: "Executives or MNO viewers for rollout status.",
  },
  project_one_contributor: {
    summary: "View rollouts and edit SAQ / CME field data (resubmit returned gate work).",
    assignWhen: "SAQ engineers and CME field staff without PMO sign-off.",
  },
  project_one_operator: {
    summary: "Manage rollouts, approve gates, and run SAQ/CME workflows.",
    assignWhen: "Rollout managers and regional leads.",
  },
  project_one_admin: {
    summary: "Project-One operator plus playbook configuration and finance views.",
    assignWhen: "PMO admins who configure rollout playbooks.",
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
  procurement_viewer: {
    summary: "View PR, PO, RFQ, vendors, and inventory — no create or edit.",
    assignWhen: "Finance or audit read-only on procurement.",
  },
  procurement_contributor: {
    summary: "Create PR/PO/RFQ and E-Approval submissions; view and resubmit returned forms.",
    assignWhen: "Requestors and buyers who do not approve or manage vendors.",
  },
  procurement_operator: {
    summary: "Full procurement operations: documents, vendors, inventory.",
    assignWhen: "Procurement officers and buyers.",
  },
  procurement_admin: {
    summary: "Procurement operator plus module settings and E-Approval approvals.",
    assignWhen: "Head of procurement.",
  },
  finance_viewer: {
    summary: "Finance-One: budget, AP, payments, contracts — view only.",
    assignWhen: "Controllers and auditors.",
  },
  finance_contributor: {
    summary: "Submit AP invoices and E-Approval forms; view and resubmit returned items.",
    assignWhen: "AP clerks who create but do not approve payments.",
  },
  finance_operator: {
    summary: "Manage budget, AP, payments, contracts, and reports.",
    assignWhen: "Finance operations team.",
  },
  finance_admin: {
    summary: "Finance operator plus Finance-One settings.",
    assignWhen: "Finance module owners.",
  },
  documents_viewer: {
    summary: "Browse site and rollout documents only.",
    assignWhen: "Compliance viewers.",
  },
  documents_contributor: {
    summary: "View and upload site or rollout documents.",
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
    summary: "Submit new controlled documents and revisions via E-Approval.",
    assignWhen: "Process owners and document authors.",
  },
  dcf_approver: {
    summary: "Approve controlled-document E-Approval workflow steps.",
    assignWhen: "Department heads and designated approvers.",
  },
  dcf_controller: {
    summary: "Full DCF operations: publish, obsolete, manage metadata and revisions.",
    assignWhen: "Document controllers and quality managers.",
  },
  dcf_admin: {
    summary: "Full DCF control plus bulk import, E-Approval form management, and audit.",
    assignWhen: "Quality system administrators.",
  },
  sites_viewer: {
    summary: "Sites map and registry: view only.",
    assignWhen: "Network planning viewers.",
  },
  ai_assistant_user: {
    summary: "Ask TowerOS help assistant for how-to and workflow guidance.",
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
    summary: "Forms, policies, audit, and E-Approval settings.",
    assignWhen: "Process owners.",
  },
  saq_approver: {
    summary: "SAQ gate steps and hunting discipline (add-on to project_one roles).",
    gateSteps: "Approves gates whose current step is saq.",
    assignWhen: "Dedicated SAQ leads.",
    alsoSetOnRollout: "Set as SAQ owner on rollouts.",
  },
  pmo_approver: {
    summary: "PMO gate steps and rollout metadata (add-on).",
    gateSteps: "Approves gates whose current step is pmo.",
    assignWhen: "BD PMO after SAQ.",
    alsoSetOnRollout: "Set as PMO owner on rollouts.",
  },
  cme_approver: {
    summary: "CME gate steps and construction reports (add-on).",
    gateSteps: "Approves gates whose current step is cme.",
    assignWhen: "Construction managers.",
    alsoSetOnRollout: "Set as CME PM on rollouts.",
  },
};

export function getTenantRoleGuide(roleName: string): TenantRoleGuide | null {
  return TENANT_ROLE_GUIDES[roleName] ?? null;
}
