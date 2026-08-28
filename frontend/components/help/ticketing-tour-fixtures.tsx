"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { formatTicketingDate } from "@/components/ticketing/ticketing-utils";
import { buildTourSearchParams } from "@/lib/help/e-approval-live-tour";
import {
  TICKETING_TOUR_SAMPLE_DETAIL_PATH,
  isTicketingTourActive,
  ticketingTourSampleListRow,
} from "@/lib/help/ticketing-tour-fixtures";
import { TICKETING_LIVE_TOUR_ID } from "@/lib/help/ticketing-live-tour";

function tourQuerySuffix(searchParams: URLSearchParams): string {
  const step = Number.parseInt(searchParams.get("tourStep") ?? "0", 10);
  const params = buildTourSearchParams(
    TICKETING_LIVE_TOUR_ID,
    Number.isFinite(step) ? step : 0,
    {
      id: "_",
      path: "/",
      target: "_",
      title: "",
      body: "",
    },
    searchParams,
  );
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

export function TicketingTourSampleNotice() {
  return (
    <p className="rounded-lg border border-dashed border-sky-300 bg-sky-50/80 px-3 py-2 text-xs text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
      Sample UI for this tour only — nothing is saved. After you finish or skip, lists return to real data.
    </p>
  );
}

/** Final tour step anchor on Overview (ephemeral while tour is active). */
export function TicketingTourCompleteAnchor() {
  const searchParams = useSearchParams();
  if (!isTicketingTourActive(searchParams)) {
    return null;
  }

  return (
    <aside
      data-help="tk-tour-complete"
      className="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30"
      aria-label="Tour complete"
    >
      <p className="text-sm font-medium text-foreground">Ticketing tour finished</p>
      <p className="mt-1 text-xs text-muted-foreground">
        You covered the ticket queue and the steps available for your role. Sample cards disappear when you
        finish. Click Finish tour to close.
      </p>
    </aside>
  );
}

/** Recent-tickets row while the live tour is active (ephemeral). */
export function TicketingTourOverviewRecentFixtures() {
  const searchParams = useSearchParams();
  if (!isTicketingTourActive(searchParams)) {
    return null;
  }

  const ticket = ticketingTourSampleListRow;
  const href = `${TICKETING_TOUR_SAMPLE_DETAIL_PATH}${tourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  return (
    <tr className="border-b border-dashed border-sky-300/80 bg-sky-50/40 last:border-0 hover:bg-sky-50/70 dark:border-sky-800 dark:bg-sky-950/20">
      <td className="px-4 py-2.5 font-mono text-xs">
        <Link href={href} className="text-primary hover:underline">
          {ticket.ticket_number}
        </Link>
      </td>
      <td className="max-w-xs truncate px-4 py-2.5 text-foreground">{ticket.title}</td>
      <td className="px-4 py-2.5">
        <TicketingStatusBadge status={ticket.status} />
      </td>
      <td className="px-4 py-2.5">
        <TicketingPriorityBadge priority={ticket.priority} />
      </td>
      <td className="hidden px-4 py-2.5 text-xs text-muted-foreground md:table-cell">
        {formatTicketingDate(ticket.updated_at)}
      </td>
    </tr>
  );
}
