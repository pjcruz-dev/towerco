"use client";

import { useQuery } from "@tanstack/react-query";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { fetchProcurementVendorInbox } from "@/lib/api/modules/procurement-vendor-inbox-public-api";

type Props = {
  accessToken: string;
};

export function ProcurementVendorInboxPageClient({ accessToken }: Props) {
  const query = useQuery({
    queryKey: ["procurement", "public", "vendor-inbox", accessToken],
    queryFn: () => fetchProcurementVendorInbox(accessToken),
  });

  const payload = query.data;

  return (
    <div className="mx-auto w-full max-w-3xl space-y-6">
      <header className="space-y-1">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Supplier portal</p>
        <h1 className="text-2xl font-semibold text-foreground">Your RFQ invitations</h1>
        {payload?.vendor.company_name ? (
          <p className="text-sm text-muted-foreground">{payload.vendor.company_name}</p>
        ) : null}
      </header>

      {query.isLoading ? <p className="text-sm text-muted-foreground">Loading invitations…</p> : null}
      {query.isError ? (
        <OperationalAlert level="error" title="Unable to open supplier inbox" description={getErrorMessage(query.error)} />
      ) : null}

      {payload ? (
        <section className="rounded-xl border border-border bg-card shadow-sm">
          {payload.items.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">No RFQ invitations at the moment.</p>
          ) : (
            <ul className="divide-y divide-border">
              {payload.items.map((item) => (
                <li key={item.invitation_id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                  <div>
                    <p className="text-sm font-medium">{item.title ?? item.document_no ?? "RFQ"}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {item.document_no ? `RFQ ${item.document_no}` : null}
                      {item.document_no && item.status_label ? " · " : null}
                      {item.status_label}
                      {item.bidding_closes_at ? ` · closes ${new Date(item.bidding_closes_at).toLocaleString()}` : null}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground capitalize">Invitation: {item.invitation_status}</p>
                  </div>
                  {item.can_quote && item.quote_url ? (
                    <Button size="sm" render={<a href={item.quote_url} target="_blank" rel="noopener noreferrer" />}>
                      Submit quotation
                    </Button>
                  ) : (
                    <span className="text-xs text-muted-foreground">Not accepting quotes</span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      ) : null}
    </div>
  );
}
