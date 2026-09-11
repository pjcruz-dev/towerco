/**
 * Declarative page header chrome — title, description, and which header actions show.
 * Persisted via DashboardLayoutPrefs.pageChrome.
 */

export type PageChromeActionId = string;

export type PageChromeActionGroup = "guidance" | "layout" | "primary";

export type PageChromeActionDef = {
  id: PageChromeActionId;
  label: string;
  /** Short “what this is for” shown in Customize. */
  purpose?: string;
  /** Visual grouping in the Header actions panel. */
  group?: PageChromeActionGroup;
  /** When false, always shown and not togglable in Customize. */
  hideable?: boolean;
};

export const PAGE_CHROME_ACTION_GROUP_LABELS: Record<PageChromeActionGroup, string> = {
  guidance: "Guidance",
  layout: "Layout",
  primary: "Primary actions",
};

export function resolvePageChromeActionGroup(action: PageChromeActionDef): PageChromeActionGroup {
  if (action.group) return action.group;
  if (action.id === "help" || action.id === "tour") return "guidance";
  if (action.id === "customize" || action.id === "layout_preset") return "layout";
  return "primary";
}

/** Fallback purpose when a page chrome action omits one. */
export function pageChromeActionPurpose(action: PageChromeActionDef): string {
  if (action.purpose?.trim()) return action.purpose.trim();
  switch (action.id) {
    case "help":
      return "Open the help panel or tour chapter list for this module.";
    case "tour":
      return "Launch the interactive product tour for first-time operators.";
    case "customize":
      return "Enter layout mode — reorder widgets, widths, and page chrome.";
    case "refresh":
      return "Reload live metrics and queues without leaving the page.";
    case "new":
      return "Create a new record (ticket, submission, or extraction).";
    case "approvals":
      return "Jump to items waiting on your decision.";
    case "templates":
      return "Open templates used to start new work.";
    case "export":
      return "Download the current list or open export options.";
    default:
      return "Show this control in the page header.";
  }
}

export type PageChromePrefs = {
  title?: string;
  description?: string;
  /** Action ids hidden from the header. */
  hiddenActionIds?: string[];
  /** Custom left→right order for header action buttons. */
  actionOrder?: PageChromeActionId[];
  /** Where the title + description block sits relative to actions. */
  identityPlacement?: PageChromeIdentityPlacement;
};

/** Title/description placement relative to the action cluster. */
export type PageChromeIdentityPlacement = "start" | "end" | "above" | "below";

export const PAGE_CHROME_IDENTITY_PLACEMENTS: Array<{
  id: PageChromeIdentityPlacement;
  label: string;
  purpose: string;
}> = [
  { id: "start", label: "Title left", purpose: "Classic ops layout — title left, actions right." },
  { id: "end", label: "Title right", purpose: "Actions first on the left; title anchors the right." },
  { id: "above", label: "Title above", purpose: "Full-width title row, actions on the row below." },
  { id: "below", label: "Title below", purpose: "Actions on top; title/description under them." },
];

export type PageChromeDefaults = {
  title: string;
  description: string;
  actions: PageChromeActionDef[];
  /**
   * Hideable actions hidden until the user opts them back on in Customize.
   * Applied only when `prefs.hiddenActionIds` is unset (fresh layout).
   */
  defaultHiddenActionIds?: PageChromeActionId[];
};

function mergeActionOrder(
  preferred: string[] | undefined,
  defaults: string[],
): string[] {
  const allowed = new Set(defaults);
  const out: string[] = [];
  for (const id of preferred ?? []) {
    if (!allowed.has(id) || out.includes(id)) continue;
    out.push(id);
  }
  for (const id of defaults) {
    if (!out.includes(id)) out.push(id);
  }
  return out;
}

export function resolvePageChrome(
  defaults: PageChromeDefaults,
  prefs?: PageChromePrefs | null,
): {
  title: string;
  description: string;
  visibleActionIds: string[];
  hiddenActionIds: string[];
  actionOrder: string[];
  identityPlacement: PageChromeIdentityPlacement;
} {
  const hidden = new Set(
    prefs?.hiddenActionIds !== undefined
      ? prefs.hiddenActionIds
      : (defaults.defaultHiddenActionIds ?? []),
  );
  const defaultIds = defaults.actions.map((action) => action.id);
  const actionOrder = mergeActionOrder(prefs?.actionOrder, defaultIds);
  const visibleActionIds = actionOrder.filter((id) => {
    const action = defaults.actions.find((item) => item.id === id);
    if (!action) return false;
    return action.hideable === false || !hidden.has(id);
  });

  const placement = prefs?.identityPlacement;
  const identityPlacement: PageChromeIdentityPlacement =
    placement === "start" || placement === "end" || placement === "above" || placement === "below"
      ? placement
      : "start";

  return {
    title: prefs?.title?.trim() || defaults.title,
    description: prefs?.description?.trim() || defaults.description,
    visibleActionIds,
    hiddenActionIds: [...hidden],
    actionOrder,
    identityPlacement,
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
  defaultHiddenActionIds: ["tour"],
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
    {
      id: "help",
      label: "Tour chapters",
      purpose: "Browse help chapters without starting the full tour.",
      group: "guidance",
      hideable: true,
    },
    {
      id: "tour",
      label: "Start tour",
      purpose: "Walk new operators through this page step by step.",
      group: "guidance",
      hideable: true,
    },
    {
      id: "customize",
      label: "Customize",
      purpose: "Reorder widgets, set widths, and edit this header.",
      group: "layout",
      hideable: false,
    },
    {
      id: "refresh",
      label: "Refresh",
      purpose: "Reload KPIs and queues from live data.",
      group: "primary",
      hideable: true,
    },
    {
      id: "new",
      label: "New ticket",
      purpose: "Create a ticket — keep this on the header (not only on a banner).",
      group: "primary",
      hideable: true,
    },
  ],
  defaultHiddenActionIds: ["tour"],
};

export const E_APPROVAL_DASHBOARD_PAGE_CHROME: PageChromeDefaults = {
  title: "E-Forms",
  description: "Your inbox for approvals, returns, and open requests.",
  actions: [
    {
      id: "help",
      label: "Tour guide",
      purpose: "Open help topics for E-Forms without starting the tour.",
      group: "guidance",
      hideable: true,
    },
    {
      id: "tour",
      label: "Start tour",
      purpose: "Interactive walkthrough for first-time approvers.",
      group: "guidance",
      hideable: true,
    },
    {
      id: "customize",
      label: "Customize",
      purpose: "Reorder widgets, set widths, and edit this header.",
      group: "layout",
      hideable: false,
    },
    {
      id: "refresh",
      label: "Refresh",
      purpose: "Reload inbox KPIs and approval queues.",
      group: "primary",
      hideable: true,
    },
    {
      id: "approvals",
      label: "Approvals",
      purpose: "Go straight to items waiting on you.",
      group: "primary",
      hideable: true,
    },
    {
      id: "new",
      label: "New submission",
      purpose: "Start a new form request from the header.",
      group: "primary",
      hideable: true,
    },
  ],
  defaultHiddenActionIds: ["tour"],
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
