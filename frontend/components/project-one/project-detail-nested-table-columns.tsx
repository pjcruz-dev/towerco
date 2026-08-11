"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { AcronymLabel } from "@/components/help/acronym-label";
import { ProjectMilestoneLabel } from "@/components/help/milestone-phase-label";
import { MilestoneWorkflowActions } from "@/components/project-one/milestone-workflow-actions";
import {
  createActionsColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type {
  ProjectMilestoneRow,
  ProjectOneApproval,
  ProjectRolloutSummary,
} from "@/modules/project-one/types";

export function createProjectDetailMilestoneColumns(
  projectId: string,
): ColumnDef<ProjectMilestoneRow>[] {
  return [
    createTextColumn("name", "Milestone", (row) => (
      <span className="font-medium">
        <ProjectMilestoneLabel name={row.name} />
      </span>
    )),
    createTextColumn("due_date", "Due date", (row) => row.due_date ?? "—"),
    createTextColumn("status", "Status", (row) => (
      <span className="capitalize">{row.status.replaceAll("_", " ")}</span>
    )),
    createActionsColumn("Actions", (row) => (
      <MilestoneWorkflowActions
        milestoneId={row.original.id}
        status={row.original.status}
        invalidateKeys={[["project-one", "projects", projectId]]}
      />
    )),
  ];
}

export function createProjectDetailRolloutColumns(): ColumnDef<ProjectRolloutSummary>[] {
  return [
    createLinkColumn("rollout_ref", "Reference", {
      href: (row) => `/project-one/rollouts/${row.id}`,
      label: (row) => row.rollout_ref,
      className: "font-medium text-primary underline-offset-4 hover:underline",
    }),
    createTextColumn("search_ring_name", "Search ring", (row) => row.search_ring_name ?? "—"),
    {
      id: "mno",
      accessorFn: (row) => row.mno,
      header: () => <AcronymLabel term="MNO" />,
      cell: ({ row }) => <span className="uppercase">{row.original.mno}</span>,
      enableSorting: false,
    },
    createTextColumn("status", "Status", (row) => (
      <span className="capitalize">{row.status}</span>
    )),
    {
      id: "target_rfi_working_date",
      accessorFn: (row) => row.target_rfi_working_date ?? "",
      header: () => <AcronymLabel term="RFI / RFTI">Target RFI</AcronymLabel>,
      cell: ({ row }) => row.original.target_rfi_working_date ?? "—",
      enableSorting: false,
    },
  ];
}

export function createProjectDetailApprovalColumns(): ColumnDef<ProjectOneApproval>[] {
  return [
    createTextColumn("title", "Title", (row) => (
      <span className="font-medium">{row.title}</span>
    )),
    createTextColumn("type", "Type", (row) => row.type),
    createTextColumn("requester", "Requester", (row) => row.requester),
    {
      id: "slaRisk",
      accessorFn: (row) => row.slaRisk,
      header: () => <AcronymLabel term="SLA">SLA risk</AcronymLabel>,
      cell: ({ row }) => <span className="uppercase">{row.original.slaRisk}</span>,
      enableSorting: false,
    },
  ];
}
