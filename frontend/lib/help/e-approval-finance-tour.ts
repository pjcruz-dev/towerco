import type {
  EApprovalTourCapabilities,
  EApprovalTourChapterStart,
  LiveTourChapterId,
  LiveTourDefinition,
  LiveTourStep,
} from "@/lib/help/e-approval-live-tour";
import {
  E_APPROVAL_FINANCE_TOUR_FAMILY_SLUG,
  type EApprovalFinanceTourFamily,
} from "@/lib/help/e-approval-finance-tour-sample-data";

/** Live coach-mark tour for cash advance, liquidation, and reimbursement. */
export const E_APPROVAL_FINANCE_TOUR_ID = "e-approval-finance";

export const E_APPROVAL_FINANCE_TOUR_GUIDE_PATH = "/help/e-approval/finance";

export function financeTourComposePath(family: EApprovalFinanceTourFamily): string {
  return `/e-approval/request/tour-finance/${E_APPROVAL_FINANCE_TOUR_FAMILY_SLUG[family]}`;
}

export function financeTourDetailPath(family: EApprovalFinanceTourFamily): string {
  return `/e-approval/submissions/tour-finance/${E_APPROVAL_FINANCE_TOUR_FAMILY_SLUG[family]}`;
}

export function financeTourPrintPath(family: EApprovalFinanceTourFamily): string {
  return `${financeTourDetailPath(family)}/print`;
}

export function financeTourFamilyFromSlug(slug: string | null | undefined): EApprovalFinanceTourFamily | null {
  if (slug === "cash-advance") return "cash_advance";
  if (slug === "liquidation") return "liquidation";
  if (slug === "reimbursement") return "reimbursement";
  return null;
}

export function financeFamilyFromFormName(name: string | null | undefined): EApprovalFinanceTourFamily | null {
  const normalized = (name ?? "").trim().toLowerCase();
  if (!normalized) return null;
  if (normalized.includes("cash advance")) return "cash_advance";
  if (normalized.includes("liquidation")) return "liquidation";
  if (normalized.includes("reimbursement") || normalized.includes("reimburse")) return "reimbursement";
  return null;
}

export function financeFamilyForStepId(stepId: string | null | undefined): EApprovalFinanceTourFamily {
  if (stepId?.startsWith("fin-lq-")) return "liquidation";
  if (stepId?.startsWith("fin-re-")) return "reimbursement";
  return "cash_advance";
}

export function isEApprovalFinanceTourActive(searchParams: { get: (key: string) => string | null }): boolean {
  return searchParams.get("tour") === E_APPROVAL_FINANCE_TOUR_ID;
}

export function isEApprovalFinanceTourSamplePath(pathname: string): boolean {
  return (
    pathname.startsWith("/e-approval/request/tour-finance/") ||
    pathname.startsWith("/e-approval/submissions/tour-finance/")
  );
}

export function eApprovalFinanceTourExitPath(pathname: string): string {
  if (pathname.startsWith("/e-approval/request/tour-finance/")) {
    return "/e-approval/submissions/new";
  }
  if (pathname.startsWith("/e-approval/submissions/tour-finance/")) {
    return "/e-approval/submissions";
  }
  return pathname;
}

export const E_APPROVAL_FINANCE_TOUR_CHAPTER_STARTS: EApprovalTourChapterStart[] = [
  { id: "overview", how: "E-Forms → Overview → status cards", audience: "all" },
  {
    id: "track",
    how: "E-Forms → Submissions → status / advanced filters / Step Show",
    audience: "requestor",
  },
  {
    id: "cash_advance",
    how: "New submission → Cash advance → forecast lines → release",
    audience: "all",
  },
  {
    id: "liquidation",
    how: "New submission → Liquidation → released cash advance → settlement",
    audience: "all",
  },
  {
    id: "reimbursement",
    how: "New submission → Reimbursement → receipts → payment",
    audience: "all",
  },
  {
    id: "follow_up",
    how: "E-Forms → Submissions → open pending cash advance → Decide → Cancel / Follow-up",
    audience: "requestor",
  },
  { id: "approvals", how: "E-Forms → Approvals → queue", audience: "approver" },
  {
    id: "signature",
    how: "My profile → Account → E-Forms signature, or Settings → My E-Forms profile",
    audience: "approver",
  },
  { id: "decide", how: "E-Forms → Approvals → Decide tab on the cash advance", audience: "approver" },
];

function visible(step: LiveTourStep, capabilities: EApprovalTourCapabilities): boolean {
  const audience = step.audience ?? "all";
  if (audience === "approver") return capabilities.canApprove;
  if (audience === "requestor") return capabilities.canCreate;
  return true;
}

function formSteps(family: EApprovalFinanceTourFamily): LiveTourStep[] {
  const short = family === "cash_advance" ? "ca" : family === "liquidation" ? "lq" : "re";
  const id = (suffix: string) => `fin-${short}-${suffix}`;
  const chapter = family;
  const compose = financeTourComposePath(family);
  const detail = financeTourDetailPath(family);
  const print = financeTourPrintPath(family);
  const formName =
    family === "cash_advance" ? "Cash advance" : family === "liquidation" ? "Liquidation" : "Reimbursement";

  const steps: LiveTourStep[] = [
    {
      id: id("new"),
      chapter,
      path: "/e-approval/submissions",
      entryPath: "/e-approval/submissions",
      target: "ea-submissions-new",
      title: `Start a ${formName.toLowerCase()}`,
      body:
        family === "cash_advance"
          ? "On Submissions, click New submission. Next opens the form picker on Cash advance."
          : family === "liquidation"
            ? "Back on Submissions, click New submission again. Next opens Liquidation, which must link to a released cash advance."
            : "Click New submission once more. Reimbursement pays out-of-pocket expenses and does not need a cash advance.",
      audience: "requestor",
    },
    {
      id: id("picker-search"),
      chapter,
      path: "/e-approval/submissions/new",
      entryPath: "/e-approval/submissions/new",
      autoNavFrom: "ea-submissions-new",
      target: "ea-picker-search",
      title: "Search forms",
      body: `Filter published forms, then pick ${formName}.`,
      audience: "requestor",
    },
    {
      id: id("picker-card"),
      chapter,
      path: "/e-approval/submissions/new",
      target: "ea-picker-form-card",
      title: `${formName} form`,
      body:
        family === "cash_advance"
          ? "Cash advance forecasts the trip. Department Head, Finance, and the CFO approve, then Finance or Treasury releases the full amount. A new cash advance is blocked while one is still open."
          : family === "liquidation"
            ? "Liquidation settles one released cash advance. Actual lines carry the receipt, the forecast amount, and the variance."
            : "Reimbursement is the out-of-pocket form. Same approval chain as cash advance, then Finance or Treasury pays the total. No parent cash advance.",
      missingHint: `No ${formName} form published yet — a sample card is shown for this tour.`,
      audience: "requestor",
    },
    {
      id: id("picker-start"),
      chapter,
      path: "/e-approval/submissions/new",
      target: "ea-picker-start",
      title: "Start request",
      body: `Opens the ${formName} sample. Nothing you type here is saved.`,
      missingHint: "Start is unavailable until a form is published. Compose steps use the sample.",
      audience: "requestor",
    },
  ];

  if (family === "liquidation") {
    steps.push({
      id: id("compose-parent"),
      chapter,
      path: compose,
      entryPath: compose,
      autoNavFrom: "ea-picker-start",
      target: "ea-compose-parent",
      title: "Cash advance to liquidate",
      body: "Choose one released cash advance. The document number, purpose, subsidiary, and cash released fill in from that parent. You can only liquidate an advance that still has open balance.",
      audience: "requestor",
    });
  }

  steps.push(
    {
      id: id("compose-fields"),
      chapter,
      path: compose,
      entryPath: compose,
      autoNavFrom: family === "liquidation" ? undefined : "ea-picker-start",
      target: "ea-compose-fields",
      title: `${formName} fields`,
      body:
        family === "cash_advance"
          ? "Subsidiary, funds needed by, and purpose. The approval chain is Department Head → Finance → CFO → Release cash."
          : family === "liquidation"
            ? "Subsidiary and liquidation date. Purpose is copied from the cash advance and stays read-only."
            : "Subsidiary and purpose. Receipts are required before submit. The chain ends in Payment, not a cash release.",
      audience: "requestor",
    },
    {
      id: id("compose-lines"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-lines",
      title: family === "cash_advance" ? "Forecast lines" : "Expense lines",
      body:
        family === "cash_advance"
          ? "Each line is one trip: dates, from and to site, expense type, amount, and VAT treatment. VAT follows the rate in E-Forms settings."
          : family === "liquidation"
            ? "Actual spend against the forecast. OR number, supplier, forecast amount, and a variance reason when the amount differs."
            : "Out-of-pocket lines need an OR number, supplier, and description, plus expense type, amount, and VAT treatment.",
      audience: "requestor",
    },
  );

  if (family === "liquidation") {
    steps.push({
      id: id("compose-settlement"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-settlement",
      title: "Settlement",
      body: "Cash released comes from the linked advance. Settlement amount is actual expenses minus cash released. Positive means pay the employee. Negative means surrender.",
      audience: "requestor",
    });
  }

  steps.push(
    {
      id: id("compose-totals"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-totals",
      title: family === "cash_advance" ? "Requested amount" : "Total expenses",
      body:
        family === "cash_advance"
          ? "Requested amount is the sum of the Amount column, plus VAT, non-VATable, and VATable net. Finance releases this full amount."
          : family === "liquidation"
            ? "Total expenses is blocked when it exceeds the cash advance open balance. Pending liquidations count against that balance. Rejected ones do not."
            : "Total expenses is what Finance or Treasury pays after approval. It is the sum of the Amount column.",
      audience: "requestor",
    },
    {
      id: id("compose-upload"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-upload",
      title: family === "cash_advance" ? "Supporting documents" : "Receipts",
      body:
        family === "cash_advance"
          ? "Attach the itinerary or other support. Drag and drop, paste, or browse. This sample file is not uploaded."
          : "Receipts are required. The sample shows a PDF already attached — it is not uploaded.",
      audience: "requestor",
    },
    {
      id: id("compose-save"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-save-draft",
      title: "Save draft",
      body: "Keep the request in progress without starting the approval path.",
      audience: "requestor",
    },
    {
      id: id("compose-submit"),
      chapter,
      path: compose,
      entryPath: compose,
      target: "ea-compose-submit",
      title: "Submit request",
      body: `Sends the ${formName.toLowerCase()} into workflow. Next returns to Submissions to open the sample card.`,
      audience: "requestor",
    },
    {
      id: id("after-gallery"),
      chapter,
      path: "/e-approval/submissions",
      entryPath: "/e-approval/submissions",
      target: "ea-submissions-gallery",
      title: "Find the request",
      body: `The ${formName} sample sits in the gallery with its document number and Step Show. Sample cards disappear when the tour ends.`,
      listViewMode: "gallery",
      missingHint: "Sample cards appear while the tour is active even when the real list is empty.",
    },
    {
      id: id("after-open"),
      chapter,
      path: "/e-approval/submissions",
      target: "ea-submissions-card-actions",
      title: `Open the ${formName.toLowerCase()}`,
      body: "Click Open submission. Next continues on the sample detail.",
      listViewMode: "gallery",
      missingHint: "Sample Open link appears while the tour is active.",
    },
    {
      id: id("detail-header"),
      chapter,
      path: detail,
      entryPath: detail,
      autoNavFrom: "ea-submissions-card-actions",
      target: "ea-detail-header",
      title: "Document number & status",
      body: `${formName} document number and status. This sample stays Pending on Department Head.`,
    },
    {
      id: id("detail-summary"),
      chapter,
      path: detail,
      entryPath: detail,
      target: "ea-detail-summary",
      title: "Summary & Step Show",
      body: `Requestor, subsidiary, and the chain: ${
        family === "cash_advance"
          ? "Department Head → Finance → CFO → Release cash."
          : family === "liquidation"
            ? "Department Head → Finance → CFO → Finance validation → Settlement."
            : "Department Head → Finance → CFO → Payment."
      }`,
    },
    {
      id: id("detail-fields"),
      chapter,
      path: detail,
      entryPath: detail,
      target: "ea-detail-fields",
      title: `${formName} fields`,
      body:
        family === "liquidation"
          ? "Document number of the cash advance, liquidation date, and the purpose copied from that advance."
          : family === "cash_advance"
            ? "Subsidiary, funds needed by, and purpose — the same fields as the live cash advance form."
            : "Subsidiary and purpose — the same fields as the live reimbursement form.",
    },
    {
      id: id("detail-lines"),
      chapter,
      path: detail,
      entryPath: detail,
      target: "ea-detail-lines",
      title: family === "cash_advance" ? "Forecast lines" : "Expense lines",
      body:
        family === "cash_advance"
          ? "The forecast the approvers will release against."
          : "The actual lines, with OR and supplier where the form asks for them.",
    },
  );

  if (family === "liquidation") {
    steps.push(
      {
        id: id("detail-parent"),
        chapter,
        path: detail,
        entryPath: detail,
        target: "ea-detail-parent",
        title: "Linked cash advance",
        body: "This liquidation is tied to released cash advance ATC-CA-F-0990. The open balance is what total expenses is checked against.",
      },
      {
        id: id("detail-settlement"),
        chapter,
        path: detail,
        entryPath: detail,
        target: "ea-detail-settlement",
        title: "Settlement",
        body: "16,200.00 spent against 18,500.00 released. Settlement is surrender 2,300.00. The CFO sees the difference; Finance or Treasury records it.",
      },
    );
  }

  steps.push({
    id: id("detail-totals"),
    chapter,
    path: detail,
    entryPath: detail,
    target: "ea-detail-totals",
    title: family === "cash_advance" ? "Requested amount" : "Total expenses",
    body:
      family === "cash_advance"
        ? "18,500.00 requested, with VAT split out. Release pays this full amount."
        : family === "reimbursement"
          ? "4,800.00 to pay the employee after approval."
          : "16,200.00 of actual expenses. That figure is what the open-balance check uses.",
  });

  if (family !== "liquidation") {
    steps.push({
      id: id("detail-related"),
      chapter,
      path: detail,
      entryPath: detail,
      target: "ea-detail-related",
      title: family === "cash_advance" ? "Later liquidation" : "No cash advance",
      body:
        family === "cash_advance"
          ? "Nothing is linked yet. After release, a liquidation points back here. Until then, another cash advance for this requestor is blocked."
          : "Reimbursement does not attach to a cash advance and does not change an open balance.",
    });
  }

  steps.push(
    {
      id: id("detail-print"),
      chapter,
      path: detail,
      entryPath: detail,
      target: "ea-detail-print",
      title: "Print / PDF",
      body: `Opens the ${formName} print sample. Landscape, with the expense grid.`,
    },
    {
      id: id("print-sample"),
      chapter,
      path: print,
      entryPath: detail,
      autoNavFrom: "ea-detail-print",
      target: "ea-print-sample",
      title: "Printed copy",
      body:
        family === "liquidation"
          ? "Liquidation print shows the cash advance number and the settlement under the lines."
          : `Sample ${formName} print. Scroll the grid — the tour stays open.`,
    },
    {
      id: id("print-fields"),
      chapter,
      path: print,
      entryPath: print,
      target: "ea-print-doc-fields",
      title: "Form on the page",
      body:
        family === "cash_advance"
          ? "Letterhead, purpose, forecast lines, and notes. Same layout as a live cash advance PDF."
          : family === "liquidation"
            ? "Cash advance reference, actual lines, and the surrender or payment line."
            : "Purpose, actual lines, and notes. No cash advance number on this form.",
    },
  );

  if (family === "cash_advance") {
    steps.push(
      {
        id: id("print-attachments"),
        chapter,
        path: print,
        entryPath: print,
        target: "ea-print-attachments",
        title: "Attachment pages",
        body: "Each supporting file is its own page with the approval-history footer, same idea as live every-page stamps.",
        missingHint: "Scroll below the form to the attachment sample.",
      },
      {
        id: id("detail-tabs"),
        chapter,
        path: detail,
        entryPath: detail,
        query: { tab: "request" },
        target: "ea-detail-tabs",
        title: "Request / Approvals / Activity",
        body: "Switch tabs for the form, the workflow path, or comments. Decide appears when you can approve or you own the request.",
      },
      {
        id: id("detail-attachments"),
        chapter,
        path: detail,
        entryPath: detail,
        query: { tab: "request" },
        target: "ea-detail-attachments",
        title: "Attachments",
        body: "Open previews from the request. Stamps appear on the printed copy when approvals complete.",
      },
      {
        id: id("detail-workflow"),
        chapter,
        path: detail,
        entryPath: detail,
        query: { tab: "approvals" },
        target: "ea-detail-workflow-path",
        title: "Workflow path",
        body: "Department Head is current. Finance, the CFO, and Release cash are still ahead.",
      },
      {
        id: id("detail-activity"),
        chapter,
        path: detail,
        entryPath: detail,
        query: { tab: "activity" },
        target: "ea-detail-comment",
        title: "Activity",
        body: "Comments stay on the submission for audit and handoff.",
      },
    );
  }

  return steps;
}

const overviewSteps: LiveTourStep[] = [
  {
    id: "nav-e-approval",
    chapter: "overview",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-nav-e-approval",
    title: "Open E-Forms",
    body: "In the sidebar, open E-Forms. Cash advance, liquidation, and reimbursement all live here with the other requests.",
    missingHint: "On a phone, open the menu (☰) first. Expand E-Forms if it is collapsed, then continue.",
  },
  {
    id: "nav-e-approval-overview",
    chapter: "overview",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-nav-e-approval-overview",
    title: "Overview",
    body: "Click Overview for counts of work waiting on you.",
    missingHint: "Expand E-Forms in the sidebar to see Overview.",
  },
  {
    id: "overview-kpis",
    chapter: "overview",
    path: "/e-approval",
    entryPath: "/e-approval",
    autoNavFrom: "ea-nav-e-approval-overview",
    target: "ea-overview-kpis",
    title: "Status cards",
    body: "Approvals waiting on you, returns, open submissions, and SLA risk. Empty until the workspace has requests.",
  },
  {
    id: "overview-awaiting",
    chapter: "overview",
    path: "/e-approval",
    target: "ea-overview-awaiting",
    title: "Needs my approval",
    body: "Oldest items assigned to you, including a cash advance when you are on its current step.",
    audience: "approver",
  },
  {
    id: "overview-attention",
    chapter: "overview",
    path: "/e-approval",
    target: "ea-overview-attention",
    title: "Needs my attention",
    body: "Your drafts and returned requests, including an unfinished cash advance or liquidation.",
  },
  {
    id: "overview-actions",
    chapter: "overview",
    path: "/e-approval",
    target: "ea-overview-quick-actions",
    title: "Quick actions",
    body: "Jump to Approvals or start a new submission from here.",
  },
];

const trackSteps: LiveTourStep[] = [
  {
    id: "nav-e-approval-submissions",
    chapter: "track",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-nav-e-approval-submissions",
    title: "Open Submissions",
    body: "Under E-Forms, click Submissions to track cash advances, liquidations, and reimbursements.",
    missingHint: "Expand E-Forms in the sidebar to see Submissions.",
  },
  {
    id: "submissions-filters",
    chapter: "track",
    path: "/e-approval/submissions",
    entryPath: "/e-approval/submissions",
    autoNavFrom: "ea-nav-e-approval-submissions",
    target: "ea-submissions-filters",
    title: "Status filters",
    body: "Narrow to Needs revision, Pending, Approved, and more. Use Mine / All users when you can view everyone’s requests.",
  },
  {
    id: "submissions-search",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-search",
    title: "Search submissions",
    body: "Find a cash advance or liquidation by document number, form name, or requestor.",
  },
  {
    id: "submissions-advanced-filters",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-advanced-filters",
    title: "Advanced filters",
    body: "Filter by Form (Cash advance, Liquidation, Reimbursement), Subsidiary, Department, and submitted date.",
  },
  {
    id: "submissions-view-gallery",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-gallery",
    title: "Gallery view",
    body: "Cards show document number, form, subsidiary, and Step Show. The tour samples three finance requests.",
    listViewMode: "gallery",
    missingHint: "Gallery cards appear when the list has rows, or as samples while this tour is active.",
  },
  {
    id: "submissions-view-table",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-table",
    title: "Table view",
    body: "Dense rows for sorting many requests. Switch back to Gallery with the same toggle.",
    listViewMode: "table",
    missingHint: "Table rows appear when the list has data. Continue if you only have the sample.",
  },
  {
    id: "submissions-step-show",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-step-show",
    title: "Step Show",
    body: "Which step is current — Department Head, Finance, CFO, then release, settlement, or payment.",
    listViewMode: "gallery",
    missingHint: "Step Show appears on gallery cards and in the table Step column.",
  },
  {
    id: "submissions-status",
    chapter: "track",
    path: "/e-approval/submissions",
    target: "ea-submissions-status",
    title: "Status badge",
    body: "Pending, Approved, Rejected, Draft, and the other states. These samples are Pending.",
    listViewMode: "gallery",
  },
];

const CA_DETAIL = financeTourDetailPath("cash_advance");

const followUpSteps: LiveTourStep[] = [
  {
    id: "follow-up-nav-e-approval",
    chapter: "follow_up",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-nav-e-approval",
    title: "Open E-Forms",
    body: "Cancel and follow-up start from a pending request you own. This chapter uses the sample cash advance.",
    audience: "requestor",
    missingHint: "On a phone, open the menu (☰) first.",
  },
  {
    id: "follow-up-nav-submissions",
    chapter: "follow_up",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-nav-e-approval-submissions",
    title: "Open Submissions",
    body: "Under E-Forms, click Submissions and find the pending cash advance.",
    audience: "requestor",
    missingHint: "Expand E-Forms in the sidebar to see Submissions.",
  },
  {
    id: "follow-up-open-request",
    chapter: "follow_up",
    path: "/e-approval/submissions",
    entryPath: "/e-approval/submissions",
    autoNavFrom: "ea-nav-e-approval-submissions",
    target: "ea-submissions-card-actions",
    title: "Open the pending cash advance",
    body: "Open ATC-CA-F-1042 (or any pending request you own). Next continues on Decide.",
    audience: "requestor",
    listViewMode: "gallery",
    missingHint: "Sample Open link appears while the tour is active.",
  },
  {
    id: "follow-up-decide-tab",
    chapter: "follow_up",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    autoNavFrom: "ea-submissions-card-actions",
    query: { tab: "decide" },
    target: "ea-detail-decide-tab",
    title: "Open Decide",
    body: "On a pending request you own, Decide is where Cancel and Follow-up live.",
    audience: "requestor",
    missingHint: "Open the sample cash advance, then select Decide.",
    skipIfMissing: true,
  },
  {
    id: "detail-requestor-actions",
    chapter: "follow_up",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-detail-requestor-actions",
    title: "Requestor actions",
    body: "While the cash advance is pending — including before release — cancel it or nudge the current approvers.",
    audience: "requestor",
    skipIfMissing: true,
  },
  {
    id: "detail-cancel",
    chapter: "follow_up",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-detail-cancel",
    title: "Cancel the request",
    body: "Cancel withdraws a pending cash advance, liquidation, or reimbursement so approvers no longer need to act.",
    audience: "requestor",
    skipIfMissing: true,
  },
  {
    id: "detail-follow-up",
    chapter: "follow_up",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-detail-follow-up",
    title: "Manual follow-up",
    body: "Send follow-up notifies every pending approver on the current step by email and in-app. A cooldown may apply.",
    audience: "requestor",
    skipIfMissing: true,
  },
];

const approvalSteps: LiveTourStep[] = [
  {
    id: "nav-e-approval-approvals",
    chapter: "approvals",
    path: "/e-approval/submissions",
    entryPath: "/e-approval/submissions",
    target: "ea-nav-e-approval-approvals",
    title: "Open Approvals",
    body: "Under E-Forms → Decide, click Approvals for cash advances, liquidations, and reimbursements waiting on you.",
    audience: "approver",
    missingHint: "Expand E-Forms in the sidebar to see Approvals.",
  },
  {
    id: "approvals-tabs",
    chapter: "approvals",
    path: "/e-approval/approvals",
    entryPath: "/e-approval/approvals",
    autoNavFrom: "ea-nav-e-approval-approvals",
    target: "ea-approvals-tabs",
    title: "Awaiting me / All",
    body: "Awaiting me shows only the finance requests that need your sign-off.",
    audience: "approver",
  },
  {
    id: "approvals-queue",
    chapter: "approvals",
    path: "/e-approval/approvals",
    target: "ea-approvals-queue",
    title: "Approval queue",
    body: "Open a row to review the lines and the settlement, then approve, reject, or request revision.",
    audience: "approver",
  },
];

const signatureSteps: LiveTourStep[] = [
  {
    id: "nav-settings",
    chapter: "signature",
    path: "/e-approval/approvals",
    entryPath: "/e-approval/approvals",
    target: "ea-nav-settings",
    title: "Open Settings",
    body: "Your signature is My E-Forms profile — not on the Approvals list. The same page is My profile → Account → E-Forms signature.",
    audience: "approver",
    missingHint: "Expand Settings in the sidebar if it is collapsed, then continue.",
  },
  {
    id: "nav-e-approval-profile",
    chapter: "signature",
    path: "/e-approval/approvals",
    entryPath: "/e-approval/approvals",
    target: "ea-nav-e-approval-profile",
    title: "My E-Forms profile",
    body: "Under Settings, click My E-Forms profile. Next opens that page so you can save your signature.",
    audience: "approver",
    missingHint: "Expand Settings in the sidebar to see My E-Forms profile.",
  },
  {
    id: "profile-signature",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    autoNavFrom: "ea-nav-e-approval-profile",
    target: "ea-profile-signature",
    title: "My signature",
    body: "Save a signature here. It loads on Decide for cash advance, liquidation, and reimbursement, and on printed footers.",
    audience: "approver",
  },
  {
    id: "profile-signature-modes",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-profile-signature-modes",
    title: "Draw, Type, or Upload",
    body: "Draw on the pad, type your name, or upload a scan.",
    audience: "approver",
  },
  {
    id: "profile-signature-pad",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-profile-signature-pad",
    title: "Capture signature",
    body: "Create your signature in the selected mode before saving.",
    audience: "approver",
  },
  {
    id: "profile-signature-consent",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-profile-signature-consent",
    title: "Consent before save",
    body: "Accept both consents when you add or change a signature.",
    audience: "approver",
  },
  {
    id: "profile-signature-save",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-profile-signature-save",
    title: "Save signature",
    body: "Click Save signature to store it for approvals and PDF footers.",
    audience: "approver",
  },
  {
    id: "profile-signature-current",
    chapter: "signature",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-profile-signature-current",
    title: "Current signature",
    body: "Shows what is saved. Next, return to Approvals to decide on the sample cash advance.",
    audience: "approver",
  },
];

const decideSteps: LiveTourStep[] = [
  {
    id: "nav-decide-approvals",
    chapter: "decide",
    path: "/e-approval/profile",
    entryPath: "/e-approval/profile",
    target: "ea-nav-e-approval-approvals",
    title: "Open Approvals to decide",
    body: "In the sidebar under E-Forms → Decide, open Approvals. Next opens the sample cash advance on Decide.",
    audience: "approver",
    missingHint: "Expand E-Forms in the sidebar to see Approvals.",
  },
  {
    id: "decide-tab",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-detail-decide-tab",
    title: "Decide tab",
    body: "On a pending cash advance, liquidation, or reimbursement, Decide is where you sign and choose an action.",
    missingHint: "Decide appears when this document is waiting on you (or on the tour sample).",
    audience: "approver",
  },
  {
    id: "decide-panel",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-panel",
    title: "Your decision",
    body: "Signature, remarks, and the action buttons. Sample UI during the tour is not saved.",
    audience: "approver",
  },
  {
    id: "decide-signature",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-signature",
    title: "Your signature",
    body: "A signature saved on My E-Forms profile loads here. Otherwise draw, type, or upload, then accept consent.",
    audience: "approver",
  },
  {
    id: "decide-signature-modes",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-signature-modes",
    title: "Draw, Type, or Upload",
    body: "Choose how to capture the signature for this decision.",
    skipIfProfileSignature: true,
    audience: "approver",
  },
  {
    id: "decide-signature-pad",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-signature-pad",
    title: "Signature pad",
    body: "Draw, type your name, or choose an image.",
    skipIfProfileSignature: true,
    audience: "approver",
  },
  {
    id: "decide-signature-consent",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-approve",
    title: "Signature consent",
    body: "Approve opens a confirmation. Check both consent boxes. Confirm stays off until both are checked.",
    audience: "approver",
  },
  {
    id: "decide-remarks",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-remarks",
    title: "Remarks",
    body: "Optional for Approve. Required (min 5 characters) for Reject or Request revision. On a liquidation, use remarks when you question the variance or the settlement.",
    audience: "approver",
  },
  {
    id: "decide-actions",
    chapter: "decide",
    path: CA_DETAIL,
    entryPath: CA_DETAIL,
    query: { tab: "decide" },
    target: "ea-decide-actions",
    title: "Approve, Reject, Revision",
    body: "Approve moves the cash advance toward release (or a liquidation toward settlement, or a reimbursement toward payment). Reject ends it. Request revision sends it back.",
    audience: "approver",
  },
];

function completeStep(capabilities?: EApprovalTourCapabilities): LiveTourStep {
  const canApprove = capabilities?.canApprove ?? true;
  return {
    id: "tour-complete",
    chapter: "complete",
    path: "/e-approval",
    entryPath: "/e-approval",
    target: "ea-tour-complete",
    title: "Tour complete",
    body: canApprove
      ? "You’re finished. You can request a cash advance, liquidate a released one, or file a reimbursement, and decide from Approvals. Click Finish tour to close — sample cards disappear."
      : "You’re finished. You can start a cash advance, liquidation, or reimbursement and track them from Submissions. Approver steps were skipped for your role. Click Finish tour to close.",
  };
}

export const eApprovalFinanceLiveTour: LiveTourDefinition = {
  id: E_APPROVAL_FINANCE_TOUR_ID,
  title: "Cash advance, liquidation & reimbursement",
  steps: [
    ...overviewSteps,
    ...trackSteps,
    ...formSteps("cash_advance"),
    ...formSteps("liquidation"),
    ...formSteps("reimbursement"),
    ...followUpSteps,
    ...approvalSteps,
    ...signatureSteps,
    ...decideSteps,
    completeStep(),
  ],
};

export function resolveEApprovalFinanceTourSteps(
  capabilities: EApprovalTourCapabilities,
): LiveTourStep[] {
  return eApprovalFinanceLiveTour.steps
    .filter((step) => visible(step, capabilities))
    .map((step) => (step.id === "tour-complete" ? completeStep(capabilities) : step));
}

export function financeTourChaptersForCapabilities(
  capabilities: EApprovalTourCapabilities,
): EApprovalTourChapterStart[] {
  return E_APPROVAL_FINANCE_TOUR_CHAPTER_STARTS.filter((chapter) => {
    const audience = chapter.audience ?? "all";
    if (audience === "approver") return capabilities.canApprove;
    if (audience === "requestor") return capabilities.canCreate;
    return true;
  });
}

export function financeTourStartHref(
  stepIndex = 0,
  capabilities?: EApprovalTourCapabilities,
  options?: { chapterId?: LiveTourChapterId },
): string {
  const steps = capabilities
    ? resolveEApprovalFinanceTourSteps(capabilities)
    : eApprovalFinanceLiveTour.steps;
  const safeIndex = Math.max(0, Math.min(stepIndex, Math.max(0, steps.length - 1)));
  const step = steps[safeIndex];
  if (!step) return E_APPROVAL_FINANCE_TOUR_GUIDE_PATH;
  const params = new URLSearchParams();
  params.set("tour", E_APPROVAL_FINANCE_TOUR_ID);
  params.set("tourStep", String(safeIndex));
  if (step.query) {
    for (const [key, value] of Object.entries(step.query)) {
      params.set(key, value);
    }
  }
  if (options?.chapterId && options.chapterId !== "complete") {
    params.set("tourChapter", options.chapterId);
  }
  const path = step.entryPath ?? step.path;
  return `${path}?${params.toString()}`;
}

export function financeTourChapterStartHref(
  chapterId: LiveTourChapterId,
  capabilities: EApprovalTourCapabilities,
): string {
  const steps = resolveEApprovalFinanceTourSteps(capabilities);
  const index = steps.findIndex((step) => step.chapter === chapterId);
  return financeTourStartHref(index >= 0 ? index : 0, capabilities, { chapterId });
}
