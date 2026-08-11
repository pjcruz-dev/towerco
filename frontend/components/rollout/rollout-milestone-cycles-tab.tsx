"use client";

import { LayoutGrid, Table2 } from "lucide-react";

import { RolloutMilestoneCycleLabel } from "@/components/rollout/rollout-milestone-cycle-label";
import { rolloutMilestoneCyclesTableColumns } from "@/components/rollout/rollout-milestone-cycles-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { useIsMobile } from "@/hooks/use-mobile";
import { useLocalStorageState } from "@/hooks/use-local-storage-state";
import {
  MILESTONE_MOBILE_DEFAULT_ZOOM,
  MILESTONE_VIEW_MODES,
  MILESTONE_VIEW_STORAGE_KEY,
  type MilestoneViewMode,
} from "@/lib/rollout/milestone-preferences";
import type { RolloutDetail, RolloutMilestoneCycle, RolloutMilestoneCycleSummary } from "@/modules/rollout/types";

import { RolloutMilestoneCyclesGrid } from "./rollout-milestone-cycles-grid";

type Props = {
  detail: RolloutDetail | undefined;
};

export function RolloutMilestoneCyclesTab({ detail }: Props) {
  const isMobile = useIsMobile();
  const [view, setView] = useLocalStorageState<MilestoneViewMode>(
    MILESTONE_VIEW_STORAGE_KEY,
    "table",
    MILESTONE_VIEW_MODES,
  );
  const cycles = detail?.milestone_cycles ?? [];
  const summary = detail?.milestone_cycles_summary;

  const displayView: MilestoneViewMode = isMobile ? "table" : view;

  if (cycles.length === 0) {
    return (
      <div className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground shadow-sm">
        No milestone checkpoints for this rollout.
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {summary ? <MilestoneSummary summary={summary} /> : null}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-muted-foreground">
          {displayView === "table" ? (
            <>
              {isMobile
                ? "Mobile-friendly milestone list — tap underlined acronyms for definitions."
                : "Tabular milestone checkpoints — tap or hover underlined acronyms for definitions."}
            </>
          ) : (
            "Timeline grid — scroll horizontally on long programs. Tap or hover milestone names for acronym help."
          )}
        </p>
        <ViewToggle view={view} onChange={setView} showGrid={!isMobile} />
      </div>

      {displayView === "table" ? (
        <>
          <MilestoneMobileList cycles={cycles} />
          <MilestoneDesktopTable cycles={cycles} />
        </>
      ) : detail ? (
        <RolloutMilestoneCyclesGrid detail={detail} defaultZoom={isMobile ? MILESTONE_MOBILE_DEFAULT_ZOOM : undefined} />
      ) : null}
    </div>
  );
}

function ViewToggle({
  view,
  onChange,
  showGrid,
}: {
  view: MilestoneViewMode;
  onChange: (view: MilestoneViewMode) => void;
  showGrid: boolean;
}) {
  return (
    <div className="inline-flex w-full rounded-lg border border-border bg-muted/30 p-0.5 sm:w-auto">
      <button
        type="button"
        onClick={() => onChange("table")}
        className={`inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors sm:min-h-0 sm:flex-none sm:py-1.5 sm:text-xs ${
          view === "table" ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"
        }`}
      >
        <Table2 className="h-4 w-4 sm:h-3.5 sm:w-3.5" />
        Table
      </button>
      {showGrid ? (
        <button
          type="button"
          onClick={() => onChange("grid")}
          className={`inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors sm:min-h-0 sm:flex-none sm:py-1.5 sm:text-xs ${
            view === "grid" ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"
          }`}
        >
          <LayoutGrid className="h-4 w-4 sm:h-3.5 sm:w-3.5" />
          Grid
        </button>
      ) : null}
    </div>
  );
}

function MilestoneMobileList({ cycles }: { cycles: RolloutMilestoneCycle[] }) {
  return (
    <ul className="space-y-2 md:hidden">
      {cycles.map((cycle) => (
        <li key={cycle.phase_key} className="rounded-xl border border-border bg-card p-3 shadow-sm">
          <p className="text-sm font-medium leading-snug text-foreground">
            <RolloutMilestoneCycleLabel cycle={cycle} />
          </p>
          <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
            <div>
              <dt className="text-muted-foreground">Anchor</dt>
              <dd className="font-medium capitalize text-foreground">{formatAnchor(cycle.anchor)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Target WD</dt>
              <dd className="font-medium tabular-nums text-foreground">{cycle.target_working_days}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Target date</dt>
              <dd className="font-mono text-foreground">{cycle.target_date ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Variance</dt>
              <dd className="font-mono text-foreground">
                {cycle.variance_wd !== null && cycle.variance_wd !== undefined ? `+${cycle.variance_wd} wd` : "—"}
              </dd>
            </div>
            <div className="col-span-2">
              <dt className="text-muted-foreground">Status</dt>
              <dd className="mt-0.5">
                <MilestoneStatusBadge status={cycle.status} />
              </dd>
            </div>
          </dl>
        </li>
      ))}
    </ul>
  );
}

function MilestoneDesktopTable({ cycles }: { cycles: RolloutMilestoneCycle[] }) {
  return (
    <div className="hidden overflow-hidden rounded-xl border border-border bg-card shadow-sm md:block">
      <RegistryDataTableView
        columns={rolloutMilestoneCyclesTableColumns}
        data={cycles}
        getRowId={(row) => row.phase_key}
        isEmpty={cycles.length === 0}
        enableColumnVisibility
        columnVisibilityStorageKey="toweros.table.columns.project-one.rollout-milestone-cycles"
        scrollClassName="max-h-none [-webkit-overflow-scrolling:touch]"
      />
    </div>
  );
}

function MilestoneSummary({ summary }: { summary: RolloutMilestoneCycleSummary }) {
  return (
    <div className="space-y-3">
      <p className="text-xs text-muted-foreground">
        SLA schedule health based on target working-day dates. Gate progress and permit checkpoints are on the
        Timeline workspace tab.
      </p>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <SummaryCard label="Total milestones" value={String(summary.total)} />
        <SummaryCard label="On track" value={String(summary.on_track)} />
        <SummaryCard label="At risk" value={String(summary.at_risk)} tone="warning" />
        <SummaryCard label="Overdue" value={String(summary.overdue)} tone="danger" />
        <SummaryCard label="SLA milestones met" value={`${summary.progress_pct}%`} />
      </div>
    </div>
  );
}

function SummaryCard({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone?: "warning" | "danger";
}) {
  const valueTone =
    tone === "danger"
      ? "text-red-600 dark:text-red-400"
      : tone === "warning"
        ? "text-amber-600 dark:text-amber-400"
        : "text-foreground";

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className={`mt-1 text-base font-medium tabular-nums ${valueTone}`}>{value}</p>
    </div>
  );
}

function MilestoneStatusBadge({ status }: { status: RolloutMilestoneCycle["status"] }) {
  const tone =
    status === "completed"
      ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200"
      : status === "active"
        ? "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        : status === "at_risk"
          ? "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
          : status === "overdue"
            ? "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200"
            : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200";

  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${tone}`}>
      {status.replaceAll("_", " ")}
    </span>
  );
}

function formatAnchor(anchor: string): string {
  return anchor === "day_one" ? "Day 1" : anchor.replaceAll("_", " ");
}
