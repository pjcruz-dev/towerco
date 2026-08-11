import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import type { RolloutGateApprovalRequest } from "@/modules/rollout/types";

type Props = {
  awaitingCount: number;
  preview: RolloutGateApprovalRequest[];
  /** When true, render an empty state instead of hiding the card. */
  alwaysShow?: boolean;
};

export function GateApprovalsPendingWidget({
  awaitingCount,
  preview,
  alwaysShow = false,
}: Props) {
  if (!alwaysShow && awaitingCount === 0 && preview.length === 0) {
    return null;
  }

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-medium text-foreground">Gate approvals awaiting you</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Formal timeline gate steps assigned to your role or acting delegation.
          </p>
        </div>
        <Link href="/project-one/gate-approvals?awaiting_me=1" className={buttonVariants({ size: "sm" })}>
          Open inbox ({awaitingCount})
        </Link>
      </div>

      {preview.length > 0 ? (
        <ul className="mt-4 space-y-2">
          {preview.map((row) => (
            <li
              key={row.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/70 px-3 py-2 text-sm"
            >
              <div>
                <p className="font-medium text-foreground">
                  {row.rollout?.rollout_ref ?? "Rollout"} · {row.phase?.label ?? row.phase_key}
                </p>
                <p className="text-xs text-muted-foreground">
                  Step {row.current_step + 1}/{row.approval_chain.length} · {row.current_approver_role}
                  {row.escalation_due ? (
                    <span className="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                      Escalation due
                    </span>
                  ) : null}
                </p>
              </div>
              {row.rollout ? (
                <Link
                  href={`/project-one/rollouts/${row.rollout.id}?phase=${encodeURIComponent(row.phase_key)}`}
                  className={buttonVariants({ size: "sm", variant: "outline" })}
                >
                  Review
                </Link>
              ) : null}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">
          {awaitingCount > 0
            ? `${awaitingCount} item(s) in your approval queue.`
            : "Nothing waiting on you right now."}
        </p>
      )}
    </section>
  );
}
