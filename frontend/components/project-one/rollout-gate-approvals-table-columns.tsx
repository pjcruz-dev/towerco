"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { GateLabelText, MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import {
  GateBinderReadinessBanner,
  gateBinderBlocksFinalApprove,
} from "@/components/rollout/gate-binder-readiness-banner";
import { GateApprovalActingLabel } from "@/components/rollout/gate-approval-acting-label";
import { Button } from "@/components/ui/button";
import {
  createActionsColumn,
  createDateColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import type { RolloutGateApprovalRequest } from "@/modules/rollout/types";

export function createRolloutGateApprovalsTableColumns(options: {
  pending: boolean;
  onDecide: (id: string, decision: "approve" | "reject") => void;
}): ColumnDef<RolloutGateApprovalRequest>[] {
  return [
    {
      id: "rollout",
      header: "Rollout",
      enableSorting: false,
      cell: ({ row }) => {
        const item = row.original;
        if (!item.rollout) return "—";
        return (
          <Link
            className="font-medium text-primary underline-offset-4 hover:underline"
            href={`/project-one/rollouts/${item.rollout.id}?phase=${encodeURIComponent(item.phase_key)}`}
          >
            {item.rollout.rollout_ref}
          </Link>
        );
      },
    },
    {
      id: "phase_key",
      accessorKey: "phase_key",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Phase / gate" />,
      cell: ({ row }) => (
        <div className="space-y-1.5">
          <div>
            <p className="font-medium">
              <MilestonePhaseLabel
                phaseKey={row.original.phase_key}
                label={row.original.phase?.label ?? row.original.phase_key}
              />
            </p>
            <p className="text-xs text-muted-foreground">
              <GateLabelText text={row.original.gate_label ?? ""} />
            </p>
          </div>
          {row.original.can_act && row.original.document_binder_gate?.applies ? (
            <GateBinderReadinessBanner
              gate={row.original.document_binder_gate}
              blocksApprove={gateBinderBlocksFinalApprove(
                row.original.document_binder_gate,
                row.original.is_final_step,
              )}
              compact
            />
          ) : null}
        </div>
      ),
    },
    createTextColumn(
      "status",
      "Status",
      (row) => <span className="capitalize">{row.status.replaceAll("_", " ")}</span>,
      { enableSorting: true },
    ),
    {
      id: "current_step",
      accessorKey: "current_step",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Step" />,
      cell: ({ row }) => (
        <span className="text-xs text-muted-foreground">
          {row.original.status === "in_review"
            ? `${row.original.current_step + 1}/${row.original.approval_chain.length} · ${row.original.current_approver_role ?? "—"}`
            : "—"}
        </span>
      ),
    },
    {
      id: "waiting",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Waiting" />,
      cell: ({ row }) => {
        const item = row.original;
        if (item.status === "in_review" && item.step_waiting_working_days != null) {
          return (
            <span className="text-xs text-muted-foreground">
              {item.step_waiting_working_days} WD
              {item.escalation_due ? (
                <span className="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                  Escalation
                </span>
              ) : null}
            </span>
          );
        }
        return <span className="text-xs text-muted-foreground">—</span>;
      },
    },
    createDateColumn("submitted_at", "Submitted", (row) => row.submitted_at, {
      enableSorting: true,
    }),
    createActionsColumn("Actions", (row) => {
      if (!row.original.can_act) return null;
      const blockApprove = gateBinderBlocksFinalApprove(
        row.original.document_binder_gate,
        row.original.is_final_step,
      );
      return (
        <div className="flex flex-col items-end gap-1">
          <GateApprovalActingLabel actingFor={row.original.acting_for} />
          <div className="flex justify-end gap-1">
            <Button
              type="button"
              size="sm"
              disabled={options.pending || blockApprove}
              title={
                blockApprove
                  ? "Complete the site binder checklist before final approve"
                  : undefined
              }
              onClick={() => options.onDecide(row.original.id, "approve")}
            >
              Approve
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={options.pending}
              onClick={() => options.onDecide(row.original.id, "reject")}
            >
              Reject
            </Button>
          </div>
        </div>
      );
    }),
  ];
}
