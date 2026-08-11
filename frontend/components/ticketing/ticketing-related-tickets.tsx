"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { fetchTicketingTickets } from "@/lib/api/modules/ticketing-api";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { isTenantModuleEnabled, resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";

type Props = {
  sourceModule: string;
  sourceReferenceId: string;
  title?: string;
  /**
   * When set, matches tickets sourced from this record OR linked to it (e.g. a PO shows
   * tickets raised from its child GRNs/invoices that link back to the PO). Overrides the
   * source-only filter above.
   */
  linkedModule?: string;
};

export function TicketingRelatedTickets({
  sourceModule,
  sourceReferenceId,
  title = "Related tickets",
  linkedModule,
}: Props) {
  const user = useAuthStore((s) => s.user);
  const activeTenantId = useAuthStore((s) => s.activeTenantId);
  const enabledModules = resolveEnabledModulesForUser(user, activeTenantId);
  const canView = usePermission([permissions.ticketingView]);
  const ticketingEnabled = isTenantModuleEnabled(enabledModules, "ticketing");

  const useLinked = Boolean(linkedModule);
  const ticketsQuery = useQuery({
    queryKey: [
      "ticketing",
      "related",
      useLinked ? "linked" : "source",
      linkedModule ?? sourceModule,
      sourceReferenceId,
    ],
    queryFn: () =>
      fetchTicketingTickets(
        useLinked
          ? {
              linked_module: linkedModule,
              linked_id: sourceReferenceId,
              per_page: 10,
            }
          : {
              source_module: sourceModule,
              source_reference_id: sourceReferenceId,
              per_page: 10,
            },
      ),
    enabled: ticketingEnabled && canView && Boolean(sourceReferenceId),
  });

  if (!ticketingEnabled || !canView) {
    return null;
  }

  const tickets = ticketsQuery.data?.data ?? [];

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h3 className="text-sm font-medium text-foreground">{title}</h3>
      {ticketsQuery.isLoading ? (
        <div className="mt-2">
          <RefreshingHint label="Loading tickets" />
        </div>
      ) : tickets.length === 0 ? (
        <p className="mt-2 text-xs text-muted-foreground">No tickets raised from this record yet.</p>
      ) : (
        <ul className="mt-3 space-y-2">
          {tickets.map((ticket) => (
            <li key={ticket.id}>
              <Link
                href={`/ticketing/tickets/${ticket.id}`}
                className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm hover:border-primary/30"
              >
                <span className="min-w-0 truncate font-medium text-foreground">
                  {ticket.ticket_number} — {ticket.title}
                </span>
                <TicketingStatusBadge status={ticket.status} />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
