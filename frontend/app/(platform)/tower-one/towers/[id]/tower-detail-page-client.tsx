"use client";

import Link from "next/link";

import { PermissionGate } from "@/components/layout/permission-gate";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { Button } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { useTowerOneTowerDetail } from "@/hooks/use-tower-one-tower-detail";
import { permissions } from "@/lib/rbac/permissions";

export function TowerDetailPageClient({ towerId }: { towerId: string }) {
  const { data, isFetching, isError, refetch } = useTowerOneTowerDetail(towerId);
  const siteLabel = data?.site ? `${data.site.site_code} · ${data.site.name}` : "Tower";
  const sourceLabel = data?.site ? `${data.site.site_code} · ${data.tower_type}` : data?.tower_type ?? "Tower";

  return (
    <PermissionGate requiredPermissions={[permissions.towerOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground capitalize">
              {data?.tower_type ?? "Tower"}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">{siteLabel}</p>
            <p className="mt-2 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/tower-one/towers">
                All towers
              </Link>
              {data?.site ? (
                <>
                  {" · "}
                  <Link
                    className="text-primary underline-offset-4 hover:underline"
                    href={`/sites/${data.site.id}`}
                  >
                    Site {data.site.site_code}
                  </Link>
                </>
              ) : null}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {data ? (
              <RaiseTicketButton
                prefill={{
                  title: `Tower issue — ${sourceLabel}`,
                  description: [
                    `Tower type: ${data.tower_type}`,
                    `Site: ${siteLabel}`,
                    `Height: ${data.height_m ?? "—"} m`,
                    `Capacity: ${data.capacity_kg ?? "—"} kg`,
                    `Status: ${data.status}`,
                    `Link: /tower-one/towers/${towerId}`,
                  ].join("\n"),
                  source_module: "tower_one",
                  source_reference_type: "tower",
                  source_reference_id: towerId,
                  source_label: sourceLabel,
                  links: [
                    {
                      link_module: "tower_one",
                      link_type: "tower",
                      link_id: towerId,
                      link_label: sourceLabel,
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
          <Metric label="Height (m)" value={data?.height_m ?? "—"} />
          <Metric label="Capacity (kg)" value={data?.capacity_kg ?? "—"} />
          <Metric label="Max tenants" value={data?.max_tenants != null ? String(data.max_tenants) : "—"} />
          <Metric label="Status" value={data?.status ?? "—"} />
        </div>

        <TicketingRelatedTickets sourceModule="tower_one" sourceReferenceId={towerId} />

        {isFetching && !data ? <RefreshingHint label="Loading" /> : null}
        {isError ? <p className="text-sm text-destructive">Could not load tower.</p> : null}
      </div>
    </PermissionGate>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-lg font-medium capitalize text-foreground">{value}</p>
    </div>
  );
}
