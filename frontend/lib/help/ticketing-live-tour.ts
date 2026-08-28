import type {
  LiveTourAudience,
  LiveTourChapterId,
  LiveTourDefinition,
  LiveTourStep,
} from "@/lib/help/e-approval-live-tour";

export const TICKETING_LIVE_TOUR_ID = "ticketing";
export const TICKETING_TOUR_GUIDE_PATH = "/help/ticketing/tour";

export type TicketingTourCapabilities = {
  canCreate: boolean;
  canManage: boolean;
  canSettings: boolean;
};

export type TicketingTourChapterStart = {
  id: Exclude<
    LiveTourChapterId,
    "complete" | "approvals" | "signature" | "decide" | "follow_up"
  >;
  how: string;
  /** Who sees this chapter. Default `all`. Admin chapters use manager/settings. */
  audience?: LiveTourAudience;
};

/** Chapter titles on the Ticketing tour guide (shared labels stay E-Approval-oriented). */
export const TICKETING_TOUR_CHAPTER_LABELS: Record<
  TicketingTourChapterStart["id"],
  string
> = {
  overview: "Overview",
  track: "Track tickets",
  create: "Create a ticket",
  view: "View a ticket",
  manage: "Manage tickets",
  settings: "Ticketing settings",
};

/**
 * Jump-in chapters. Admin-only chapters (`ticket_manager`, `ticket_settings`) are hidden
 * from normal requesters via `ticketingTourChaptersForCapabilities`.
 */
export const TICKETING_TOUR_CHAPTER_STARTS: TicketingTourChapterStart[] = [
  {
    id: "overview",
    how: "Ticketing → Overview → status cards",
    audience: "all",
  },
  {
    id: "track",
    how: "Ticketing → Tickets → filters / queue",
    audience: "all",
  },
  {
    id: "create",
    how: "Ticketing → New ticket → title, category, attachments, submit",
    audience: "ticket_creator",
  },
  {
    id: "view",
    how: "Ticketing → Tickets → open ticket → header / activity",
    audience: "all",
  },
  {
    id: "manage",
    how: "Ticketing → Tickets → open ticket → triage panel",
    audience: "ticket_manager",
  },
  {
    id: "settings",
    how: "Settings → Ticketing settings → categories, SLA, notifications",
    audience: "ticket_settings",
  },
];

export function stepVisibleForTicketingCapabilities(
  step: LiveTourStep,
  capabilities: TicketingTourCapabilities,
): boolean {
  const audience = step.audience ?? "all";
  if (audience === "ticket_creator") {
    return capabilities.canCreate;
  }
  if (audience === "ticket_manager") {
    return capabilities.canManage;
  }
  if (audience === "ticket_settings") {
    return capabilities.canSettings;
  }
  return true;
}

export function ticketingTourChaptersForCapabilities(
  capabilities: TicketingTourCapabilities,
): TicketingTourChapterStart[] {
  return TICKETING_TOUR_CHAPTER_STARTS.filter((chapter) => {
    const audience = chapter.audience ?? "all";
    if (audience === "ticket_creator") {
      return capabilities.canCreate;
    }
    if (audience === "ticket_manager") {
      return capabilities.canManage;
    }
    if (audience === "ticket_settings") {
      return capabilities.canSettings;
    }
    return true;
  });
}

export function resolveTicketingTourSteps(
  capabilities: TicketingTourCapabilities,
): LiveTourStep[] {
  return ticketingLiveTour.steps
    .filter((step) => stepVisibleForTicketingCapabilities(step, capabilities))
    .map((step) => {
      if (step.id !== "tour-complete") {
        return step;
      }
      if (capabilities.canManage) {
        return {
          ...step,
          body: "You’re finished. You can raise tickets, work the queue, and update status from ticket detail anytime. Click Finish tour to close.",
        };
      }
      if (capabilities.canCreate) {
        return {
          ...step,
          body: "You’re finished. You can raise tickets and track them from Overview and Tickets. Manage steps were skipped for your role. Click Finish tour to close.",
        };
      }
      return {
        ...step,
        body: "You’re finished. You can browse Overview and the ticket queue for your role. Click Finish tour to close.",
      };
    });
}

export function findTicketingTourChapterStartIndex(
  chapterId: LiveTourChapterId,
  capabilities: TicketingTourCapabilities,
): number {
  const steps = resolveTicketingTourSteps(capabilities);
  const index = steps.findIndex((step) => step.chapter === chapterId);
  return index >= 0 ? index : 0;
}

/** Avoid importing liveTourStartHref here (circular with e-approval-live-tour). */
export function ticketingTourStartHref(
  stepIndex = 0,
  capabilities?: TicketingTourCapabilities,
  options?: { chapterId?: LiveTourChapterId },
): string {
  const steps = capabilities
    ? resolveTicketingTourSteps(capabilities)
    : ticketingLiveTour.steps;
  const step = steps[stepIndex] ?? steps[0];
  if (!step) {
    return "/ticketing";
  }
  const params = new URLSearchParams();
  params.set("tour", TICKETING_LIVE_TOUR_ID);
  params.set("tourStep", String(stepIndex));
  if (options?.chapterId && options.chapterId !== "complete") {
    params.set("tourChapter", options.chapterId);
  }
  if (step.query) {
    for (const [key, value] of Object.entries(step.query)) {
      params.set(key, value);
    }
  }
  const path =
    step.entryPath ?? (step.pathMatch === "prefix" ? step.path.replace(/\/$/, "") : step.path);
  return `${path}?${params.toString()}`;
}

export function ticketingTourChapterStartHref(
  chapterId: LiveTourChapterId,
  capabilities: TicketingTourCapabilities,
): string {
  const allowed = ticketingTourChaptersForCapabilities(capabilities).some(
    (chapter) => chapter.id === chapterId,
  );
  const safeChapterId = allowed ? chapterId : "overview";
  const index = findTicketingTourChapterStartIndex(safeChapterId, capabilities);
  return ticketingTourStartHref(index, capabilities, { chapterId: safeChapterId });
}

/** Live coach-mark tour for Ticketing (Overview → Tickets → New → Detail → Settings). */
export const ticketingLiveTour: LiveTourDefinition = {
  id: TICKETING_LIVE_TOUR_ID,
  title: "Ticketing tour",
  steps: [
    {
      id: "nav-ticketing",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing",
      chapter: "overview",
      title: "Open Ticketing",
      body: "In the sidebar, open Ticketing. This is where you raise issues, track the queue, and manage SLA.",
      missingHint: "On a phone, open the menu (☰) first. Expand Ticketing if it is collapsed, then continue.",
    },
    {
      id: "nav-ticketing-overview",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-overview",
      chapter: "overview",
      title: "Overview",
      body: "Click Overview for your Ticketing home — KPIs, charts, and recent tickets.",
      missingHint: "Expand Ticketing in the sidebar to see Overview.",
    },
    {
      id: "overview-kpis",
      path: "/ticketing",
      entryPath: "/ticketing",
      autoNavFrom: "tk-nav-ticketing-overview",
      target: "tk-overview-kpis",
      chapter: "overview",
      title: "Status cards",
      body: "Open, assigned to you, urgent, SLA at risk, and resolved this week. Counts stay at zero until tickets exist.",
    },
    {
      id: "overview-actions",
      path: "/ticketing",
      target: "tk-overview-quick-actions",
      chapter: "overview",
      title: "Quick actions",
      body: "Jump to the full ticket queue or report a new issue from these tiles.",
    },
    {
      id: "overview-recent",
      path: "/ticketing",
      target: "tk-overview-recent",
      chapter: "overview",
      title: "Recent tickets",
      body: "Latest tickets across the workspace. Open a row to see detail, comments, and status.",
    },
    {
      id: "track-nav-ticketing",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing",
      chapter: "track",
      title: "Open Ticketing",
      body: "In the sidebar, open Ticketing. The operational queue lives under Tickets.",
      missingHint: "On a phone, open the menu (☰) first. Expand Ticketing if it is collapsed, then continue.",
    },
    {
      id: "nav-ticketing-tickets",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-tickets",
      chapter: "track",
      title: "Open Tickets",
      body: "Under Ticketing in the sidebar, click Tickets to search and filter the operational queue.",
      missingHint: "Expand Ticketing in the sidebar to see Tickets.",
    },
    {
      id: "tickets-filters",
      path: "/ticketing/tickets",
      entryPath: "/ticketing/tickets",
      autoNavFrom: "tk-nav-ticketing-tickets",
      target: "tk-tickets-filters",
      chapter: "track",
      title: "Search and filters",
      body: "Find by title, ticket number, status, category, or limit to tickets you raised or that are assigned to you.",
    },
    {
      id: "tickets-table",
      path: "/ticketing/tickets",
      target: "tk-tickets-table",
      chapter: "track",
      title: "Ticket queue",
      body: "The list shows status, priority, and last update. Open any row to work the ticket.",
    },
    {
      id: "create-nav-ticketing",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing",
      chapter: "create",
      title: "Open Ticketing",
      body: "In the sidebar, open Ticketing. Creating a ticket starts from New ticket under this module.",
      audience: "ticket_creator",
      missingHint: "On a phone, open the menu (☰) first. Expand Ticketing if it is collapsed, then continue.",
    },
    {
      id: "create-nav-new",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-new",
      chapter: "create",
      title: "New ticket",
      body: "Under Ticketing, click New ticket to open the compose form.",
      audience: "ticket_creator",
      missingHint: "Expand Ticketing in the sidebar to see New ticket.",
    },
    {
      id: "compose-title",
      path: "/ticketing/tickets/new",
      entryPath: "/ticketing/tickets/new",
      autoNavFrom: "tk-nav-ticketing-new",
      target: "tk-compose-title",
      chapter: "create",
      title: "Title and description",
      body: "Summarize the issue, then add enough detail for the team to reproduce or triage it.",
      audience: "ticket_creator",
    },
    {
      id: "compose-category",
      path: "/ticketing/tickets/new",
      target: "tk-compose-category",
      chapter: "create",
      title: "Category",
      body: "Pick a category so auto-assign and SLA rules can route the ticket correctly. Priority is set during triage.",
      audience: "ticket_creator",
    },
    {
      id: "compose-attachments",
      path: "/ticketing/tickets/new",
      target: "tk-compose-attachments",
      chapter: "create",
      title: "Attachments",
      body: "Add screenshots or documents to speed up resolution. Files upload when you submit.",
      audience: "ticket_creator",
    },
    {
      id: "compose-submit",
      path: "/ticketing/tickets/new",
      target: "tk-compose-submit",
      chapter: "create",
      title: "Submit",
      body: "Create the ticket when ready. You will land on the detail page to track progress and comments.",
      audience: "ticket_creator",
    },
    {
      id: "view-nav-ticketing",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing",
      chapter: "view",
      title: "Open Ticketing",
      body: "In the sidebar, open Ticketing. Viewing a ticket starts from the Tickets queue.",
      missingHint: "On a phone, open the menu (☰) first. Expand Ticketing if it is collapsed, then continue.",
    },
    {
      id: "view-nav-tickets",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-tickets",
      chapter: "view",
      title: "Open Tickets",
      body: "Under Ticketing, click Tickets to open the queue, then open a ticket from the list.",
      missingHint: "Expand Ticketing in the sidebar to see Tickets.",
    },
    {
      id: "view-open-ticket",
      path: "/ticketing/tickets",
      entryPath: "/ticketing/tickets",
      autoNavFrom: "tk-nav-ticketing-tickets",
      target: "tk-tickets-open",
      chapter: "view",
      title: "Open a ticket",
      body: "Click the sample ticket number (or any real ticket) to open detail. Next continues on the sample.",
      missingHint: "Sample ticket row appears while the tour is active.",
    },
    {
      id: "detail-header",
      path: "/ticketing/tickets/",
      pathMatch: "prefix",
      entryPath: "/ticketing/tickets/tour-sample",
      autoNavFrom: "tk-tickets-open",
      target: "tk-detail-header",
      chapter: "view",
      title: "Ticket header",
      body: "Ticket number, status, priority, and SLA risk appear here. A sample ticket is shown during the tour and is never saved.",
      missingHint: "Open the sample ticket from Tickets (or any real ticket), then continue the tour.",
      skipIfMissing: true,
    },
    {
      id: "detail-description",
      path: "/ticketing/tickets/",
      pathMatch: "prefix",
      entryPath: "/ticketing/tickets/tour-sample",
      target: "tk-detail-description",
      chapter: "view",
      title: "Description and links",
      body: "Full issue text and any linked records from other modules show in this section.",
      skipIfMissing: true,
    },
    {
      id: "detail-activity",
      path: "/ticketing/tickets/",
      pathMatch: "prefix",
      entryPath: "/ticketing/tickets/tour-sample",
      target: "tk-detail-activity",
      chapter: "view",
      title: "Comments and activity",
      body: "Requestors and IT leave updates here. Managers can post internal notes visible only to the support team.",
      skipIfMissing: true,
    },
    {
      id: "manage-nav-ticketing",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing",
      chapter: "manage",
      title: "Open Ticketing",
      body: "In the sidebar, open Ticketing. Managers triage tickets from detail after opening the queue.",
      audience: "ticket_manager",
      missingHint: "On a phone, open the menu (☰) first. Expand Ticketing if it is collapsed, then continue.",
    },
    {
      id: "manage-nav-tickets",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-tickets",
      chapter: "manage",
      title: "Open Tickets",
      body: "Under Ticketing, click Tickets, then open a ticket to reach triage controls.",
      audience: "ticket_manager",
      missingHint: "Expand Ticketing in the sidebar to see Tickets.",
    },
    {
      id: "manage-open-ticket",
      path: "/ticketing/tickets",
      entryPath: "/ticketing/tickets",
      autoNavFrom: "tk-nav-ticketing-tickets",
      target: "tk-tickets-open",
      chapter: "manage",
      title: "Open a ticket to triage",
      body: "Open the sample ticket (or any ticket you can manage). Next highlights the Manage panel.",
      audience: "ticket_manager",
      missingHint: "Sample ticket row appears while the tour is active.",
    },
    {
      id: "detail-manage",
      path: "/ticketing/tickets/",
      pathMatch: "prefix",
      entryPath: "/ticketing/tickets/tour-sample",
      autoNavFrom: "tk-tickets-open",
      target: "tk-detail-manage",
      chapter: "manage",
      title: "Triage and resolve",
      body: "Managers update status, priority, category, assignee, and resolution notes from this panel.",
      audience: "ticket_manager",
      missingHint: "Open the sample ticket while signed in with ticket manage permission to see triage controls.",
      skipIfMissing: true,
    },
    {
      id: "settings-nav-settings",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "ea-nav-settings",
      chapter: "settings",
      title: "Open Settings",
      body: "In the sidebar under Administration, expand Settings. Ticketing settings live there with other module settings.",
      audience: "ticket_settings",
      missingHint: "On a phone, open the menu (☰) first. Expand Settings if it is collapsed, then continue.",
    },
    {
      id: "nav-ticketing-settings",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-nav-ticketing-settings",
      chapter: "settings",
      title: "Ticketing settings",
      body: "Under Settings, open Ticketing settings for categories, SLA, assignment rules, and notifications.",
      audience: "ticket_settings",
      missingHint: "Expand Settings and scroll to the Ticketing section. You need Ticketing settings permission to see this item.",
      skipIfMissing: true,
    },
    {
      id: "settings-categories",
      path: "/ticketing/settings",
      entryPath: "/ticketing/settings",
      autoNavFrom: "tk-nav-ticketing-settings",
      target: "tk-settings-categories",
      chapter: "settings",
      title: "Categories",
      body: "Define ticket categories and optional per-category SLA overrides used across the workspace.",
      audience: "ticket_settings",
    },
    {
      id: "settings-sla",
      path: "/ticketing/settings",
      target: "tk-settings-sla",
      chapter: "settings",
      title: "SLA and escalation",
      body: "Tenant defaults for response reminders and escalations. The scheduler runs ticketing:sla-run every five minutes.",
      audience: "ticket_settings",
    },
    {
      id: "settings-notify",
      path: "/ticketing/settings",
      target: "tk-settings-notify",
      chapter: "settings",
      title: "Notifications",
      body: "IT mailbox, email toggles, and optional Teams webhook for create and SLA events.",
      audience: "ticket_settings",
    },
    {
      id: "tour-complete",
      path: "/ticketing",
      entryPath: "/ticketing",
      target: "tk-tour-complete",
      chapter: "complete",
      title: "Tour complete",
      body: "You’re finished with the Ticketing tour. Click Finish tour to close.",
    },
  ],
};

export function isTicketingTourActive(searchParams: { get: (key: string) => string | null }): boolean {
  return searchParams.get("tour") === TICKETING_LIVE_TOUR_ID;
}

export function toLiveTourCapabilities(
  capabilities: TicketingTourCapabilities,
): {
  canApprove: boolean;
  canCreate: boolean;
  canCreateTickets: boolean;
  canManageTickets: boolean;
  canManageTicketingSettings: boolean;
} {
  return {
    canApprove: false,
    canCreate: false,
    canCreateTickets: capabilities.canCreate,
    canManageTickets: capabilities.canManage,
    canManageTicketingSettings: capabilities.canSettings,
  };
}
