"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { syncRolloutPermits } from "@/lib/api/modules/rollout-api";
import { getErrorMessage } from "@/lib/api/error";
import { isMocColPassed, isMocColReady, isPreAssessmentPassed } from "@/lib/rollout/phase-gate-readiness";
import { permitsForTimelinePhase, phasePermitsSummary } from "@/lib/rollout/phase-permits";
import type { RolloutDetail, RolloutPermitRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
  phaseKey: string;
  phaseLabel: string;
  canManage: boolean;
};

export function RolloutPhasePermitsPanel({ rolloutId, detail, phaseKey, phaseLabel, canManage }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const allPermits = detail.permits ?? [];
  const phasePermits = useMemo(() => permitsForTimelinePhase(allPermits, phaseKey), [allPermits, phaseKey]);
  const [rows, setRows] = useState<RolloutPermitRow[]>(phasePermits);
  const isMocCol = phaseKey === "moc_col";

  useEffect(() => {
    setRows(permitsForTimelinePhase(detail.permits ?? [], phaseKey));
  }, [detail.permits, phaseKey]);

  const mutation = useMutation({
    mutationFn: () => {
      const edited = new Map(rows.map((row) => [row.permit_type, row]));
      const payload = allPermits.map((row) => {
        const updated = edited.get(row.permit_type);
        if (!updated) {
          return {
            permit_type: row.permit_type,
            applied_date: row.applied_date,
            secured_date: row.secured_date,
            notes: row.notes,
          };
        }

        return {
          permit_type: updated.permit_type,
          applied_date: updated.applied_date,
          secured_date: updated.secured_date,
          notes: updated.notes,
        };
      });

      return syncRolloutPermits(rolloutId, payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", rolloutId] });
      push({ level: "success", title: "Permits saved", message: "Permit checkpoints updated." });
    },
    onError: (error) => {
      push({ level: "error", title: "Save failed", message: getErrorMessage(error) });
    },
  });

  const summary = phasePermitsSummary(allPermits, phaseKey);

  const updateRow = (permitType: string, field: "applied_date" | "secured_date" | "notes", value: string) => {
    setRows((current) =>
      current.map((row) =>
        row.permit_type === permitType ? { ...row, [field]: value.trim() === "" ? null : value } : row,
      ),
    );
  };

  if (phasePermits.length === 0 && !isMocCol) {
    return null;
  }

  return (
    <div className="space-y-3">
      {isMocCol ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h3 className="text-sm font-medium text-foreground">MOC + COL Securing</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Secure MOC/COL (eLAS IRR) <span className="font-medium">before</span> TSSR create/review. Gate chain:
            SAQ → PMO.
          </p>
          <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
            <li>Pre-assessment passed {isPreAssessmentPassed(detail) ? "✓" : ""}</li>
            <li>Record MOC/COL permit dates below (when listed)</li>
            <li>
              Request / pass MOC+COL gate
              {isMocColPassed(detail) ? " ✓" : isMocColReady(detail) ? " — ready" : " — after Pre-assessment"}
            </li>
          </ul>
          {!isPreAssessmentPassed(detail) ? (
            <p className="mt-2 text-xs text-amber-800 dark:text-amber-200">
              Complete Pre-assessment before requesting this gate.
            </p>
          ) : null}
          {isMocColPassed(detail) ? (
            <p className="mt-2 text-xs text-green-800 dark:text-green-200">
              MOC/COL complete — proceed to TSSR create/review.
            </p>
          ) : null}
        </div>
      ) : null}

      {phasePermits.length > 0 ? (
        <section
          className="rounded-lg border border-border border-l-4 border-l-primary bg-card shadow-sm"
          aria-labelledby={`phase-permits-title-${phaseKey}`}
        >
          <header className="border-b border-border px-4 py-3 sm:px-5">
            <h3 id={`phase-permits-title-${phaseKey}`} className="text-base font-medium text-foreground">
              <MilestonePhaseLabel phaseKey={phaseKey} label={phaseLabel} /> — permits
            </h3>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Applied and secured dates for this phase. Gate pass stays in the timeline row above.
              {summary ? ` · ${summary}` : null}
            </p>
          </header>

          <div className="space-y-3 px-4 py-4 sm:px-5">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Permit</TableHead>
                    <TableHead>Applied</TableHead>
                    <TableHead>Secured</TableHead>
                    <TableHead>Notes</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.permit_type}>
                      <TableCell className="font-medium">{row.label}</TableCell>
                      <TableCell>
                        <DatePicker
                          className="h-9 min-w-[9rem] text-sm"
                          value={row.applied_date ?? ""}
                          disabled={!canManage}
                          onChange={(value) => updateRow(row.permit_type, "applied_date", value)}
                        />
                      </TableCell>
                      <TableCell>
                        <DatePicker
                          className="h-9 min-w-[9rem] text-sm"
                          value={row.secured_date ?? ""}
                          disabled={!canManage}
                          onChange={(value) => updateRow(row.permit_type, "secured_date", value)}
                        />
                      </TableCell>
                      <TableCell>
                        <input
                          type="text"
                          className="h-9 w-full min-w-[10rem] rounded-md border border-input bg-background px-2 text-sm"
                          value={row.notes ?? ""}
                          disabled={!canManage}
                          placeholder="Optional"
                          onChange={(event) => updateRow(row.permit_type, "notes", event.target.value)}
                        />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            {canManage ? (
              <Button size="sm" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
                {mutation.isPending ? "Saving…" : "Save permits"}
              </Button>
            ) : null}
          </div>
        </section>
      ) : null}
    </div>
  );
}
