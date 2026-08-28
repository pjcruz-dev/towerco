export type VisualCallout = {
  n: number;
  title: string;
  body: string;
  /** Percent from left / top of the screenshot (0–100). */
  x: number;
  y: number;
};

export type VisualGuideSection = {
  id: string;
  title: string;
  description: string;
  imageSrc: string;
  imageAlt: string;
  callouts: VisualCallout[];
  tip?: string;
};

export type VisualGuideTab = {
  id: string;
  label: string;
  sections: VisualGuideSection[];
};

/**
 * Diagrams-first E-Approval visual guide.
 * Callout x/y are % of full screenshots (~1910×900) that include the left sidebar.
 * Calibrated against 10% grid overlays on the source PNGs (Aug 2026).
 */
export const eApprovalVisualGuideTabs: VisualGuideTab[] = [
  {
    id: "overview",
    label: "Overview",
    sections: [
      {
        id: "overview-home",
        title: "Your E-Approval home",
        description:
          "Start here for counts of work waiting on you, open drafts, and shortcuts into Submissions or Approvals.",
        imageSrc: "/help/e-approval/01-overview.png",
        imageAlt: "E-Approval overview dashboard",
        callouts: [
          {
            n: 1,
            title: "Status cards",
            body: "Awaiting my approval, returned items, your open submissions, and SLA risk at a glance.",
            x: 48,
            y: 25,
          },
          {
            n: 2,
            title: "Needs my approval",
            body: "Items assigned to you for a decision. Open one to approve, reject, or ask for changes.",
            x: 32,
            y: 42,
          },
          {
            n: 3,
            title: "Needs my attention",
            body: "Drafts and returns that still need you to finish or resubmit.",
            x: 72,
            y: 42,
          },
          {
            n: 4,
            title: "Quick actions",
            body: "Jump to Approvals or start a new submission without hunting the sidebar.",
            x: 95,
            y: 13,
          },
        ],
      },
    ],
  },
  {
    id: "submissions",
    label: "Submissions",
    sections: [
      {
        id: "submissions-list",
        title: "Anatomy of the Submissions list",
        description:
          "Track every request you own. Filter by status, search by document number, and switch gallery or table.",
        imageSrc: "/help/e-approval/02-submissions.png",
        imageAlt: "E-Approval submissions gallery",
        callouts: [
          {
            n: 1,
            title: "New submission",
            body: "Start a request from a published form.",
            x: 95,
            y: 12,
          },
          {
            n: 2,
            title: "Status filters",
            body: "Narrow to Needs revision, Pending, Approved, Rejected, or Cancelled.",
            x: 22,
            y: 21,
          },
          {
            n: 3,
            title: "Search submissions",
            body: "Find by document number, form name, or requestor.",
            x: 28,
            y: 28,
          },
          {
            n: 4,
            title: "Gallery view",
            body: "Card layout for scanning document number, status, and requestor.",
            x: 50,
            y: 42,
          },
          {
            n: 5,
            title: "Table view",
            body: "Dense rows for sorting and scanning many requests.",
            x: 91,
            y: 28,
          },
          {
            n: 6,
            title: "Status badge",
            body: "Draft, Approved, Rejected, and other states are color-coded on each card.",
            x: 67,
            y: 37,
          },
        ],
      },
    ],
  },
  {
    id: "start",
    label: "Start a request",
    sections: [
      {
        id: "pick-form",
        title: "Pick a published form",
        description: "The tour uses Document Approval. Search if the list is long.",
        imageSrc: "/help/e-approval/03-new-submission.png",
        imageAlt: "Choose Document Approval form",
        callouts: [
          {
            n: 1,
            title: "Search forms",
            body: "Filter by form name, category, or description.",
            x: 28,
            y: 23,
          },
          {
            n: 2,
            title: "Document Approval form",
            body: "Title, Approver 1–3, and Attachments — not Cash Advance or Liquidation.",
            x: 28,
            y: 36,
          },
          {
            n: 3,
            title: "Start request",
            body: "Opens Document Approval compose for this walkthrough.",
            x: 28,
            y: 49,
          },
        ],
      },
      {
        id: "compose",
        title: "Fill and submit",
        description:
          "Complete Title and Approver 1–3, attach PDFs, save a draft anytime, then submit when ready.",
        imageSrc: "/help/e-approval/04-compose.png",
        imageAlt: "Document Approval compose form",
        callouts: [
          {
            n: 1,
            title: "Document Approval fields",
            body: "Title and Approver 1–3 — the same fields as the live Document Approval form.",
            x: 40,
            y: 25,
          },
          {
            n: 2,
            title: "Attachments",
            body: "Attach PDF documents for review.",
            x: 22,
            y: 45,
          },
          {
            n: 3,
            title: "Save draft",
            body: "Keep work in progress without starting the approval path.",
            x: 78,
            y: 57,
          },
          {
            n: 4,
            title: "Submit request",
            body: "Sends the request into workflow. Approvers are notified per policy.",
            x: 88,
            y: 57,
          },
        ],
        tip: "You can leave and reopen a draft from Submissions → Continue editing.",
      },
    ],
  },
  {
    id: "detail",
    label: "Reading a request",
    sections: [
      {
        id: "detail-request",
        title: "Request tab",
        description:
          "Open any submission to see metadata, status, attachments, and Print / PDF.",
        imageSrc: "/help/e-approval/05-detail-request.png",
        imageAlt: "Approved submission request details",
        callouts: [
          {
            n: 1,
            title: "Document number & status",
            body: "Identifier plus a status badge (Approved, Rejected, Draft, and more).",
            x: 95,
            y: 11,
          },
          {
            n: 2,
            title: "Summary strip",
            body: "Form, requestor, workflow step, and submitted time.",
            x: 50,
            y: 26,
          },
          {
            n: 3,
            title: "Print / PDF",
            body: "Download or print a stamped copy when signatures are available.",
            x: 27,
            y: 35,
          },
          {
            n: 4,
            title: "Request / Approvals / Activity",
            body: "Switch tabs for form values, workflow path, or comments.",
            x: 42,
            y: 44,
          },
          {
            n: 5,
            title: "Attachments",
            body: "Open previews to view files; stamps appear when approvals complete.",
            x: 40,
            y: 82,
          },
        ],
      },
      {
        id: "detail-approvals",
        title: "Approvals tab — workflow path",
        description:
          "See who must decide, what already ran, and what was skipped.",
        imageSrc: "/help/e-approval/06-detail-approvals.png",
        imageAlt: "Submission workflow path",
        callouts: [
          {
            n: 1,
            title: "Workflow path",
            body: "Step order from Start to End for this request.",
            x: 55,
            y: 52,
          },
          {
            n: 2,
            title: "Step status",
            body: "Approved, pending, or skipped for each approver.",
            x: 62,
            y: 55,
          },
          {
            n: 3,
            title: "Legend",
            body: "Runs vs skipped steps so parallel or optional paths stay clear.",
            x: 90,
            y: 46,
          },
        ],
      },
      {
        id: "detail-activity",
        title: "Activity tab",
        description: "Comments and remarks stay with the submission for audit and handoff.",
        imageSrc: "/help/e-approval/10-activity.png",
        imageAlt: "Submission activity and comments",
        callouts: [
          {
            n: 1,
            title: "Activity",
            body: "Timeline of comments tied to this request.",
            x: 25,
            y: 52,
          },
          {
            n: 2,
            title: "Add a comment",
            body: "Post a note for requestors and approvers on the same thread.",
            x: 50,
            y: 66,
          },
        ],
      },
    ],
  },
  {
    id: "decide",
    label: "Decide",
    sections: [
      {
        id: "approval-inbox",
        title: "Approval inbox",
        description:
          "Under Decide → Approvals. Use Awaiting me for items that need your sign-off.",
        imageSrc: "/help/e-approval/07-approval-inbox.png",
        imageAlt: "E-Approval approval inbox",
        callouts: [
          {
            n: 1,
            title: "Awaiting me",
            body: "Only submissions waiting on your decision.",
            x: 17,
            y: 21,
          },
          {
            n: 2,
            title: "All",
            body: "Broader list when you need history beyond your queue.",
            x: 22,
            y: 21,
          },
          {
            n: 3,
            title: "Empty state",
            body: "Nothing here means no item currently needs your approval.",
            x: 56,
            y: 44,
          },
        ],
        tip: "Open a row to review the request, then approve, reject, or request revision on the detail page.",
      },
    ],
  },
  {
    id: "returns",
    label: "Returns & drafts",
    sections: [
      {
        id: "rejected",
        title: "When a request is rejected",
        description:
          "The detail page explains who rejected it and offers a clear path to fix and resubmit.",
        imageSrc: "/help/e-approval/08-rejected.png",
        imageAlt: "Rejected submission with resubmit actions",
        callouts: [
          {
            n: 1,
            title: "Rejection banner",
            body: "Who rejected, when, and any note they left.",
            x: 45,
            y: 26,
          },
          {
            n: 2,
            title: "Edit and resubmit",
            body: "Change fields or attachments, then send again.",
            x: 24,
            y: 35,
          },
          {
            n: 3,
            title: "Resubmit without changes",
            body: "Send the same payload again when only a re-run is needed.",
            x: 34,
            y: 35,
          },
        ],
      },
      {
        id: "draft",
        title: "Draft not submitted yet",
        description: "Drafts stay private until you submit. Continue editing from the banner or Submissions.",
        imageSrc: "/help/e-approval/09-draft.png",
        imageAlt: "Draft submission detail",
        callouts: [
          {
            n: 1,
            title: "Draft badge",
            body: "Confirms the request has not entered approval yet.",
            x: 96,
            y: 11,
          },
          {
            n: 2,
            title: "Continue editing",
            body: "Returns you to the compose form to finish and submit.",
            x: 94,
            y: 21,
          },
        ],
      },
    ],
  },
];
