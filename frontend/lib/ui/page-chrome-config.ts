/**
 * Declarative page header chrome — title, description, and which header actions show.
 * Persisted via DashboardLayoutPrefs.pageChrome.
 */

export type PageChromeActionId = string;

export type PageChromeActionDef = {
  id: PageChromeActionId;
  label: string;
  /** When false, always shown and not togglable in Customize. */
  hideable?: boolean;
};

export type PageChromePrefs = {
  title?: string;
  description?: string;
  /** Action ids hidden from the header. */
  hiddenActionIds?: string[];
};

export type PageChromeDefaults = {
  title: string;
  description: string;
  actions: PageChromeActionDef[];
};

export function resolvePageChrome(
  defaults: PageChromeDefaults,
  prefs?: PageChromePrefs | null,
): {
  title: string;
  description: string;
  visibleActionIds: string[];
  hiddenActionIds: string[];
} {
  const hidden = new Set(prefs?.hiddenActionIds ?? []);
  const visibleActionIds = defaults.actions
    .filter((action) => action.hideable === false || !hidden.has(action.id))
    .map((action) => action.id);

  return {
    title: prefs?.title?.trim() || defaults.title,
    description: prefs?.description?.trim() || defaults.description,
    visibleActionIds,
    hiddenActionIds: [...hidden],
  };
}

export function isActionVisible(
  chrome: { visibleActionIds: string[] },
  actionId: PageChromeActionId,
): boolean {
  return chrome.visibleActionIds.includes(actionId);
}

export const DOC_EXTRACT_BATCHES_PAGE_CHROME: PageChromeDefaults = {
  title: "DocExtract",
  description:
    "Upload → consolidate pages → extract → customize columns → export. Source files purge after 7 days; filenames stay for audit.",
  actions: [
    { id: "help", label: "Help", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
    { id: "templates", label: "Templates", hideable: true },
    { id: "new", label: "New extraction", hideable: true },
  ],
};

export const E_APPROVAL_REPORTS_PAGE_CHROME: PageChromeDefaults = {
  title: "Reports",
  description:
    "Analytics, exports, saved reports, and download history. Use Customize to reorder sections and open Layout & options on each widget.",
  actions: [
    { id: "customize", label: "Customize", hideable: false },
  ],
};

export const TICKETING_DASHBOARD_PAGE_CHROME: PageChromeDefaults = {
  title: "Ticketing",
  description:
    "Cross-module issue tracking — raise tickets from any INFRA SUITE module or manually.",
  actions: [
    { id: "help", label: "Tour chapters", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
    { id: "refresh", label: "Refresh", hideable: true },
    { id: "new", label: "New ticket", hideable: true },
  ],
};

export const E_APPROVAL_DASHBOARD_PAGE_CHROME: PageChromeDefaults = {
  title: "E-Forms",
  description: "Your inbox for approvals, returns, and open requests.",
  actions: [
    { id: "help", label: "Tour guide", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
    { id: "refresh", label: "Refresh", hideable: true },
    { id: "approvals", label: "Approvals", hideable: true },
    { id: "new", label: "New submission", hideable: true },
  ],
};

export const E_APPROVAL_WORKSPACE_PAGE_CHROME: PageChromeDefaults = {
  title: "Form workspace",
  description: "Operational dashboard for this approval form.",
  actions: [
    { id: "customize", label: "Customize", hideable: false },
    { id: "refresh", label: "Refresh", hideable: true },
    { id: "export", label: "Export", hideable: true },
    { id: "approvals", label: "My approvals", hideable: true },
    { id: "new", label: "New request", hideable: true },
  ],
};

export const TICKETING_TICKETS_PAGE_CHROME: PageChromeDefaults = {
  title: "Tickets",
  description: "Operational issue queue for your workspace.",
  actions: [
    { id: "help", label: "Tour chapters", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
    { id: "new", label: "New ticket", hideable: true },
  ],
};

export const E_APPROVAL_SUBMISSIONS_PAGE_CHROME: PageChromeDefaults = {
  title: "Submissions",
  description:
    "Track requests in workflow. Switch between gallery and table to match how you work.",
  actions: [
    { id: "help", label: "Tour guide", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
    { id: "export", label: "Export", hideable: true },
    { id: "new", label: "New submission", hideable: true },
  ],
};

export const E_APPROVAL_APPROVALS_PAGE_CHROME: PageChromeDefaults = {
  title: "Approval inbox",
  description: "One row per document. Open a submission to approve or reject.",
  actions: [
    { id: "help", label: "Tour guide", hideable: true },
    { id: "tour", label: "Start tour", hideable: true },
    { id: "customize", label: "Customize", hideable: false },
  ],
};

export const E_APPROVAL_FORMS_PAGE_CHROME: PageChromeDefaults = {
  title: "Forms",
  description:
    "Design templates and workflows. Switch gallery or table to manage at your pace. Volume and status trends live under Reports.",
  actions: [
    { id: "customize", label: "Customize", hideable: false },
    { id: "templates", label: "Templates", hideable: true },
    { id: "import", label: "Import JSON", hideable: true },
    { id: "new", label: "New form", hideable: true },
  ],
};

export const DOC_EXTRACT_TEMPLATES_PAGE_CHROME: PageChromeDefaults = {
  title: "Templates",
  description:
    "Create reusable column definitions. Only published templates appear when extracting documents; drafts stay here until you publish.",
  actions: [
    { id: "customize", label: "Customize", hideable: false },
    { id: "new", label: "Create template", hideable: true },
  ],
};

export const WORKSPACE_DASHBOARD_PAGE_CHROME: PageChromeDefaults = {
  title: "Dashboard",
  description:
    "Work awaiting you across modules — gate approvals, e-approvals, tickets, and SLA risk.",
  actions: [
    { id: "customize", label: "Customize", hideable: false },
    { id: "refresh", label: "Refresh", hideable: true },
  ],
};
