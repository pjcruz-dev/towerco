/**
 * Human labels for shared UI preference keys (admin audit).
 */

const DASHBOARD_LABELS: Record<string, string> = {
  "toweros.workspace.dashboard.layout": "Home · Dashboard",
  "toweros.ticketing.dashboard.layout": "Ticketing · Overview",
  "toweros.ticketing.tickets.layout": "Ticketing · Tickets",
  "toweros.doc-extract.dashboard.layout": "DocExtract · Batches",
  "toweros.doc-extract.templates.layout": "DocExtract · Templates",
  "toweros.e-approval.dashboard.layout": "E-Forms · Overview",
  "toweros.e-approval.submissions.layout": "E-Forms · Submissions",
  "toweros.e-approval.approvals.layout": "E-Forms · Approvals",
  "toweros.e-approval.forms.layout": "E-Forms · Forms catalog",
  "toweros.e-approval.reports.dashboard.layout.v2": "E-Forms · Reports",
};

export function labelSharedUiLayoutKey(key: string): string {
  if (key.startsWith("dashboard-layout.")) {
    const storage = key.slice("dashboard-layout.".length);
    if (DASHBOARD_LABELS[storage]) return DASHBOARD_LABELS[storage];
    if (storage.startsWith("toweros.e-approval.workspace.layout.")) {
      const slug = storage.slice("toweros.e-approval.workspace.layout.".length);
      return `E-Forms · Workspace (${slug || "form"})`;
    }
    return `Page board · ${storage}`;
  }
  if (key.startsWith("module-list.")) {
    return `Column layouts · ${key.slice("module-list.".length)}`;
  }
  return key;
}
