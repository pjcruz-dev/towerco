"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { GateLabelText, MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { fetchRolloutActivity } from "@/lib/api/modules/rollout-api";
import type { RolloutDetail, RolloutGateApprovalRequest, RolloutTimelinePhase } from "@/modules/rollout/types";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
};

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return "—";
  try {
    return new Date(iso).toLocaleString(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return iso;
  }
}

function approvalEntries(phase: RolloutTimelinePhase): RolloutGateApprovalRequest[] {
  const entries: RolloutGateApprovalRequest[] = [];
  if (phase.active_gate_approval) entries.push(phase.active_gate_approval);
  if (
    phase.latest_gate_approval &&
    (!phase.active_gate_approval || phase.latest_gate_approval.id !== phase.active_gate_approval.id)
  ) {
    entries.push(phase.latest_gate_approval);
  }
  return entries;
}

function StepLogList({ log }: { log: Array<Record<string, unknown>> }) {
  if (!log.length) {
    return <p className="text-xs text-muted-foreground">No step history yet.</p>;
  }

  return (
    <ul className="space-y-2 text-xs">
      {log.map((entry, index) => {
        const step = entry.step ?? entry.step_number ?? index + 1;
        const role = entry.role ?? entry.approver_role ?? "—";
        const decision = entry.decision ?? entry.action ?? "—";
        const at = entry.at ?? entry.approved_at ?? entry.created_at;
        const notes = entry.notes ?? entry.comment ?? entry.rejection_notes;

        return (
          <li key={`${String(step)}-${index}`} className="rounded-md border border-border bg-muted/20 px-2 py-1.5">
            <p className="font-medium text-foreground">
              Step {String(step)} · {String(role)} · {String(decision)}
            </p>
            <p className="text-muted-foreground">{formatWhen(typeof at === "string" ? at : null)}</p>
            {notes ? <p className="mt-1 text-muted-foreground">{String(notes)}</p> : null}
          </li>
        );
      })}
    </ul>
  );
}

export function RolloutApprovalsActivityPanel({ rolloutId, detail }: Props) {
  const activityQuery = useQuery({
    queryKey: ["project-one", "rollouts", "activity", rolloutId],
    queryFn: () => fetchRolloutActivity(rolloutId),
  });

  const phasesWithApprovals = (detail.timeline_phases ?? []).filter(
    (phase) => phase.active_gate_approval || phase.latest_gate_approval,
  );

  const activity = activityQuery.data ?? [];

  return (
    <aside className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm lg:sticky lg:top-4 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-base font-medium text-foreground">Approvals & activity</h2>
          <p className="mt-1 text-xs text-muted-foreground">Gate chain history and rollout audit trail.</p>
        </div>
        <Link
          href="/project-one/gate-approvals?awaiting_me=1"
          className="inline-flex h-8 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium hover:bg-muted"
        >
          Inbox
        </Link>
      </div>

      <section className="space-y-3">
        <h3 className="text-xs font-medium text-muted-foreground">Gate approvals</h3>
        {phasesWithApprovals.length === 0 ? (
          <p className="text-sm text-muted-foreground">No gate approval requests for this rollout yet.</p>
        ) : (
          phasesWithApprovals.map((phase) =>
            approvalEntries(phase).map((approval) => (
              <div key={approval.id} className="rounded-lg border border-border bg-muted/10 p-3">
                <p className="text-sm font-medium text-foreground">
                  <MilestonePhaseLabel phaseKey={phase.phase_key} label={phase.label} />
                </p>
                {phase.gate_label ? (
                  <p className="mt-1 text-xs text-muted-foreground line-clamp-2">
                    <GateLabelText text={phase.gate_label} />
                  </p>
                ) : null}
                <p className="mt-2 text-xs capitalize text-muted-foreground">
                  Status: <span className="font-medium text-foreground">{approval.status.replaceAll("_", " ")}</span>
                  {approval.current_approver_role ? (
                    <>
                      {" "}
                      · Step {approval.current_step} ({approval.current_approver_role})
                    </>
                  ) : null}
                </p>
                {approval.request_notes ? (
                  <p className="mt-2 text-xs text-muted-foreground">
                    <span className="font-medium text-foreground">Request:</span> {approval.request_notes}
                  </p>
                ) : null}
                {approval.rejection_notes ? (
                  <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                    <span className="font-medium">Rejected:</span> {approval.rejection_notes}
                  </p>
                ) : null}
                <div className="mt-3">
                  <StepLogList log={approval.step_log ?? []} />
                </div>
              </div>
            )),
          )
        )}
      </section>

      <section className="space-y-2">
        <h3 className="text-xs font-medium text-muted-foreground">Recent activity</h3>
        {activityQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">Loading activity…</p>
        ) : activity.length === 0 ? (
          <p className="text-sm text-muted-foreground">No audit entries yet.</p>
        ) : (
          <ul className="max-h-64 space-y-2 overflow-y-auto pr-1 text-xs">
            {activity.map((entry) => (
              <li key={entry.id} className="rounded-md border border-border px-2 py-1.5">
                <p className="font-medium text-foreground">{entry.description}</p>
                <p className="text-muted-foreground">
                  {formatWhen(entry.created_at)}
                  {entry.causer?.name ? ` · ${entry.causer.name}` : ""}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </aside>
  );
}
