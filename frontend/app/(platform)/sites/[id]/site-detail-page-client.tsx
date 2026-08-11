"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { SiteDocumentsPanel } from "@/components/documents/site-documents-panel";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { Button } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { useSiteDetail } from "@/hooks/use-site-detail";
import { usePermission } from "@/hooks/use-permission";
import { isTenantModuleEnabled, resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";
import { permissions } from "@/lib/rbac/permissions";

export function SiteDetailPageClient({ siteId }: { siteId: string }) {
  const [deepLinkDocumentId, setDeepLinkDocumentId] = useState<string | null>(null);
  const { data, isFetching, isError, refetch } = useSiteDetail(siteId);

  const canViewDocuments = usePermission([permissions.documentsView]);
  const user = useAuthStore((s) => s.user);
  const activeTenantId = useAuthStore((s) => s.activeTenantId);
  const enabledModules = resolveEnabledModulesForUser(user, activeTenantId);
  const documentsModuleOn = isTenantModuleEnabled(enabledModules, "documents");

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setDeepLinkDocumentId(params.get("document"));
    if (params.get("panel") !== "documents" || !canViewDocuments || !documentsModuleOn) {
      return;
    }
    const timer = window.setTimeout(() => {
      document.getElementById("site-documents-panel")?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }, 150);
    return () => window.clearTimeout(timer);
  }, [canViewDocuments, documentsModuleOn]);

  return (
    <PermissionGate requiredPermissions={[permissions.sitesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              {data?.name ?? "Site"}
            </h1>
            <p className="mt-1 font-mono text-sm text-muted-foreground">{data?.site_code ?? "—"}</p>
            <p className="mt-2 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
                All sites
              </Link>
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {data ? (
              <RaiseTicketButton
                prefill={{
                  title: `Site issue — ${data.site_code}`,
                  description: [
                    `Site: ${data.site_code} — ${data.name}`,
                    `Type: ${data.type ?? "—"}`,
                    `Status: ${data.status}`,
                    `Coordinates: ${data.latitude ?? "—"}, ${data.longitude ?? "—"}`,
                    `Link: /sites/${siteId}`,
                  ].join("\n"),
                  source_module: "sites",
                  source_reference_type: "site",
                  source_reference_id: siteId,
                  source_label: data.site_code,
                  links: [
                    {
                      link_module: "sites",
                      link_type: "site",
                      link_id: siteId,
                      link_label: data.site_code,
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
          <Metric label="Type" value={data?.type ?? "—"} />
          <Metric label="Status" value={data?.status ?? "—"} />
          <Metric label="Towers" value={String(data?.towers_count ?? 0)} />
          <Metric label="Projects" value={String(data?.projects_count ?? 0)} />
        </div>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm text-sm">
          <dl className="grid gap-4 sm:grid-cols-2">
            <div>
              <dt className="text-xs text-muted-foreground">Latitude</dt>
              <dd className="mt-0.5">{data?.latitude ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs text-muted-foreground">Longitude</dt>
              <dd className="mt-0.5">{data?.longitude ?? "—"}</dd>
            </div>
          </dl>
        </section>

        {canViewDocuments && documentsModuleOn ? (
          <SiteDocumentsPanel
            siteId={siteId}
            siteCode={data?.site_code}
            initialDocumentId={deepLinkDocumentId}
          />
        ) : null}

        <TicketingRelatedTickets sourceModule="sites" sourceReferenceId={siteId} />

        {isFetching && !data ? <RefreshingHint label="Loading" /> : null}
        {isError ? <p className="text-sm text-destructive">Could not load site.</p> : null}
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
