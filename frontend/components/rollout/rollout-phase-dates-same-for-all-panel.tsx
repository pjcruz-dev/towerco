"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { getErrorMessage } from "@/lib/api/error";
import {
  bulkBackfillRolloutPhaseDates,
  fetchRolloutPlaybookStatus,
} from "@/lib/api/modules/rollout-api";
import { mergePlaybookPhaseCatalog } from "@/lib/rollout/playbook-phase-catalog";
import type { RolloutListRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  selectedIds: string[];
  selectedRows: RolloutListRow[];
  onSuccess: () => void;
  onCancel: () => void;
};

export function RolloutPhaseDatesSameForAllPanel({
  selectedIds,
  selectedRows,
  onSuccess,
  onCancel,
}: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [datesByPhase, setDatesByPhase] = useState<Record<string, string>>({});
  const [markGatePassed, setMarkGatePassed] = useState(true);
  const [confirming, setConfirming] = useState(false);

  const playbookQuery = useQuery({
    queryKey: ["project-one", "rollout-playbook", "bulk-phase-dates"],
    queryFn: fetchRolloutPlaybookStatus,
  });

  const phaseCatalog = useMemo(
    () => mergePlaybookPhaseCatalog(playbookQuery.data?.timeline_templates),
    [playbookQuery.data?.timeline_templates],
  );

  useEffect(() => {
    setDatesByPhase({});
    setMarkGatePassed(true);
    setConfirming(false);
  }, [selectedIds]);

  const phasesToApply = useMemo(
    () =>
      Object.entries(datesByPhase)
        .filter(([, date]) => date.trim() !== "")
        .map(([phase_key, actual_date]) => ({ phase_key, actual_date })),
    [datesByPhase],
  );

  const mutation = useMutation({
    mutationFn: () =>
      bulkBackfillRolloutPhaseDates({
        rollout_ids: [...selectedIds],
        phases: phasesToApply,
        mark_gate_passed: markGatePassed,
        backfill: true,
      }),
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      await queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });

      push({
        level: result.updated > 0 ? "success" : "warning",
        title: "Phase dates backfilled",
        message: `${result.phases_applied} phase updates on ${result.updated} rollout${result.updated === 1 ? "" : "s"}.`,
      });

      onSuccess();
    },
    onError: (error) => {
      push({ level: "error", title: "Backfill failed", message: getErrorMessage(error) });
    },
  });

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="flex-1 space-y-4 overflow-y-auto px-4 py-2">
        <p className="text-xs text-muted-foreground">
          One date per phase applied to every selected rollout — use <strong>Grid per rollout</strong> when EZVL and
          OLMD need different dates.
        </p>

        <label className="flex cursor-pointer items-start gap-2 text-sm">
          <Checkbox
            className="mt-0.5"
            checked={markGatePassed}
            onCheckedChange={(v) => setMarkGatePassed(v === true)}
          />
          <span className="font-medium text-foreground">Mark gate Passed</span>
        </label>

        {playbookQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">Loading phases…</p>
        ) : (
          <div className="overflow-hidden rounded-lg border border-border">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                <tr>
                  <th className="px-3 py-2 font-medium">Phase</th>
                  <th className="px-3 py-2 font-medium">Actual date (all rollouts)</th>
                </tr>
              </thead>
              <tbody>
                {phaseCatalog.map((phase) => (
                  <tr key={phase.phase_key} className="border-b border-border/60 last:border-0">
                    <td className="px-3 py-2">
                      <MilestonePhaseLabel phaseKey={phase.phase_key} label={phase.label} />
                    </td>
                    <td className="px-3 py-2">
                      <DatePicker
                        className="h-9 text-xs"
                        value={datesByPhase[phase.phase_key] ?? ""}
                        onChange={(value) =>
                          setDatesByPhase((prev) => ({
                            ...prev,
                            [phase.phase_key]: value,
                          }))
                        }
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {confirming ? (
          <p className="text-sm text-muted-foreground">
            Apply {phasesToApply.length} phase(s) to {selectedIds.length} rollout
            {selectedIds.length === 1 ? "" : "s"} ({selectedRows
              .slice(0, 3)
              .map((r) => r.rollout_ref)
              .join(", ")}
            …)?
          </p>
        ) : null}
      </div>

      <div className="flex flex-row justify-end gap-2 border-t border-border px-4 py-4">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        {!confirming ? (
          <Button type="button" disabled={phasesToApply.length === 0} onClick={() => setConfirming(true)}>
            Review
          </Button>
        ) : (
          <>
            <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
              Back
            </Button>
            <Button type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
              {mutation.isPending ? "Applying…" : `Apply to ${selectedIds.length}`}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}
