import type { TicketingTicketDetail, TicketingTicketListRow } from "@/modules/ticketing/types";

import { TICKETING_LIVE_TOUR_ID } from "@/lib/help/ticketing-live-tour";

/** Client-only sample routes used by the live tour (never persisted). */
export const TICKETING_TOUR_SAMPLE_TICKET_ID = "tour-sample";
export const TICKETING_TOUR_SAMPLE_DETAIL_PATH = `/ticketing/tickets/${TICKETING_TOUR_SAMPLE_TICKET_ID}`;

export function isTicketingTourActive(searchParams: { get: (key: string) => string | null }): boolean {
  return searchParams.get("tour") === TICKETING_LIVE_TOUR_ID;
}

export function isTicketingTourSamplePath(pathname: string): boolean {
  return pathname === TICKETING_TOUR_SAMPLE_DETAIL_PATH;
}

const SAMPLE_UPDATED_AT = "2026-08-28T08:15:00.000Z";

export const ticketingTourSampleListRow: TicketingTicketListRow = {
  id: TICKETING_TOUR_SAMPLE_TICKET_ID,
  ticket_number: "TKT-TOUR-0001",
  title: "Sample: VPN login fails after password reset",
  status: "open",
  priority: "high",
  category: "access",
  source_module: "manual",
  source_label: "Tour sample",
  requester: {
    id: "tour-sample-requester",
    name: "Alex Requester",
    email: "alex.requester@example.com",
  },
  assignee: {
    id: "tour-sample-assignee",
    name: "Sam Support",
    email: "sam.support@example.com",
  },
  created_at: "2026-08-28T07:40:00.000Z",
  updated_at: SAMPLE_UPDATED_AT,
  sla_due_at: "2026-08-28T15:40:00.000Z",
  sla_status: "at_risk",
};

export const ticketingTourSampleDetail: TicketingTicketDetail = {
  ...ticketingTourSampleListRow,
  description:
    "After resetting my password I cannot connect to corporate VPN. Error: authentication failed.\n\nThis is sample tour content — nothing is saved.",
  source_reference_type: null,
  source_reference_id: null,
  resolved_at: null,
  closed_at: null,
  can_reopen: false,
  comments: [
    {
      id: "tour-sample-comment-1",
      body: "Thanks — IT is checking MFA sync for your account.",
      is_internal: false,
      author: { id: "tour-sample-assignee", name: "Sam Support" },
      created_at: "2026-08-28T08:00:00.000Z",
    },
    {
      id: "tour-sample-comment-2",
      body: "Internal: verify Entra conditional access policy CA-12.",
      is_internal: true,
      author: { id: "tour-sample-assignee", name: "Sam Support" },
      created_at: "2026-08-28T08:05:00.000Z",
    },
  ],
  attachments: [
    {
      id: "tour-sample-attachment-1",
      file_name: "vpn-error-screenshot.png",
      mime_type: "image/png",
      size_bytes: 186_432,
      uploaded_by: { id: "tour-sample-requester", name: "Alex Requester" },
      created_at: "2026-08-28T07:42:00.000Z",
    },
  ],
  links: [],
};
