export type LiveTourAudience = "all" | "approver" | "requestor";

export type LiveTourChapterId =
  | "overview"
  | "track"
  | "create"
  | "view"
  | "approvals"
  | "signature"
  | "decide"
  | "complete";

export const LIVE_TOUR_CHAPTER_LABELS: Record<LiveTourChapterId, string> = {
  overview: "Overview",
  track: "Track requests",
  create: "Create a request",
  view: "View a request",
  approvals: "Approvals inbox",
  signature: "Add your signature",
  decide: "Decide on a request",
  complete: "Finish",
};

/** Chapters users can jump into from the Visual guide (excludes Finish). */
export type EApprovalTourChapterStart = {
  id: Exclude<LiveTourChapterId, "complete">;
  how: string;
  /** Who sees this chapter start. Default `all`. */
  audience?: LiveTourAudience;
};

export const E_APPROVAL_TOUR_CHAPTER_STARTS: EApprovalTourChapterStart[] = [
  {
    id: "overview",
    how: "E-Approval → Overview → status cards",
    audience: "all",
  },
  {
    id: "track",
    how: "E-Approval → Submissions → filters / gallery / table",
    audience: "requestor",
  },
  {
    id: "create",
    how: "New submission → form picker → compose",
    audience: "requestor",
  },
  {
    id: "view",
    how: "Open submission → detail / print",
    audience: "all",
  },
  {
    id: "approvals",
    how: "E-Approval → Approvals → queue",
    audience: "approver",
  },
  {
    id: "signature",
    how: "Settings → My E-Approval profile → save signature",
    audience: "approver",
  },
  {
    id: "decide",
    how: "E-Approval → Approvals → Decide tab",
    audience: "approver",
  },
];

export type LiveTourStep = {
  id: string;
  /**
   * Path for this step. With `pathMatch: "prefix"`, any pathname under this prefix matches
   * (e.g. `/e-approval/request/` → `/e-approval/request/{formId}`).
   */
  path: string;
  pathMatch?: "exact" | "prefix";
  /** When the live path is unknown, land here and prompt the user (or auto-nav). */
  entryPath?: string;
  /** Extra query params (e.g. `{ tab: "approvals" }` on submission detail). */
  query?: Record<string, string>;
  /**
   * When advancing onto this step, if not already on a matching path, navigate using
   * `[data-help="{autoNavFrom}"][data-tour-nav]` if present.
   */
  autoNavFrom?: string;
  /** Matches `[data-help="…"]` on the live page. */
  target: string;
  title: string;
  body: string;
  /** Job chapter for progress labels (e.g. Create a request · 3 of 8). */
  chapter?: LiveTourChapterId;
  /** Shown when the target is missing (permissions / need to open a record first). */
  missingHint?: string;
  /**
   * When the target is still missing after the page settles (empty queue, no forms,
   * no submission to open), advance past this step automatically.
   */
  skipIfMissing?: boolean;
  /**
   * Skip when Decide already loaded a profile signature
   * (`[data-help="ea-decide-signature"][data-has-profile-signature="true"]`).
   */
  skipIfProfileSignature?: boolean;
  /**
   * Who should see this step. Default `all`.
   * - `approver`: requires e_approval:approve
   * - `requestor`: requires e_approval:submissions:create
   */
  audience?: LiveTourAudience;
  /**
   * When set, Submissions list switches to this layout while the step is active
   * (Gallery vs Table demo).
   */
  listViewMode?: "gallery" | "table";
};

export type LiveTourChapterProgress = {
  chapterId: LiveTourChapterId;
  chapterLabel: string;
  /** 1-based index within the current chapter (role-filtered steps). */
  chapterStep: number;
  chapterTotal: number;
  /** 1-based overall index across the whole tour. */
  overallStep: number;
  overallTotal: number;
};

export type LiveTourDefinition = {
  id: string;
  title: string;
  steps: LiveTourStep[];
};

export const LIVE_TOUR_QUERY = "tour";
export const LIVE_TOUR_STEP_QUERY = "tourStep";
/** When set, the tour stays in this chapter then returns to the Visual guide. */
export const LIVE_TOUR_CHAPTER_QUERY = "tourChapter";

export const E_APPROVAL_VISUAL_GUIDE_PATH = "/help/e-approval/visual";

export function isLiveTourChapterId(value: string | null | undefined): value is LiveTourChapterId {
  if (!value) {
    return false;
  }
  return value in LIVE_TOUR_CHAPTER_LABELS;
}

export type EApprovalTourCapabilities = {
  canApprove: boolean;
  canCreate: boolean;
};

export function stepVisibleForCapabilities(
  step: LiveTourStep,
  capabilities: EApprovalTourCapabilities,
): boolean {
  const audience = step.audience ?? "all";
  if (audience === "approver") {
    return capabilities.canApprove;
  }
  if (audience === "requestor") {
    return capabilities.canCreate;
  }
  return true;
}

/** Map step id → job chapter for coach-mark progress labels. */
export function chapterForEApprovalStepId(stepId: string): LiveTourChapterId {
  if (
    stepId === "nav-e-approval" ||
    stepId === "nav-e-approval-overview" ||
    stepId.startsWith("overview-")
  ) {
    return "overview";
  }
  if (
    stepId === "nav-e-approval-submissions" ||
    stepId === "submissions-filters" ||
    stepId === "submissions-search" ||
    stepId === "submissions-view-gallery" ||
    stepId === "submissions-view-table" ||
    stepId === "submissions-status"
  ) {
    return "track";
  }
  if (
    stepId === "submissions-new" ||
    stepId.startsWith("picker-") ||
    stepId.startsWith("compose-")
  ) {
    return "create";
  }
  if (stepId.startsWith("after-submit-") || stepId.startsWith("detail-")) {
    return "view";
  }
  if (stepId === "nav-e-approval-approvals" || stepId.startsWith("approvals-")) {
    return "approvals";
  }
  if (
    stepId === "nav-settings" ||
    stepId === "nav-e-approval-profile" ||
    stepId.startsWith("profile-signature")
  ) {
    return "signature";
  }
  if (stepId === "nav-decide-approvals" || stepId.startsWith("decide-")) {
    return "decide";
  }
  if (stepId === "tour-complete") {
    return "complete";
  }
  return "overview";
}

export function withEApprovalTourChapters(steps: LiveTourStep[]): LiveTourStep[] {
  return steps.map((step) => ({
    ...step,
    chapter: step.chapter ?? chapterForEApprovalStepId(step.id),
  }));
}

export function getLiveTourChapterProgress(
  steps: LiveTourStep[],
  stepIndex: number,
): LiveTourChapterProgress | null {
  const step = steps[stepIndex];
  if (!step) {
    return null;
  }
  const chapterId = step.chapter ?? chapterForEApprovalStepId(step.id);
  const chapterSteps = steps.filter(
    (entry) => (entry.chapter ?? chapterForEApprovalStepId(entry.id)) === chapterId,
  );
  const chapterStep = chapterSteps.findIndex((entry) => entry.id === step.id) + 1;
  return {
    chapterId,
    chapterLabel: LIVE_TOUR_CHAPTER_LABELS[chapterId],
    chapterStep: Math.max(1, chapterStep),
    chapterTotal: Math.max(1, chapterSteps.length),
    overallStep: stepIndex + 1,
    overallTotal: steps.length,
  };
}

export function pathMatchesTourStep(pathname: string, step: LiveTourStep): boolean {
  if (step.pathMatch === "prefix") {
    const base = step.path.replace(/\/$/, "");
    if (pathname === base) {
      return false;
    }
    if (!pathname.startsWith(`${base}/`)) {
      return false;
    }
    const rest = pathname.slice(base.length + 1);
    if (
      !rest ||
      rest === "new" ||
      rest.startsWith("new/") ||
      rest === "print" ||
      rest.endsWith("/print") ||
      rest.includes("/print/")
    ) {
      return false;
    }
    return true;
  }
  return pathname === step.path;
}

export function buildTourSearchParams(
  tourId: string,
  stepIndex: number,
  step: LiveTourStep,
  existing?: URLSearchParams,
): URLSearchParams {
  const params = new URLSearchParams(existing?.toString() ?? "");
  params.set(LIVE_TOUR_QUERY, tourId);
  params.set(LIVE_TOUR_STEP_QUERY, String(stepIndex));
  if (step.query) {
    for (const [key, value] of Object.entries(step.query)) {
      params.set(key, value);
    }
  }
  return params;
}

export function liveTourStartHref(
  tourId: string,
  stepIndex = 0,
  capabilities?: EApprovalTourCapabilities,
  options?: { chapterId?: LiveTourChapterId },
): string {
  const tour = capabilities
    ? resolveLiveTour(tourId, capabilities)
    : tourById(tourId);
  const step = tour?.steps[stepIndex] ?? tour?.steps[0];
  if (!step || !tour) {
    return E_APPROVAL_VISUAL_GUIDE_PATH;
  }
  const params = buildTourSearchParams(tourId, stepIndex, step);
  if (options?.chapterId && options.chapterId !== "complete") {
    params.set(LIVE_TOUR_CHAPTER_QUERY, options.chapterId);
  }
  const path = step.entryPath ?? (step.pathMatch === "prefix" ? step.path.replace(/\/$/, "") : step.path);
  return `${path}?${params.toString()}`;
}

export function findEApprovalTourChapterStartIndex(
  chapterId: LiveTourChapterId,
  capabilities: EApprovalTourCapabilities,
): number {
  const steps = resolveEApprovalTourSteps(capabilities);
  const index = steps.findIndex(
    (step) => (step.chapter ?? chapterForEApprovalStepId(step.id)) === chapterId,
  );
  return index >= 0 ? index : 0;
}

export function findEApprovalTourChapterEndIndex(
  chapterId: LiveTourChapterId,
  capabilities: EApprovalTourCapabilities,
): number {
  const steps = resolveEApprovalTourSteps(capabilities);
  let last = -1;
  for (let index = 0; index < steps.length; index += 1) {
    const step = steps[index];
    if (!step) {
      continue;
    }
    if ((step.chapter ?? chapterForEApprovalStepId(step.id)) === chapterId) {
      last = index;
    }
  }
  return last >= 0 ? last : findEApprovalTourChapterStartIndex(chapterId, capabilities);
}

export function liveTourChapterStartHref(
  chapterId: LiveTourChapterId,
  capabilities: EApprovalTourCapabilities,
): string {
  const index = findEApprovalTourChapterStartIndex(chapterId, capabilities);
  return liveTourStartHref("e-approval", index, capabilities, { chapterId });
}

export function eApprovalTourChaptersForCapabilities(
  capabilities: EApprovalTourCapabilities,
): EApprovalTourChapterStart[] {
  return E_APPROVAL_TOUR_CHAPTER_STARTS.filter((chapter) => {
    const audience = chapter.audience ?? "all";
    if (audience === "approver") {
      return capabilities.canApprove;
    }
    if (audience === "requestor") {
      return capabilities.canCreate;
    }
    return true;
  });
}

export function tourById(id: string): LiveTourDefinition | null {
  if (id === eApprovalLiveTour.id) {
    return eApprovalLiveTour;
  }
  return null;
}

/** Live coach-mark tour for E-Approval (Overview → Submissions → Compose → Detail → Approvals). */
export const eApprovalLiveTour: LiveTourDefinition = {
  id: "e-approval",
  title: "E-Approval tour",
  steps: [
    {
      id: "nav-e-approval",
      path: "/e-approval",
      entryPath: "/e-approval",
      target: "ea-nav-e-approval",
      title: "Open E-Approval",
      body: "In the sidebar menu, open E-Approval. That module is where you track requests, create submissions, and decide.",
      missingHint: "On a phone, open the menu (☰) first. Expand E-Approval if it is collapsed, then continue.",
    },
    {
      id: "nav-e-approval-overview",
      path: "/e-approval",
      entryPath: "/e-approval",
      target: "ea-nav-e-approval-overview",
      title: "Overview",
      body: "Click Overview for your E-Approval home — status cards and work waiting on you.",
      missingHint: "Expand E-Approval in the sidebar to see Overview.",
    },
    {
      id: "overview-kpis",
      path: "/e-approval",
      entryPath: "/e-approval",
      autoNavFrom: "ea-nav-e-approval-overview",
      target: "ea-overview-kpis",
      title: "Status cards",
      body: "Counts for approvals waiting on you, returns, open submissions, and SLA risk. Empty until you have work in the system.",
    },
    {
      id: "overview-awaiting",
      path: "/e-approval",
      target: "ea-overview-awaiting",
      title: "Needs my approval",
      body: "Oldest items assigned to you. Open one to approve, reject, or ask for changes. Stays empty when nothing awaits you.",
      audience: "approver",
    },
    {
      id: "overview-attention",
      path: "/e-approval",
      target: "ea-overview-attention",
      title: "Needs my attention",
      body: "Your drafts and returned requests that still need work. Empty until you own a draft or return.",
    },
    {
      id: "overview-actions",
      path: "/e-approval",
      target: "ea-overview-quick-actions",
      title: "Quick actions",
      body: "Jump to Approvals or start a new submission from here.",
    },
    {
      id: "nav-e-approval-submissions",
      path: "/e-approval",
      entryPath: "/e-approval",
      target: "ea-nav-e-approval-submissions",
      title: "Open Submissions",
      body: "Under E-Approval in the sidebar, click Submissions to track requests in workflow.",
      missingHint: "Expand E-Approval in the sidebar to see Submissions.",
    },
    {
      id: "submissions-filters",
      path: "/e-approval/submissions",
      entryPath: "/e-approval/submissions",
      autoNavFrom: "ea-nav-e-approval-submissions",
      target: "ea-submissions-filters",
      title: "Status filters",
      body: "Narrow the list to Needs revision, Pending, Approved, and more.",
    },
    {
      id: "submissions-search",
      path: "/e-approval/submissions",
      target: "ea-submissions-search",
      title: "Search submissions",
      body: "Find by document number, form name, or requestor.",
    },
    {
      id: "submissions-view-gallery",
      path: "/e-approval/submissions",
      target: "ea-submissions-gallery",
      title: "Gallery view",
      body: "Card layout for scanning document number, status, and requestor at a glance. Use the toggle to switch layouts.",
      listViewMode: "gallery",
      missingHint: "Gallery cards appear when the list has rows (or sample cards while the tour is active).",
    },
    {
      id: "submissions-view-table",
      path: "/e-approval/submissions",
      target: "ea-submissions-table",
      title: "Table view",
      body: "Dense rows for sorting and scanning many requests. Switch back to Gallery anytime with the same toggle.",
      listViewMode: "table",
      missingHint: "Table rows appear when the list has data. Continue if the list is empty.",
    },
    {
      id: "submissions-status",
      path: "/e-approval/submissions",
      target: "ea-submissions-status",
      title: "Status badge",
      body: "Draft, Approved, Rejected, and other states are color-coded on each card.",
      listViewMode: "gallery",
      missingHint: "No submissions yet — badges appear on cards after you create a request. Continue to the next step.",
    },
    {
      id: "submissions-new",
      path: "/e-approval/submissions",
      target: "ea-submissions-new",
      title: "New submission",
      body: "Still on Submissions — click New submission to start a Document Approval request. Next opens the form picker.",
      audience: "requestor",
    },
    {
      id: "picker-search",
      path: "/e-approval/submissions/new",
      entryPath: "/e-approval/submissions/new",
      autoNavFrom: "ea-submissions-new",
      target: "ea-picker-search",
      title: "Search forms",
      body: "On the form picker, filter published forms by name, category, or description.",
      audience: "requestor",
    },
    {
      id: "picker-card",
      path: "/e-approval/submissions/new",
      target: "ea-picker-form-card",
      title: "Document Approval form",
      body: "The tour uses Document Approval (Title, Approver 1–3, Attachments) — not Cash Advance or Liquidation.",
      missingHint: "No Document Approval form published yet — a sample card is shown for this tour.",
      audience: "requestor",
    },
    {
      id: "picker-start",
      path: "/e-approval/submissions/new",
      target: "ea-picker-start",
      title: "Start request",
      body: "Opens Document Approval compose for this tour (Title, Approver 1–3, Attachments).",
      missingHint: "Start is unavailable until a form is published. Compose steps use the Document Approval sample.",
      audience: "requestor",
    },
    {
      id: "compose-fields",
      path: "/e-approval/request/",
      pathMatch: "prefix",
      entryPath: "/e-approval/request/tour-sample",
      autoNavFrom: "ea-picker-start",
      target: "ea-compose-fields",
      title: "Document Approval fields",
      body: "Fill Title and Approver 1–3 — the same fields as the live Document Approval form.",
      missingHint: "No form to compose — continue. Compose steps use the Document Approval sample when available.",
      audience: "requestor",
    },
    {
      id: "compose-upload",
      path: "/e-approval/request/",
      pathMatch: "prefix",
      entryPath: "/e-approval/request/tour-sample",
      autoNavFrom: "ea-picker-start",
      target: "ea-compose-upload",
      title: "Attachments",
      body: "Attach PDF documents for Document Approval review.",
      missingHint: "Skipped when no compose form is open, or this form has no file field.",
      audience: "requestor",
    },
    {
      id: "compose-save",
      path: "/e-approval/request/",
      pathMatch: "prefix",
      entryPath: "/e-approval/request/tour-sample",
      autoNavFrom: "ea-picker-start",
      target: "ea-compose-save-draft",
      title: "Save draft",
      body: "Keep work in progress without starting the approval path.",
      missingHint: "Skipped when no compose form is open.",
      audience: "requestor",
    },
    {
      id: "compose-submit",
      path: "/e-approval/request/",
      pathMatch: "prefix",
      entryPath: "/e-approval/request/tour-sample",
      autoNavFrom: "ea-picker-start",
      target: "ea-compose-submit",
      title: "Submit request",
      body: "Sends the Document Approval into workflow. Next you’ll return to Submissions to open the new card.",
      missingHint: "Skipped when no compose form is open.",
      audience: "requestor",
    },
    {
      id: "after-submit-gallery",
      path: "/e-approval/submissions",
      entryPath: "/e-approval/submissions",
      target: "ea-submissions-gallery",
      title: "Your submissions list",
      body: "After submit you land back on Submissions. Find the Document Approval by document number, title, and status.",
      listViewMode: "gallery",
      missingHint: "Sample card appears while the tour is active even when the real list is empty.",
    },
    {
      id: "after-submit-open",
      path: "/e-approval/submissions",
      target: "ea-submissions-card-actions",
      title: "Open the request",
      body: "Click Open submission (or View in table) to open the request detail. Next continues the tour on the sample.",
      listViewMode: "gallery",
      missingHint: "Sample Open link appears while the tour is active.",
    },
    {
      id: "detail-header",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      autoNavFrom: "ea-submissions-card-actions",
      target: "ea-detail-header",
      title: "Document number & status",
      body: "Identifier plus a status badge (Pending, Approved, Rejected, Draft, and more).",
      missingHint: "No submission to open yet — detail steps are skipped until the list has at least one request.",
    },
    {
      id: "detail-summary",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      target: "ea-detail-summary",
      title: "Summary strip",
      body: "Form, requestor, workflow step, and submitted time.",
      missingHint: "Skipped until a submission detail page is available.",
    },
    {
      id: "detail-print",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      target: "ea-detail-print",
      title: "Print / PDF",
      body: "Open a printable copy with approval history stamps. Next shows a sample print for this tour.",
      missingHint: "Skipped until a submission detail page is available.",
    },
    {
      id: "detail-print-sample",
      path: "/e-approval/submissions/tour-sample/print",
      entryPath: "/e-approval/submissions/tour-sample",
      autoNavFrom: "ea-detail-print",
      target: "ea-print-sample",
      title: "Printed copy",
      body: "Sample Document Approval print. Next: Title + Approver 1–3 fields, then approval trail, then attachment pages with stamps.",
      missingHint: "Sample print opens from Print / PDF during the tour.",
    },
    {
      id: "detail-print-doc-fields",
      path: "/e-approval/submissions/tour-sample/print",
      entryPath: "/e-approval/submissions/tour-sample/print",
      target: "ea-print-doc-fields",
      title: "Document Approval fields",
      body: "Only Title, Approver 1, Approver 2, Approver 3, and Attachments — the same fields as the live Document Approval form.",
      missingHint: "Scroll the print sample if this block is off-screen, then continue.",
    },
    {
      id: "detail-print-approval-trail",
      path: "/e-approval/submissions/tour-sample/print",
      entryPath: "/e-approval/submissions/tour-sample/print",
      target: "ea-print-approval-trail",
      title: "Approval trail",
      body: "Approver 1–3 status, acted time, and remarks. This trail feeds the signed footer on each attachment page.",
      missingHint: "Scroll down on the print sample to the Approval trail table.",
    },
    {
      id: "detail-print-attachments",
      path: "/e-approval/submissions/tour-sample/print",
      entryPath: "/e-approval/submissions/tour-sample/print",
      target: "ea-print-attachments",
      title: "Attachment pages + stamps",
      body: "Attachments only — each PDF page shows the approval-history signature footer (same idea as live every-page stamps).",
      missingHint: "Scroll below the form to the attachment sample pages.",
    },
    {
      id: "detail-tabs",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "request" },
      target: "ea-detail-tabs",
      title: "Request / Approvals / Activity",
      body: "Switch tabs for form values, workflow path, or comments. Decide appears only when you can approve.",
      missingHint: "Skipped until a submission detail page is available.",
    },
    {
      id: "detail-attachments",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "request" },
      target: "ea-detail-attachments",
      title: "Attachments",
      body: "Open previews to view files; stamps appear when approvals complete.",
      missingHint: "Skipped when there is no detail page, or this submission has no files.",
    },
    {
      id: "detail-workflow",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "approvals" },
      target: "ea-detail-workflow-path",
      title: "Workflow path",
      body: "Who must decide, what already ran, and what was skipped.",
      missingHint: "Skipped until a submission detail page is available.",
    },
    {
      id: "detail-activity",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "activity" },
      target: "ea-detail-comment",
      title: "Activity",
      body: "Post comments that stay with the submission for audit and handoff.",
      missingHint: "Skipped until a submission detail page is available.",
    },
    {
      id: "nav-e-approval-approvals",
      path: "/e-approval/submissions",
      entryPath: "/e-approval/submissions",
      target: "ea-nav-e-approval-approvals",
      title: "Open Approvals",
      body: "Under E-Approval → Decide in the sidebar, click Approvals for items waiting on your sign-off.",
      audience: "approver",
      missingHint: "Expand E-Approval in the sidebar to see Approvals.",
    },
    {
      id: "approvals-tabs",
      path: "/e-approval/approvals",
      entryPath: "/e-approval/approvals",
      autoNavFrom: "ea-nav-e-approval-approvals",
      target: "ea-approvals-tabs",
      title: "Awaiting me / All",
      body: "Awaiting me shows only items that need your sign-off.",
      audience: "approver",
    },
    {
      id: "approvals-queue",
      path: "/e-approval/approvals",
      target: "ea-approvals-queue",
      title: "Approval queue",
      body: "Open a row to review, then approve, reject, or request revision on the detail page.",
      audience: "approver",
    },
    {
      id: "nav-settings",
      path: "/e-approval/approvals",
      entryPath: "/e-approval/approvals",
      target: "ea-nav-settings",
      title: "Open Settings",
      body: "In the left sidebar, open Settings. Your signature is under personal E-Approval settings — not on the Approvals list.",
      audience: "approver",
      missingHint: "Expand Settings in the sidebar if it is collapsed, then continue.",
    },
    {
      id: "nav-e-approval-profile",
      path: "/e-approval/approvals",
      entryPath: "/e-approval/approvals",
      target: "ea-nav-e-approval-profile",
      title: "My E-Approval profile",
      body: "Under Settings, click My E-Approval profile. Next opens that page so you can save your signature.",
      audience: "approver",
      missingHint: "Expand Settings in the sidebar to see My E-Approval profile.",
    },
    {
      id: "profile-signature",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      autoNavFrom: "ea-nav-e-approval-profile",
      target: "ea-profile-signature",
      title: "My signature",
      body: "Save a signature here for reuse on Decide and printed PDF footers.",
      audience: "approver",
    },
    {
      id: "profile-signature-modes",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-profile-signature-modes",
      title: "Draw, Type, or Upload",
      body: "Choose how to capture your signature: draw on the pad, type your name, or upload a scan.",
      audience: "approver",
    },
    {
      id: "profile-signature-pad",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-profile-signature-pad",
      title: "Capture signature",
      body: "Create your signature in the selected mode before saving.",
      audience: "approver",
    },
    {
      id: "profile-signature-consent",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-profile-signature-consent",
      title: "Consent before save",
      body: "Accept both consents when you add or change a signature. Required before Save signature is enabled.",
      audience: "approver",
    },
    {
      id: "profile-signature-save",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-profile-signature-save",
      title: "Save signature",
      body: "Click Save signature to store it on your profile for approvals and PDF footers.",
      audience: "approver",
    },
    {
      id: "profile-signature-current",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-profile-signature-current",
      title: "Current signature",
      body: "Shows what is saved. Next, return to Approvals to decide on a request.",
      audience: "approver",
    },
    {
      id: "nav-decide-approvals",
      path: "/e-approval/profile",
      entryPath: "/e-approval/profile",
      target: "ea-nav-e-approval-approvals",
      title: "Open Approvals to decide",
      body: "In the sidebar under E-Approval → Decide, open Approvals. Next opens a sample request on the Decide tab.",
      audience: "approver",
      missingHint: "Expand E-Approval in the sidebar to see Approvals.",
    },
    {
      id: "decide-tab",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-detail-decide-tab",
      title: "Decide tab",
      body: "On a pending request, open Decide to apply your signature and choose Approve, Reject, or Request revision.",
      missingHint: "Decide appears when this document is waiting on you (or use the tour sample).",
      audience: "approver",
    },
    {
      id: "decide-panel",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-panel",
      title: "Your decision",
      body: "The decide panel holds signature, remarks, and action buttons. Sample UI during the tour is not saved.",
      missingHint: "Open the Decide tab. If you are not the pending approver, the tour sample includes this panel.",
      audience: "approver",
    },
    {
      id: "decide-signature",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-signature",
      title: "Your signature",
      body: "If you saved one in My E-Approval profile, it loads here automatically. Otherwise draw, type, or upload — then accept consent.",
      missingHint: "Open the Decide tab — the signature block is under Your decision.",
      audience: "approver",
    },
    {
      id: "decide-signature-modes",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-signature-modes",
      title: "Draw, Type, or Upload",
      body: "Choose how to capture your signature: draw on the pad, type your name, or upload a PNG/JPEG image.",
      missingHint: "Open Decide → Your signature modes.",
      skipIfProfileSignature: true,
      audience: "approver",
    },
    {
      id: "decide-signature-pad",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-signature-pad",
      title: "Signature pad",
      body: "Draw with mouse or finger, type your full name, or choose an image — whichever mode you selected above.",
      missingHint: "Open Decide → signature pad under Draw / Type / Upload.",
      skipIfProfileSignature: true,
      audience: "approver",
    },
    {
      id: "decide-signature-consent",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-signature-consent",
      title: "Signature consent",
      body: "Accept both consent checkboxes. Approve stays disabled until consent is accepted.",
      missingHint: "Open Decide → consent checkboxes under the signature pad.",
      audience: "approver",
    },
    {
      id: "decide-remarks",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-remarks",
      title: "Remarks",
      body: "Optional for Approve. Required (min 5 characters) for Reject or Request revision.",
      missingHint: "Open the Decide tab to see remarks.",
      audience: "approver",
    },
    {
      id: "decide-actions",
      path: "/e-approval/submissions/",
      pathMatch: "prefix",
      entryPath: "/e-approval/submissions/tour-sample",
      query: { tab: "decide" },
      target: "ea-decide-actions",
      title: "Approve, Reject, Revision",
      body: "Approve advances the workflow. Reject ends it. Request revision returns the request to the requestor. Remarks required for reject and revision.",
      missingHint: "Scroll within Decide — the three action buttons sit under remarks.",
      audience: "approver",
    },
    {
      id: "tour-complete",
      path: "/e-approval",
      target: "ea-tour-complete",
      title: "Tour complete",
      body: "You’re finished. You can start requests, track submissions, and decide from Approvals anytime. Click Finish tour to close — sample cards disappear and empty lists return.",
    },
  ],
};

/** Role-aware E-Approval tour steps (requestors skip Approvals / Decide / profile signature). */
export function resolveEApprovalTourSteps(
  capabilities: EApprovalTourCapabilities,
): LiveTourStep[] {
  const filtered = eApprovalLiveTour.steps
    .filter((step) => stepVisibleForCapabilities(step, capabilities))
    .map((step) => {
      if (step.id !== "tour-complete") {
        return step;
      }
      if (capabilities.canApprove) {
        return {
          ...step,
          body: "You’re finished. You can start requests, track submissions, and decide from Approvals anytime. Click Finish tour to close — sample cards disappear and empty lists return.",
        };
      }
      return {
        ...step,
        body: "You’re finished. You can start requests and track submissions anytime. Approver steps (Decide, signature) were skipped for your role. Click Finish tour to close.",
      };
    });
  return withEApprovalTourChapters(filtered);
}

export function resolveLiveTour(
  tourId: string | null,
  capabilities: EApprovalTourCapabilities,
): LiveTourDefinition | null {
  if (!tourId) {
    return null;
  }
  const base = tourById(tourId);
  if (!base) {
    return null;
  }
  if (base.id !== eApprovalLiveTour.id) {
    return base;
  }
  return {
    ...base,
    steps: resolveEApprovalTourSteps(capabilities),
  };
}
