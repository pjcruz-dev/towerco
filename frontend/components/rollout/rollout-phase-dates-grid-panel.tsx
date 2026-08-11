"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { getErrorMessage } from "@/lib/api/error";
import {
  bulkBackfillRolloutPhaseDatesGrid,
  fetchRolloutPlaybookStatus,
} from "@/lib/api/modules/rollout-api";
import { mergePlaybookPhaseCatalog } from "@/lib/rollout/playbook-phase-catalog";
import type { RolloutListRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

const DEFAULT_VISIBLE_PHASES = ["endorsement"];

type Props = {
  selectedRows: RolloutListRow[];
  onSuccess: () => void;
  onCancel: () => void;
};

export function RolloutPhaseDatesGridPanel({ selectedRows, onSuccess, onCancel }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [visiblePhaseKeys, setVisiblePhaseKeys] = useState<string[]>(DEFAULT_VISIBLE_PHASES);
  const [grid, setGrid] = useState<Record<string, Record<string, string>>>({});
  const [markGatePassed, setMarkGatePassed] = useState(true);
  const [confirming, setConfirming] = useState(false);

  const playbookQuery = useQuery({
    queryKey: ["project-one", "rollout-playbook", "phase-dates-grid"],
    queryFn: fetchRolloutPlaybookStatus,
  });

  const phaseCatalog = useMemo(
    () => mergePlaybookPhaseCatalog(playbookQuery.data?.timeline_templates),
    [playbookQuery.data?.timeline_templates],
  );

  const visiblePhases = useMemo(
    () => phaseCatalog.filter((phase) => visiblePhaseKeys.includes(phase.phase_key)),
    [phaseCatalog, visiblePhaseKeys],
  );

  useEffect(() => {
    setGrid({});
    setVisiblePhaseKeys(DEFAULT_VISIBLE_PHASES);
    setMarkGatePassed(true);
    setConfirming(false);
  }, [selectedRows]);

  const rowsPayload = useMemo(() => {
    const rows: Array<{ rollout_id: string; phases: Array<{ phase_key: string; actual_date: string }> }> = [];

    for (const rollout of selectedRows) {
      const cellMap = grid[rollout.id] ?? {};
      const phases = Object.entries(cellMap)
        .filter(([, date]) => date.trim() !== "")
        .map(([phase_key, actual_date]) => ({ phase_key, actual_date }));

      if (phases.length > 0) {
        rows.push({ rollout_id: rollout.id, phases });
      }
    }

    return rows;
  }, [grid, selectedRows]);

  const filledCellCount = useMemo(
    () =>
      rowsPayload.reduce((sum, row) => sum + row.phases.length, 0),
    [rowsPayload],
  );

  const mutation = useMutation({
    mutationFn: () =>
      bulkBackfillRolloutPhaseDatesGrid({
        rows: rowsPayload,
        mark_gate_passed: markGatePassed,
        backfill: true,
      }),
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      await queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });

      push({
        level: result.updated > 0 ? "success" : "warning",
        title: "Grid backfill complete",
        message: `${result.phases_applied} cells updated on ${result.updated} rollout${result.updated === 1 ? "" : "s"}${result.failed > 0 ? ` · ${result.failed} skipped` : ""}.`,
      });

      onSuccess();
    },
    onError: (error) => {
      push({ level: "error", title: "Grid backfill failed", message: getErrorMessage(error) });
    },
  });

  function togglePhaseColumn(phaseKey: string) {
    setVisiblePhaseKeys((current) =>
      current.includes(phaseKey) ? current.filter((key) => key !== phaseKey) : [...current, phaseKey],
    );
  }

  function setCell(rolloutId: string, phaseKey: string, value: string) {
    setGrid((prev) => ({
      ...prev,
      [rolloutId]: {
        ...(prev[rolloutId] ?? {}),
        [phaseKey]: value,
      },
    }));
  }

  function fillColumn(phaseKey: string, value: string) {
    setGrid((prev) => {
      const next = { ...prev };
      for (const row of selectedRows) {
        next[row.id] = { ...(next[row.id] ?? {}), [phaseKey]: value };
      }
      return next;
    });
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="flex-1 space-y-3 overflow-y-auto px-4 py-2">
        <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-muted-foreground">
          <p className="font-medium text-foreground">Per-rollout grid</p>
          <p className="mt-1">
            Each row is one rollout (e.g. <span className="font-mono">RP-2026-GLO-EZVL</span>). Fill only the cells
            you need — leave blank to skip. Add phase columns below.
          </p>
        </div>

        <label className="flex cursor-pointer items-start gap-2 text-sm">
          <Checkbox
            className="mt-0.5"
            checked={markGatePassed}
            onCheckedChange={(v) => setMarkGatePassed(v === true)}
          />
          <span>
            <span className="font-medium text-foreground">Mark gate Passed</span>
            <span className="mt-0.5 block text-xs text-muted-foreground">Recommended for timeline Actual date.</span>
          </span>
        </label>

        <div>
          <p className="mb-2 text-xs font-medium text-muted-foreground">Phase columns</p>
          <div className="flex flex-wrap gap-1.5">
            {phaseCatalog.map((phase) => {
              const on = visiblePhaseKeys.includes(phase.phase_key);
              return (
                <button
                  key={phase.phase_key}
                  type="button"
                  onClick={() => togglePhaseColumn(phase.phase_key)}
                  className={cn(
                    "rounded-md border px-2 py-1 text-[11px] font-medium transition-colors",
                    on
                      ? "border-primary bg-primary/10 text-primary"
                      : "border-border bg-muted/30 text-muted-foreground hover:text-foreground",
                  )}
                >
                  {phase.label}
                </button>
              );
            })}
          </div>
        </div>

        {visiblePhases.length === 0 ? (
          <p className="text-sm text-muted-foreground">Select at least one phase column.</p>
        ) : (
          <div className="max-h-[min(60vh,520px)] overflow-auto rounded-lg border border-border">
            <table className="min-w-max border-collapse text-left text-xs">
              <thead className="sticky top-0 z-10 bg-muted/80 text-muted-foreground backdrop-blur-sm">
                <tr>
                  <th className="sticky left-0 z-20 min-w-[128px] border-b border-r border-border bg-muted/95 px-2 py-2 font-medium">
                    Rollout
                  </th>
                  {visiblePhases.map((phase) => (
                    <th
                      key={phase.phase_key}
                      className="min-w-[118px] border-b border-border px-1.5 py-2 font-medium align-bottom"
                    >
                      <div className="space-y-1" title={`Fill column: ${phase.label}`}>
                        <MilestonePhaseLabel phaseKey={phase.phase_key} label={phase.label} />
                        <DatePicker
                          value=""
                          onChange={(value) => {
                            if (value) {
                              fillColumn(phase.phase_key, value);
                            }
                          }}
                          className="h-7 text-[10px]"
                          placeholder="Fill col"
                        />
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {selectedRows.map((rollout) => (
                  <tr key={rollout.id} className="border-b border-border/60 hover:bg-muted/20">
                    <td className="sticky left-0 z-[1] border-r border-border bg-card px-2 py-1.5 font-mono text-[11px] font-medium text-foreground">
                      {rollout.rollout_ref}
                    </td>
                    {visiblePhases.map((phase) => (
                      <td key={`${rollout.id}-${phase.phase_key}`} className="px-2 py-1.5">
                        <DatePicker
                          className="h-8 min-w-[108px] text-xs"
                          value={grid[rollout.id]?.[phase.phase_key] ?? ""}
                          onChange={(value) => setCell(rollout.id, phase.phase_key, value)}
                        />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <p className="text-xs text-muted-foreground">
          {filledCellCount} cell{filledCellCount === 1 ? "" : "s"} ready · use the date in a column header to copy the
          same date down that column
        </p>

        {confirming ? (
          <p className="text-sm text-muted-foreground">
            Apply {filledCellCount} date{filledCellCount === 1 ? "" : "s"} across {rowsPayload.length} rollout
            {rowsPayload.length === 1 ? "" : "s"}?
          </p>
        ) : null}
      </div>

      <div className="flex flex-row justify-end gap-2 border-t border-border px-4 py-4">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        {!confirming ? (
          <Button type="button" disabled={filledCellCount === 0} onClick={() => setConfirming(true)}>
            Review grid
          </Button>
        ) : (
          <>
            <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
              Back
            </Button>
            <Button type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
              {mutation.isPending ? "Applying…" : "Apply grid"}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}
