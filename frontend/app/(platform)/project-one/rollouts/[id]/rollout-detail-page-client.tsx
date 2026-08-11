"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState, type ReactNode } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RolloutCancelSheet } from "@/components/rollout/rollout-cancel-sheet";
import { RolloutColocationTenantsPanel } from "@/components/rollout/rollout-colocation-tenants-panel";
import { RolloutMetadataEditSheet } from "@/components/rollout/rollout-metadata-edit-sheet";
import { RolloutMilestoneCyclesTab } from "@/components/rollout/rollout-milestone-cycles-tab";
import { RolloutPhaseTimeline } from "@/components/rollout/rollout-phase-timeline";
import { RolloutProfitabilityTab } from "@/components/rollout/rollout-profitability-tab";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { Button } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { useRolloutDrafts } from "@/hooks/use-rollout-drafts";
import { useRolloutRealtime } from "@/hooks/use-rollout-realtime";
import { rolloutBatchChildrenTableColumns } from "@/components/project-one/rollout-batch-children-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { usePermission } from "@/hooks/use-permission";
import { useRolloutDetail } from "@/hooks/use-rollout-detail";
import { permissions } from "@/lib/rbac/permissions";

type SecondaryView = "milestones" | "profitability";

export function RolloutDetailPageClient({ rolloutId }: { rolloutId: string }) {
  const searchParams = useSearchParams();
  const urlPhaseKey = searchParams.get("phase");

  const canManageRollout = usePermission([permissions.rolloutManage]);
  const canSaq = usePermission([permissions.saqManage]);
  const canCme = usePermission([permissions.cmeManage]);
  const canViewFinance = usePermission([
    permissions.financeView,
    permissions.financeViewDiscipline,
    permissions.tenantManage,
  ]);
  const canEditFinance = usePermission([permissions.financeEdit, permissions.tenantManage]);

  const [secondaryView, setSecondaryView] = useState<SecondaryView | null>(null);
  const [showEditMetadata, setShowEditMetadata] = useState(false);
  const [showCancel, setShowCancel] = useState(false);
  const { data, isFetching, isError, refetch } = useRolloutDetail(rolloutId);
  const { pendingCount, syncDrafts, isSyncing } = useRolloutDrafts(rolloutId);
  useRolloutRealtime(rolloutId);

  const canEditMetadata =
    canManageRollout &&
    data?.status !== "completed" &&
    data?.status !== "cancelled" &&
    !data?.is_batch;

  const canCancelRollout =
    canManageRollout &&
    data?.status !== "completed" &&
    data?.status !== "cancelled" &&
    !data?.is_batch;

  return (
    <PermissionGate requiredPermissions={[permissions.rolloutView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              {data?.rollout_ref ?? "Rollout"}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              {data?.search_ring_name ?? "—"} · {data?.mno?.toUpperCase()} · {data?.project_type?.toUpperCase()}
              {data?.region ? ` · ${data.region.toUpperCase()}` : ""}
            </p>
            {data?.sla_holiday_scope ? (
              <p className="mt-1 text-xs text-muted-foreground">
                <AcronymLabel term="SLA">SLA</AcronymLabel> holiday scope:{" "}
                <span className="font-medium text-foreground">{data.sla_holiday_scope}</span>
              </p>
            ) : null}
            {data?.status === "cancelled" && data.cancellation_reason ? (
              <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                Cancelled: {data.cancellation_reason}
              </p>
            ) : null}
            <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
                All rollouts
              </Link>
              {data?.site ? (
                <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
                  Site {data.site.site_code}
                </Link>
              ) : null}
              {data?.project ? (
                <>
                  {" · "}
                  <Link
                    className="text-primary underline-offset-4 hover:underline"
                    href={`/project-one/projects/${data.project.id}`}
                  >
                    Project {data.project.name}
                  </Link>
                </>
              ) : null}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {pendingCount > 0 ? (
              <Button
                size="sm"
                variant="outline"
                disabled={isSyncing}
                title="Upload offline field drafts saved on this device"
                onClick={() => {
                  void syncDrafts(rolloutId).then(() => refetch());
                }}
              >
                {isSyncing ? "Syncing…" : `Sync drafts (${pendingCount})`}
              </Button>
            ) : null}
            {canEditMetadata ? (
              <Button size="sm" variant="outline" onClick={() => setShowEditMetadata(true)}>
                Edit metadata
              </Button>
            ) : null}
            {canCancelRollout ? (
              <Button size="sm" variant="destructive" onClick={() => setShowCancel(true)}>
                Cancel rollout
              </Button>
            ) : null}
            {data ? (
              <RaiseTicketButton
                prefill={{
                  title: `Rollout issue — ${data.rollout_ref}`,
                  description: [
                    `Rollout: ${data.rollout_ref}`,
                    `Status: ${data.status}`,
                    data.project ? `Project: ${data.project.name}` : null,
                    data.site ? `Site: ${data.site.site_code}` : null,
                    `Link: /project-one/rollouts/${rolloutId}`,
                  ]
                    .filter(Boolean)
                    .join("\n"),
                  source_module: "project_one",
                  source_reference_type: "rollout",
                  source_reference_id: rolloutId,
                  source_label: data.rollout_ref,
                  links: [
                    {
                      link_module: "project_one",
                      link_type: "rollout",
                      link_id: rolloutId,
                      link_label: data.rollout_ref,
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

        <RolloutMetadataEditSheet
          rolloutId={rolloutId}
          detail={data}
          open={showEditMetadata}
          onOpenChange={setShowEditMetadata}
        />
        <RolloutCancelSheet rolloutId={rolloutId} detail={data} open={showCancel} onOpenChange={setShowCancel} />

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard label="Status" value={data?.status?.replaceAll("_", " ") ?? "—"} />
          <MetricCard
            label={<AcronymLabel term="TCO ID">TCO Site ID</AcronymLabel>}
            value={data?.tco_site_id ?? "Pending selection"}
            mono
          />
          <MetricCard
            label={<AcronymLabel term="SLA">SLA remaining (wd)</AcronymLabel>}
            value={
              data?.sla_working_days_remaining !== null && data?.sla_working_days_remaining !== undefined
                ? String(data.sla_working_days_remaining)
                : "—"
            }
          />
          <MetricCard
            label={<AcronymLabel term="RFI / RFTI">Target RFI</AcronymLabel>}
            value={data?.target_rfi_working_date ?? "Set Day-1"}
          />
          {data?.actual_rfi_date ? (
            <MetricCard label={<AcronymLabel term="RFI / RFTI">Actual RFI</AcronymLabel>} value={data.actual_rfi_date} mono />
          ) : null}
        </div>

        <TicketingRelatedTickets sourceModule="project_one" sourceReferenceId={rolloutId} />

        {data?.is_batch ? (
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <RegistryDataTableView
              columns={rolloutBatchChildrenTableColumns}
              data={data.batch_children ?? []}
              getRowId={(row) => row.id}
              isEmpty={(data.batch_children ?? []).length === 0}
              emptyMessage="No child rollouts in this batch."
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.project-one.rollout-detail.batch-children"
              scrollClassName="max-h-none"
            />
          </div>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <Button
                size="sm"
                variant={secondaryView === null ? "default" : "outline"}
                onClick={() => setSecondaryView(null)}
              >
                Timeline workspace
              </Button>
              <Button
                size="sm"
                variant={secondaryView === "milestones" ? "default" : "outline"}
                onClick={() => setSecondaryView("milestones")}
              >
                Milestones
              </Button>
              {canViewFinance ? (
                <Button
                  size="sm"
                  variant={secondaryView === "profitability" ? "default" : "outline"}
                  onClick={() => setSecondaryView("profitability")}
                >
                  Profitability
                </Button>
              ) : null}
            </div>

            {secondaryView === null && data ? (
              <div className="space-y-4">
                <RolloutColocationTenantsPanel detail={data} />
                <RolloutPhaseTimeline
                  rolloutId={rolloutId}
                  detail={data}
                  canManageRollout={canManageRollout}
                  canSaq={canSaq}
                  canCme={canCme}
                  initialPhaseKey={urlPhaseKey}
                />
              </div>
            ) : null}

            {secondaryView === "milestones" ? <RolloutMilestoneCyclesTab detail={data} /> : null}
            {secondaryView === "profitability" ? (
              <RolloutProfitabilityTab rolloutId={rolloutId} canView={canViewFinance} canEdit={canEditFinance} />
            ) : null}
          </>
        )}

        {isFetching ? <RefreshingHint label="Syncing rollout" /> : null}
        {isError ? (
          <p className="text-xs text-red-600 dark:text-red-400">Unable to load rollout detail.</p>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function MetricCard({ label, value, mono }: { label: ReactNode; value: string; mono?: boolean }) {
  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className={`mt-1 text-base font-medium capitalize text-foreground ${mono ? "font-mono text-sm" : ""}`}>
        {value}
      </p>
    </div>
  );
}
