import type { VisualGuideTab } from "@/lib/help/visual-guide";

/**
 * Diagrams-first Ticketing visual guide (real product screenshots).
 * Callout x/y are % of full screenshots (~1024×550) including the left sidebar.
 * Calibrated against the Aug 2026 staging captures.
 */
export const ticketingVisualGuideTabs: VisualGuideTab[] = [
  {
    id: "overview",
    label: "Overview",
    sections: [
      {
        id: "overview-home",
        title: "Your Ticketing home",
        description:
          "Start here for open work, items assigned to you, SLA risk, and a path into the queue or a new ticket.",
        imageSrc: "/help/ticketing/01-overview.png",
        imageAlt: "Ticketing overview dashboard",
        callouts: [
          {
            n: 1,
            title: "Status cards",
            body: "Open / in progress, assigned to you, urgent, SLA at risk, and resolved this week at a glance.",
            x: 48,
            y: 22,
          },
          {
            n: 2,
            title: "New ticket",
            body: "Raise an issue from the header without hunting the sidebar.",
            x: 94,
            y: 13,
          },
          {
            n: 3,
            title: "Queue and analytics",
            body: "Charts and category analytics show where work is concentrating across the workspace.",
            x: 40,
            y: 48,
          },
          {
            n: 4,
            title: "Sidebar — Ticketing",
            body: "Overview, Tickets, and New ticket live under Ticketing in the left navigation.",
            x: 9,
            y: 21,
          },
        ],
      },
    ],
  },
  {
    id: "tickets",
    label: "Tickets",
    sections: [
      {
        id: "tickets-queue",
        title: "Anatomy of the ticket queue",
        description:
          "Search and filter the operational queue. Open any row to work the ticket.",
        imageSrc: "/help/ticketing/02-tickets.png",
        imageAlt: "Ticketing tickets list",
        callouts: [
          {
            n: 1,
            title: "New ticket",
            body: "Start a new issue when something needs IT or operations attention.",
            x: 94,
            y: 13,
          },
          {
            n: 2,
            title: "Search and filters",
            body: "Find by title, ticket number, status, category, or limit to tickets you raised or that are assigned to you.",
            x: 45,
            y: 26,
          },
          {
            n: 3,
            title: "Ticket queue",
            body: "Status, priority, requester, assignee, and last update. Open a ticket number to open detail.",
            x: 52,
            y: 45,
          },
          {
            n: 4,
            title: "Sidebar — Tickets",
            body: "Open Tickets under Ticketing to reach this list anytime.",
            x: 9,
            y: 26,
          },
        ],
      },
    ],
  },
  {
    id: "create",
    label: "New ticket",
    sections: [
      {
        id: "compose",
        title: "Raise a ticket",
        description:
          "Summarize the issue, pick a category, attach evidence, then submit. Priority is set during triage.",
        imageSrc: "/help/ticketing/03-new-ticket.png",
        imageAlt: "New ticket compose form",
        callouts: [
          {
            n: 1,
            title: "Title and description",
            body: "Summarize the issue, then add enough detail for the team to reproduce or triage it.",
            x: 45,
            y: 32,
          },
          {
            n: 2,
            title: "Category",
            body: "Pick a category so auto-assign and SLA rules can route the ticket correctly.",
            x: 30,
            y: 57,
          },
          {
            n: 3,
            title: "Attachments",
            body: "Add screenshots or documents (PNG, JPG, PDF, and office files) to speed up resolution.",
            x: 30,
            y: 69,
          },
          {
            n: 4,
            title: "Submit ticket",
            body: "Create the ticket when ready. You land on the detail page to track progress and comments.",
            x: 22,
            y: 78,
          },
        ],
        tip: "You can also start from Ticketing → New ticket in the sidebar.",
      },
    ],
  },
  {
    id: "detail",
    label: "Ticket detail",
    sections: [
      {
        id: "view-detail",
        title: "Reading a ticket",
        description:
          "Open any ticket for status, priority, SLA, description, attachments, and activity.",
        imageSrc: "/help/ticketing/04-detail.png",
        imageAlt: "Ticket detail page",
        callouts: [
          {
            n: 1,
            title: "Ticket header",
            body: "Ticket number, status, priority, SLA risk, and category appear here.",
            x: 38,
            y: 22,
          },
          {
            n: 2,
            title: "Description and attachments",
            body: "Full issue text plus files the requester uploaded for triage.",
            x: 40,
            y: 38,
          },
          {
            n: 3,
            title: "Comments and activity",
            body: "Requestors and IT leave updates here. Managers can post internal notes.",
            x: 40,
            y: 66,
          },
          {
            n: 4,
            title: "Details and Manage",
            body: "Requester, assignee, and SLA due stay on the right. Managers update status, priority, category, and assignee from Manage.",
            x: 88,
            y: 55,
          },
        ],
        tip: "A sample ticket (TKT-TOUR-0001) appears during the live tour and is never saved.",
      },
    ],
  },
];
