import type {
  DashboardAttentionItem,
  DashboardExportsTeaser,
  DashboardNormalizedData,
  DashboardShortcutItem,
} from "@/lib/ui/dashboard-widget-data";
import { withPageEnhancements } from "@/lib/ui/dashboard-widget-data";

export const MY_EXPORTS_HREF = "/exports";
export const E_APPROVAL_REPORTS_HREF = "/e-approval/reports";

export function exportsTeaser(
  href: string = MY_EXPORTS_HREF,
  message?: string,
): DashboardExportsTeaser {
  return {
    href,
    label: "My exports",
    message: message ?? "Open download history and async export jobs",
  };
}

/** DocExtract batches — attention from status counts. */
export function docExtractBatchesEnhancements(input: {
  processing: number;
  failed: number;
  canRun?: boolean;
}): Partial<Pick<DashboardNormalizedData, "attention" | "exportsTeaser" | "shortcuts">> {
  const attention: DashboardAttentionItem[] = [];
  if (input.processing > 0) {
    attention.push({
      id: "dx-processing",
      title: `${input.processing} extraction${input.processing === 1 ? "" : "s"} scanning`,
      subtitle: "Auto-refreshes while processing",
      href: "/doc-extract?status=processing",
      tone: "neutral",
    });
  }
  if (input.failed > 0) {
    attention.push({
      id: "dx-failed",
      title: `${input.failed} extraction${input.failed === 1 ? "" : "s"} failed`,
      subtitle: "Review and retry from the batch",
      href: "/doc-extract?status=failed",
      tone: "danger",
    });
  }
  const shortcuts: DashboardShortcutItem[] = [
    ...(input.canRun !== false
      ? [{ href: "/doc-extract/new", label: "New extraction", description: "Upload PDFs or images" }]
      : []),
    { href: "/doc-extract/templates", label: "Templates", description: "Column definitions" },
    { href: MY_EXPORTS_HREF, label: "My exports", description: "Download history" },
  ];
  return {
    attention,
    shortcuts,
    exportsTeaser: exportsTeaser(MY_EXPORTS_HREF, "Batch and list exports appear under My exports"),
  };
}

export function docExtractTemplatesEnhancements(): Partial<
  Pick<DashboardNormalizedData, "shortcuts" | "exportsTeaser">
> {
  return {
    shortcuts: [
      { href: "/doc-extract", label: "Batches", description: "Back to extractions" },
      { href: "/doc-extract/new", label: "New extraction", description: "Run with a published template" },
    ],
    exportsTeaser: exportsTeaser(),
  };
}

export function ticketingListEnhancements(input?: {
  openCount?: number;
  slaAtRisk?: number;
}): Partial<Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser">> {
  const attention: DashboardAttentionItem[] = [];
  if ((input?.slaAtRisk ?? 0) > 0) {
    attention.push({
      id: "tk-sla",
      title: `${input!.slaAtRisk} ticket${input!.slaAtRisk === 1 ? "" : "s"} at SLA risk`,
      subtitle: "Filter the queue to at-risk items",
      href: "/ticketing/tickets?sla_status=at_risk",
      tone: "warning",
    });
  }
  if ((input?.openCount ?? 0) > 0) {
    attention.push({
      id: "tk-open",
      title: `${input!.openCount} open in current view`,
      subtitle: "Matches active filters",
      tone: "neutral",
    });
  }
  return {
    shortcuts: [
      { href: "/ticketing", label: "Overview", description: "KPIs and queue charts" },
      { href: "/ticketing/tickets/new", label: "New ticket", description: "Report an issue" },
      { href: MY_EXPORTS_HREF, label: "My exports", description: "Ticket list downloads" },
    ],
    attention,
    exportsTeaser: exportsTeaser(),
  };
}

export function ticketingDashboardEnhancements(input?: {
  slaAtRisk?: number;
  urgent?: number;
}): Partial<Pick<DashboardNormalizedData, "attention" | "exportsTeaser" | "shortcuts">> {
  const attention: DashboardAttentionItem[] = [];
  if ((input?.slaAtRisk ?? 0) > 0) {
    attention.push({
      id: "tk-dash-sla",
      title: `${input!.slaAtRisk} at SLA risk`,
      href: "/ticketing/tickets?sla_status=at_risk",
      tone: "warning",
    });
  }
  if ((input?.urgent ?? 0) > 0) {
    attention.push({
      id: "tk-dash-urgent",
      title: `${input!.urgent} urgent open`,
      href: "/ticketing/tickets?priority=urgent",
      tone: "danger",
    });
  }
  return {
    attention,
    exportsTeaser: exportsTeaser(),
    shortcuts: [
      { href: "/ticketing/tickets", label: "All tickets", description: "Open the full queue" },
      { href: "/ticketing/tickets/new", label: "New ticket", description: "Report an issue" },
      { href: MY_EXPORTS_HREF, label: "My exports", description: "Download history" },
    ],
  };
}

export function eApprovalDashboardEnhancements(input?: {
  awaiting?: number;
  attention?: number;
}): Partial<Pick<DashboardNormalizedData, "attention" | "exportsTeaser" | "shortcuts">> {
  const attention: DashboardAttentionItem[] = [];
  if ((input?.awaiting ?? 0) > 0) {
    attention.push({
      id: "ea-awaiting",
      title: `${input!.awaiting} awaiting your approval`,
      href: "/e-approval/approvals?awaiting_me=1",
      tone: "warning",
    });
  }
  if ((input?.attention ?? 0) > 0) {
    attention.push({
      id: "ea-returned",
      title: `${input!.attention} need attention`,
      href: "/e-approval/submissions?status=returned",
      tone: "danger",
    });
  }
  return {
    attention,
    exportsTeaser: exportsTeaser(MY_EXPORTS_HREF, "List exports and E-Forms downloads"),
    shortcuts: [
      { href: "/e-approval/submissions", label: "Submissions", description: "Track requests" },
      { href: "/e-approval/approvals?awaiting_me=1", label: "Approvals", description: "Decide pending items" },
      { href: E_APPROVAL_REPORTS_HREF, label: "Reports", description: "Analytics and exports" },
      { href: "/e-approval/forms", label: "Forms", description: "Templates and workflows" },
    ],
  };
}

export function eApprovalSubmissionsEnhancements(input?: {
  returnedCount?: number;
}): Partial<Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser">> {
  const attention: DashboardAttentionItem[] = [];
  if ((input?.returnedCount ?? 0) > 0) {
    attention.push({
      id: "ea-sub-returned",
      title: `${input!.returnedCount} need revision`,
      subtitle: "Returned submissions in this view",
      href: "/e-approval/submissions?status=returned",
      tone: "warning",
    });
  }
  return {
    shortcuts: [
      { href: "/e-approval", label: "Overview", description: "Inbox and queues" },
      { href: "/e-approval/submissions/new", label: "New submission", description: "Start a request" },
      { href: E_APPROVAL_REPORTS_HREF, label: "Reports", description: "Exports and analytics" },
    ],
    attention,
    exportsTeaser: exportsTeaser(),
  };
}

export function eApprovalApprovalsEnhancements(input?: {
  awaitingMe?: boolean;
  count?: number;
}): Partial<Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser">> {
  const attention: DashboardAttentionItem[] = [];
  if (input?.awaitingMe && (input.count ?? 0) > 0) {
    attention.push({
      id: "ea-appr-queue",
      title: `${input.count} awaiting you`,
      subtitle: "Open a row to approve or reject",
      tone: "warning",
    });
  }
  return {
    shortcuts: [
      { href: "/e-approval", label: "Overview", description: "Back to inbox" },
      { href: "/e-approval/submissions", label: "Submissions", description: "All requests" },
      { href: E_APPROVAL_REPORTS_HREF, label: "Reports", description: "Analytics" },
    ],
    attention,
    exportsTeaser: exportsTeaser(),
  };
}

export function eApprovalFormsEnhancements(): Partial<
  Pick<DashboardNormalizedData, "shortcuts" | "exportsTeaser">
> {
  return {
    shortcuts: [
      { href: "/e-approval/forms/create", label: "New form", description: "Open the form designer" },
      { href: "/e-approval/forms/templates", label: "Templates", description: "Starter packs" },
      { href: E_APPROVAL_REPORTS_HREF, label: "Reports", description: "Volume trends" },
    ],
    exportsTeaser: exportsTeaser(E_APPROVAL_REPORTS_HREF, "Form exports live under Reports"),
  };
}

export function eApprovalReportsEnhancements(): Partial<
  Pick<DashboardNormalizedData, "shortcuts" | "exportsTeaser">
> {
  return {
    shortcuts: [
      { href: "/e-approval", label: "Overview", description: "Inbox" },
      { href: "/e-approval/submissions", label: "Submissions", description: "Operational list" },
      { href: MY_EXPORTS_HREF, label: "My exports", description: "Download history" },
    ],
    exportsTeaser: exportsTeaser(MY_EXPORTS_HREF),
  };
}

export function eApprovalWorkspaceEnhancements(input: {
  slug: string;
  newRequestHref?: string | null;
  canSubmit?: boolean;
}): Partial<Pick<DashboardNormalizedData, "shortcuts" | "exportsTeaser">> {
  const shortcuts: DashboardShortcutItem[] = [
    { href: "/e-approval", label: "Overview", description: "All forms inbox" },
    { href: E_APPROVAL_REPORTS_HREF, label: "Reports", description: "Analytics and exports" },
  ];
  if (input.canSubmit && input.newRequestHref) {
    shortcuts.unshift({
      href: input.newRequestHref,
      label: "New request",
      description: "Submit on this form",
    });
  }
  return {
    shortcuts,
    exportsTeaser: exportsTeaser(MY_EXPORTS_HREF, "Workspace exports appear under My exports"),
  };
}

export function workspaceDashboardEnhancements(input?: {
  awaitingTotal?: number;
  quickLinks?: Array<{ href: string; label: string }>;
}): Partial<Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser">> {
  const attention: DashboardAttentionItem[] = [];
  if ((input?.awaitingTotal ?? 0) > 0) {
    attention.push({
      id: "ws-awaiting",
      title: `${input!.awaitingTotal} item${input!.awaitingTotal === 1 ? "" : "s"} awaiting you`,
      subtitle: "Cross-module queue on this dashboard",
      tone: "warning",
    });
  }
  const shortcuts: DashboardShortcutItem[] = [
    ...(input?.quickLinks ?? []).map((link) => ({
      href: link.href,
      label: link.label,
    })),
    { href: "/ticketing/tickets", label: "Tickets", description: "Issue queue" },
    { href: "/e-approval", label: "E-Forms", description: "Approvals inbox" },
    { href: MY_EXPORTS_HREF, label: "My exports", description: "Download history" },
  ];
  return {
    attention,
    shortcuts,
    exportsTeaser: exportsTeaser(MY_EXPORTS_HREF, "List and report downloads"),
  };
}

export function applyPageEnhancements(
  data: DashboardNormalizedData,
  patch: Partial<
    Pick<DashboardNormalizedData, "shortcuts" | "attention" | "exportsTeaser" | "activity" | "kpis" | "hero">
  >,
): DashboardNormalizedData {
  return withPageEnhancements(data, {
    ...patch,
    // Prefer page patch when provided; keep existing normalize bags otherwise
    shortcuts: patch.shortcuts ?? data.shortcuts,
    attention: patch.attention ?? data.attention,
    exportsTeaser: patch.exportsTeaser ?? data.exportsTeaser,
    activity: patch.activity ?? data.activity,
    kpis: patch.kpis ?? data.kpis,
    hero: patch.hero ?? data.hero,
  });
}
