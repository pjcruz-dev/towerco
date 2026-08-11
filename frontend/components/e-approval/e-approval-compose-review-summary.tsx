"use client";

import {
  buildComposeReviewSummaryRows,
  type ComposeReviewSummaryRow,
  type ComposeReviewTable,
} from "@/modules/e-approval/form-compose-review";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  fields: EApprovalFormFieldInput[];
  values: Record<string, string>;
  fileSelections?: Record<string, File[]>;
  onJumpToField?: (fieldName: string) => void;
  className?: string;
};

function ReviewTable({ table }: { table: ComposeReviewTable }) {
  return (
    <div className="mt-2 overflow-x-auto rounded-lg border border-border">
      <table className="w-full min-w-[20rem] border-collapse text-left text-sm">
        <thead>
          <tr className="bg-slate-700 text-white dark:bg-slate-800">
            {table.headers.map((header) => (
              <th
                key={header}
                scope="col"
                className="px-3 py-2 text-xs font-medium whitespace-nowrap"
              >
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {table.rows.map((row, rowIndex) => (
            <tr
              key={`review-row-${rowIndex}`}
              className={cn(
                "border-t border-border",
                rowIndex % 2 === 1 ? "bg-muted/20" : "bg-card",
              )}
            >
              {row.map((cell, cellIndex) => (
                <td
                  key={`review-cell-${rowIndex}-${cellIndex}`}
                  className={cn(
                    "px-3 py-2 align-top break-words",
                    cell === "—" && "text-muted-foreground",
                  )}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function SummaryRow({
  row,
  onJumpToField,
}: {
  row: ComposeReviewSummaryRow;
  onJumpToField?: (fieldName: string) => void;
}) {
  if (row.table) {
    return (
      <div className="space-y-1">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <p className={cn("text-xs text-muted-foreground", row.highlight && "font-medium text-foreground")}>
            {row.label}
          </p>
          {onJumpToField ? (
            <button
              type="button"
              className="text-xs font-medium text-primary hover:underline"
              onClick={() => onJumpToField(row.fieldName)}
            >
              Edit
            </button>
          ) : null}
        </div>
        <p className="text-xs text-muted-foreground">{row.value}</p>
        <ReviewTable table={row.table} />
      </div>
    );
  }

  const content = (
    <>
      <dt
        className={cn(
          "text-xs text-muted-foreground",
          row.highlight && "font-medium text-foreground",
        )}
      >
        {row.label}
      </dt>
      <dd
        className={cn(
          "text-sm text-foreground break-words",
          row.highlight && "font-medium",
          row.value === "—" && "text-muted-foreground font-normal",
        )}
      >
        {row.value}
      </dd>
    </>
  );

  if (!onJumpToField) {
    return content;
  }

  return (
    <button
      type="button"
      className="col-span-2 grid grid-cols-[minmax(7rem,11rem)_1fr] gap-x-3 gap-y-0.5 rounded-md px-1 py-1.5 text-left transition-colors hover:bg-muted/40"
      onClick={() => onJumpToField(row.fieldName)}
    >
      {content}
    </button>
  );
}

export function EApprovalComposeReviewSummary({
  fields,
  values,
  fileSelections,
  onJumpToField,
  className,
}: Props) {
  const rows = buildComposeReviewSummaryRows(fields, values, fileSelections);
  const tableRows = rows.filter((row) => row.table);
  const scalarRows = rows.filter((row) => !row.table);
  const highlightRows = scalarRows.filter((row) => row.highlight);
  const otherRows = scalarRows.filter((row) => !row.highlight);

  return (
    <div className={cn("space-y-4", className)}>
      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <p className="text-base font-medium text-foreground">Review & submit</p>
        <p className="mt-1 text-sm text-muted-foreground">
          Confirm payee, amount, bank, and cost charge before submitting. Use Back to edit a previous
          step.
        </p>
      </div>

      {highlightRows.length > 0 ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="mb-3 text-xs font-medium text-foreground">Key details</p>
          <dl
            className={cn(
              "grid gap-y-2",
              onJumpToField
                ? "grid-cols-1"
                : "grid-cols-[minmax(7rem,11rem)_1fr] gap-x-3",
            )}
          >
            {highlightRows.map((row) => (
              <SummaryRow key={row.fieldName} row={row} onJumpToField={onJumpToField} />
            ))}
          </dl>
        </div>
      ) : null}

      {tableRows.length > 0 ? (
        <div className="space-y-3">
          {tableRows.map((row) => (
            <div key={row.fieldName} className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <SummaryRow row={row} onJumpToField={onJumpToField} />
            </div>
          ))}
        </div>
      ) : null}

      {otherRows.length > 0 ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="mb-3 text-xs font-medium text-foreground">All answers</p>
          <dl
            className={cn(
              "grid gap-y-2",
              onJumpToField
                ? "grid-cols-1"
                : "grid-cols-[minmax(7rem,11rem)_1fr] gap-x-3",
            )}
          >
            {otherRows.map((row) => (
              <SummaryRow key={row.fieldName} row={row} onJumpToField={onJumpToField} />
            ))}
          </dl>
        </div>
      ) : null}
    </div>
  );
}
