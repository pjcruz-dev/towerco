"use client";

import Link from "next/link";

import { PermissionGate } from "@/components/layout/permission-gate";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { Button } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { useAssetOneAssetDetail } from "@/hooks/use-asset-one-asset-detail";
import { permissions } from "@/lib/rbac/permissions";

export function AssetDetailPageClient({ assetId }: { assetId: string }) {
  const { data, isFetching, isError, refetch } = useAssetOneAssetDetail(assetId);

  return (
    <PermissionGate requiredPermissions={[permissions.assetOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">{data?.name ?? "Asset"}</h1>
            <p className="mt-1 font-mono text-sm text-muted-foreground">{data?.asset_code ?? "—"}</p>
            <p className="mt-2 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/asset-one/assets">
                All assets
              </Link>
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {data ? (
              <RaiseTicketButton
                prefill={{
                  title: `Asset issue — ${data.asset_code}`,
                  description: [
                    `Asset: ${data.asset_code} — ${data.name}`,
                    `Category: ${data.category}`,
                    `Status: ${data.status}`,
                    data.rfid_tag ? `RFID: ${data.rfid_tag}` : null,
                    data.location_type && data.location_id
                      ? `Location: ${data.location_type}:${data.location_id}`
                      : null,
                    `Link: /asset-one/assets/${assetId}`,
                  ]
                    .filter(Boolean)
                    .join("\n"),
                  source_module: "asset_one",
                  source_reference_type: "asset",
                  source_reference_id: assetId,
                  source_label: data.asset_code,
                  links: [
                    {
                      link_module: "asset_one",
                      link_type: "asset",
                      link_id: assetId,
                      link_label: data.asset_code,
                    },
                  ],
                }}
              />
            ) : null}
            <Button size="sm" variant="outline" onClick={() => refetch()}>
              Refresh
            </Button>
          </div>
        </header>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Metric label="Category" value={data?.category ?? "—"} />
          <Metric label="Status" value={data?.status ?? "—"} />
          <Metric label="RFID" value={data?.rfid_tag ?? "—"} mono />
          <Metric label="Warranty" value={data?.warranty_expiry ?? "—"} />
        </div>

        <TicketingRelatedTickets sourceModule="asset_one" sourceReferenceId={assetId} />

        {isFetching && !data ? <RefreshingHint label="Loading" /> : null}
        {isError ? <p className="text-sm text-destructive">Could not load asset.</p> : null}
      </div>
    </PermissionGate>
  );
}

function Metric({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className={`mt-1 text-lg font-medium capitalize text-foreground ${mono ? "font-mono text-sm" : ""}`}>
        {value}
      </p>
    </div>
  );
}
