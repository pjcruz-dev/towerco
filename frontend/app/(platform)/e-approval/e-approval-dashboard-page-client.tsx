"use client";

import Link from "next/link";
import { useMemo, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import {
  ArrowRight,
  ClipboardCheck,
  FilePlus2,
  FileSpreadsheet,
  FileStack,
  FileText,
  Inbox,
} from "lucide-react";

import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalHelpEntryActions } from "@/components/help/e-approval-help-entry-actions";
import { EApprovalTourCompleteAnchor, EApprovalTourOverviewQueueFixtures } from "@/components/help/e-approval-tour-fixtures";
import { EApprovalTourSoftPrompt } from "@/components/help/e-approval-tour-soft-prompt";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { Spinner } from "@/components/ui/spinner";
import { useEApprovalDashboard } from "@/hooks/use-e-approval-dashboard";
import { usePermission } from "@/hooks/use-permission";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { formatEApprovalStatusLabel } from "@/modules/e-approval/status-display";
import type {
  EApprovalDashboardKpi,
  EApprovalDashboardQueueItem,
} from "@/modules/e-approval/types";

const toneClass: Record<NonNullable<EApprovalDashboardKpi["tone"]>, string> = {
  neutral: "text-muted-foreground",
  success: "text-emerald-600 dark:text-emerald-400",
  warning: "text-amber-600 dark:text-amber-400",
  danger: "text-red-600 dark:text-red-400",
};

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime())
    ? iso
    : d.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

function OverviewKpiStrip({ items }: { items: EApprovalDashboardKpi[] }) {
  if (items.length === 0) {
    return (
      <section
        data-help="ea-overview-kpis"
        className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-sm text-muted-foreground"
      >
        Status cards appear here once approvals and submissions exist for your account.
      </section>
    );
  }

  return (
    <section
      data-help="ea-overview-kpis"
      className={cn(
        "grid gap-3 sm:grid-cols-2",
        items.length >= 5 ? "xl:grid-cols-5" : items.length === 4 ? "xl:grid-cols-4" : "xl:grid-cols-3",
      )}
    >
      {items.map((item) => {
        const body = (
          <>
            <p className="text-xs font-medium text-muted-foreground">{item.label}</p>
            <p className="mt-2 text-2xl font-semibold text-foreground">{item.value}</p>
            {item.change ? (
              <p className={cn("mt-2 text-xs", toneClass[item.tone ?? "neutral"])}>{item.change}</p>
            ) : null}
          </>
        );

        if (item.href) {
          return (
            <Link
              key={item.key}
              href={item.href}
              className="rounded-xl border border-border bg-card p-4 shadow-sm transition-colors hover:border-primary/30 hover:bg-muted/30"
            >
              {body}
            </Link>
          );
        }

        return (
          <article key={item.key} className="rounded-xl border border-border bg-card p-4 shadow-sm">
            {body}
          </article>
        );
      })}
    </section>
  );
}

function QueueList({
  items,
  emptyMessage,
  metaLabel,
  tourFixture,
}: {
  items: EApprovalDashboardQueueItem[];
  emptyMessage: string;
  metaLabel: "requestor" | "updated";
  tourFixture?: "awaiting" | "attention";
}) {
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);

  if (items.length === 0) {
    if (tourActive && tourFixture) {
      return (
        <Suspense fallback={<p className="px-1 py-6 text-sm text-muted-foreground">{emptyMessage}</p>}>
          <EApprovalTourOverviewQueueFixtures variant={tourFixture} />
        </Suspense>
      );
    }
    return <p className="px-1 py-6 text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-border">
      {items.map((row) => (
        <li key={row.id}>
          <Link
            href={row.href}
            className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
          >
            <div className="min-w-0 space-y-0.5">
              <p className="truncate text-sm font-medium text-foreground">
                {row.document_no || "Untitled"}
                {row.form_name ? (
                  <span className="ml-2 font-normal text-muted-foreground">{row.form_name}</span>
                ) : null}
              </p>
              <p className="text-xs text-muted-foreground">
                {formatEApprovalStatusLabel(row.status)}
                {row.step_order != null ? ` · Step ${row.step_order}` : ""}
                {metaLabel === "requestor" && row.requestor_name
                  ? ` · ${row.requestor_name}`
                  : ` · ${formatWhen(row.waiting_since)}`}
              </p>
            </div>
            <ArrowRight className="mt-1 size-3.5 shrink-0 text-muted-foreground" aria-hidden />
          </Link>
        </li>
      ))}
    </ul>
  );
}

export function EApprovalDashboardPageClient() {
  return (
    <Suspense
      fallback={
        <div className="space-y-6">
          <DashboardContentSkeleton />
        </div>
      }
    >
      <EApprovalDashboardPageInner />
    </Suspense>
  );
}

function EApprovalDashboardPageInner() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useEApprovalDashboard();
  const showSkeleton = isFetching && isPlaceholderData;
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canManageForms = usePermission([permissions.eApprovalFormsManage]);
  const canAudit = usePermission([permissions.eApprovalAuditView]);

  const capabilities = data?.capabilities;
  const showApproveQueue = capabilities?.can_approve ?? canApprove;
  const showCreate = capabilities?.can_create ?? canCreate;
  const showForms = capabilities?.can_manage_forms ?? canManageForms;
  const showReports = capabilities?.can_audit ?? canAudit;

  const awaitingQueue = data?.queues?.awaiting_approval ?? [];
  const attentionQueue = data?.queues?.my_attention ?? [];
  const financeKpis = data?.finance_kpis ?? [];

  const shortcuts = useMemo(
    () =>
      [
        {
          href: "/e-approval/submissions",
          label: "Submissions",
          description: "Track your requests",
          icon: FileStack,
          show: true,
        },
        {
          href: "/e-approval/approvals?awaiting_me=1",
          label: "Approvals",
          description: "Decide on pending items",
          icon: Inbox,
          show: showApproveQueue,
        },
        {
          href: "/e-approval/forms",
          label: "Forms",
          description: "Templates and workflows",
          icon: FileText,
          show: showForms,
        },
        {
          href: "/e-approval/reports",
          label: "Reports",
          description: "Analytics and exports",
          icon: FileSpreadsheet,
          show: showReports,
        },
      ].filter((item) => item.show),
    [showApproveQueue, showForms, showReports],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <EApprovalPageHeader
          title="E-Approval"
          description={data?.message ?? "Your inbox for approvals, returns, and open requests."}
          actions={
            <div data-help="ea-overview-quick-actions" className="flex flex-wrap items-center gap-2">
              <EApprovalHelpEntryActions />
              <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
                {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
              {showApproveQueue ? (
                <Button size="sm" variant="outline" render={<Link href="/e-approval/approvals?awaiting_me=1" />}>
                  <ClipboardCheck className="mr-1.5 size-3.5" aria-hidden />
                  Approvals
                </Button>
              ) : null}
              {showCreate ? (
                <Button size="sm" render={<Link href="/e-approval/submissions/new" />}>
                  <FilePlus2 className="mr-1.5 size-3.5" aria-hidden />
                  New submission
                </Button>
              ) : null}
            </div>
          }
        />

        <EApprovalTourSoftPrompt />
        <Suspense fallback={null}>
          <EApprovalTourCompleteAnchor />
        </Suspense>

        {showSkeleton ? <DashboardContentSkeleton /> : null}

        {isError ? <p className="text-sm text-destructive">Could not load E-Approval overview.</p> : null}

        {!showSkeleton ? (
          <>
            <OverviewKpiStrip items={data?.kpis ?? []} />

            <div className={cn("grid gap-4", showApproveQueue ? "lg:grid-cols-2" : "lg:grid-cols-1")}>
              {showApproveQueue ? (
                <EApprovalSectionCard
                  dataHelp="ea-overview-awaiting"
                  title="Needs my approval"
                  description="Oldest pending items assigned to you."
                  actions={
                    <Link
                      href="/e-approval/approvals?awaiting_me=1"
                      className="text-xs font-medium text-primary hover:underline"
                    >
                      View all
                    </Link>
                  }
                >
                  <QueueList
                    items={awaitingQueue}
                    emptyMessage="Nothing waiting on you right now. New items show up when someone submits work to you."
                    metaLabel="requestor"
                    tourFixture="awaiting"
                  />
                </EApprovalSectionCard>
              ) : (
                <div
                  data-help="ea-overview-awaiting"
                  className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-sm text-muted-foreground"
                >
                  Approver inbox appears here when your role can approve requests.
                </div>
              )}

              <EApprovalSectionCard
                dataHelp="ea-overview-attention"
                title="Needs my attention"
                description="Returned and draft submissions you own."
                actions={
                  <Link
                    href="/e-approval/submissions?mine=1"
                    className="text-xs font-medium text-primary hover:underline"
                  >
                    View mine
                  </Link>
                }
              >
                <QueueList
                  items={attentionQueue}
                  emptyMessage="No drafts or returns yet. Items you own that need work will list here."
                  metaLabel="updated"
                  tourFixture="attention"
                />
              </EApprovalSectionCard>
            </div>

            {financeKpis.length > 0 ? (
              <EApprovalSectionCard
                title="Finance & procurement"
                description="Open cash advances and PR follow-ups."
              >
                <OverviewKpiStrip items={financeKpis} />
              </EApprovalSectionCard>
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {shortcuts.map((tile) => {
                const Icon = tile.icon;
                return (
                  <Link
                    key={tile.href}
                    href={tile.href}
                    className="group flex items-start gap-3 rounded-xl border border-border bg-card p-3.5 shadow-sm transition-colors hover:border-primary/30 hover:bg-muted/30"
                  >
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                      <Icon className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-foreground">{tile.label}</p>
                      <p className="text-xs text-muted-foreground">{tile.description}</p>
                    </div>
                    <ArrowRight className="ml-auto size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                  </Link>
                );
              })}
            </div>

            {showReports ? (
              <p className="text-xs text-muted-foreground">
                Need trends or CSV/Excel extracts?{" "}
                <Link href="/e-approval/reports" className="font-medium text-primary hover:underline">
                  Open Reports
                </Link>
                .
              </p>
            ) : null}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
