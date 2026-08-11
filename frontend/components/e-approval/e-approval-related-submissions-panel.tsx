"use client";

import Link from "next/link";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { formatSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type {
  EApprovalRelatedSubmissionRow,
  EApprovalRelatedSubmissions,
  EApprovalRelatedSubmissionsSummary,
} from "@/modules/e-approval/types";

type Props = {
  related?: EApprovalRelatedSubmissions | null;
};

function formatAmount(label: string | null, value: string | null): string | null {
  if (!value || value.trim() === "") {
    return null;
  }

  const numeric = Number(value.replace(/,/g, ""));
  const formatted = Number.isFinite(numeric)
    ? numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : value;

  return label ? `${label}: ${formatted}` : formatted;
}

function parentSectionLabel(row: EApprovalRelatedSubmissionRow): string {
  if (row.form_family === "purchase_requisition") {
    return "Purchase requisition";
  }
  if (row.form_family === "cash_advance") {
    return "Cash advance";
  }

  return "Parent request";
}

function childrenSectionLabel(children: EApprovalRelatedSubmissionRow[], contextFamily: string | null | undefined): string {
  const poChildren = children.filter((child) => child.form_family === "purchase_order");
  if (poChildren.length > 0 || contextFamily === "purchase_requisition") {
    return poChildren.length === 1 ? "Purchase order" : `Purchase orders (${poChildren.length || children.length})`;
  }

  const liquidationChildren = children.filter((child) => child.form_family === "liquidation");
  if (liquidationChildren.length > 0 || contextFamily === "cash_advance") {
    return liquidationChildren.length === 1
      ? "Liquidation"
      : `Liquidations (${liquidationChildren.length || children.length})`;
  }

  return children.length === 1 ? "Linked follow-up" : `Linked follow-ups (${children.length})`;
}

function ChainSummaryCard({ summary }: { summary: EApprovalRelatedSubmissionsSummary }) {
  return (
    <div className="grid gap-3 rounded-lg border border-border bg-muted/20 p-3 text-sm sm:grid-cols-3">
      <div>
        <p className="text-xs text-muted-foreground">{summary.total_label}</p>
        <p className="mt-0.5 font-medium text-foreground">{formatSubmissionAmount(summary.total_amount)}</p>
      </div>
      <div>
        <p className="text-xs text-muted-foreground">{summary.committed_label}</p>
        <p className="mt-0.5 font-medium text-foreground">{formatSubmissionAmount(summary.committed_amount)}</p>
      </div>
      <div>
        <p className="text-xs text-muted-foreground">Open balance</p>
        <p className="mt-0.5 font-medium text-foreground">{formatSubmissionAmount(summary.open_balance)}</p>
      </div>
    </div>
  );
}

function RelatedSubmissionLink({ row }: { row: EApprovalRelatedSubmissionRow }) {
  const amount = formatAmount(row.amount_label, row.amount_value);

  return (
    <Link
      href={`/e-approval/submissions/${row.id}`}
      className="flex flex-col gap-1 rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm transition-colors hover:border-primary/30 sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="min-w-0">
        <p className="truncate font-medium text-foreground">
          {row.document_no}
          {row.form_name ? <span className="font-normal text-muted-foreground"> · {row.form_name}</span> : null}
        </p>
        {amount ? <p className="text-xs text-muted-foreground">{amount}</p> : null}
      </div>
      <EApprovalStatusBadge status={row.status} kind="submission" className="self-start sm:self-center" />
    </Link>
  );
}

export function EApprovalRelatedSubmissionsPanel({ related }: Props) {
  const parent = related?.parent ?? null;
  const children = related?.children ?? [];
  const summary = related?.summary ?? null;

  if (!parent && children.length === 0 && !summary) {
    return null;
  }

  return (
    <div className="mt-6 space-y-4 border-t border-border pt-4">
      <div>
        <h3 className="text-sm font-medium text-foreground">Related submissions</h3>
        <p className="mt-0.5 text-xs text-muted-foreground">
          Parent and child requests linked through the finance/procurement workflow chain.
        </p>
      </div>

      {summary ? <ChainSummaryCard summary={summary} /> : null}

      {parent ? (
        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">{parentSectionLabel(parent)}</p>
          <RelatedSubmissionLink row={parent} />
        </div>
      ) : null}

      {children.length > 0 ? (
        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">
            {childrenSectionLabel(children, related?.context_form_family)}
          </p>
          <ul className="space-y-2">
            {children.map((child) => (
              <li key={child.id}>
                <RelatedSubmissionLink row={child} />
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
