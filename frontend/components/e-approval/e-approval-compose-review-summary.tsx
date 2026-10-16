"use client";

import {
  buildComposeReviewSummaryRows,
  placeReviewTotals,
  type ComposeReviewSummaryRow,
  type ComposeReviewTable,
  type ComposeReviewTotal,
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

function isReviewMoneyColumn(header: string): boolean {
  const label = header.trim();
  return /^amount$/i.test(label) || /^vat$/i.test(label) || /^non-?vatable$/i.test(label) || /net of vat/i.test(label);
}

function ReviewTotalCell({ total }: { total: ComposeReviewTotal }) {
  return (
    <div className="text-right">
      <div className="text-[10px] font-normal leading-tight text-muted-foreground">{total.label}</div>
      <div className="text-sm font-medium whitespace-nowrap tabular-nums text-foreground">{total.value}</div>
    </div>
  );
}

export function EApprovalLineItemsTable({
  table,
  totals,
  printLayout = false,
}: {
  table: ComposeReviewTable;
  totals?: ComposeReviewTotal[];
  /** Expense lines on the request page use the print table: numbers under the column, no repeated names. */
  printLayout?: boolean;
}) {
  const totalRows = placeReviewTotals(table.headers, totals ?? []);
  const moneyColumns = table.headers.map((header) => isReviewMoneyColumn(header));

  return (
    <div className="mt-2 overflow-x-auto rounded-md border border-border">
      <table className={cn("w-full min-w-[20rem] border-collapse text-left", printLayout ? "text-[11px]" : "text-sm")}>
        <thead>
          <tr className={cn(printLayout ? "bg-muted text-foreground" : "bg-slate-700 text-white dark:bg-slate-800")}>
            {table.headers.map((header, headerIndex) => (
              <th
                key={header}
                scope="col"
                className={cn(
                  "border border-border px-2 py-1.5 font-medium",
                  printLayout ? "text-center text-[10px] leading-tight whitespace-normal" : "px-3 py-2 text-xs whitespace-nowrap",
                  moneyColumns[headerIndex] && "min-w-[5.5rem] text-right",
                )}
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
                rowIndex % 2 === 1 ? "bg-muted/30" : "bg-card",
              )}
            >
              {row.map((cell, cellIndex) => (
                <td
                  key={`review-cell-${rowIndex}-${cellIndex}`}
                  className={cn(
                    "border border-border align-top break-words",
                    printLayout ? "px-2 py-1" : "px-3 py-2 whitespace-pre-line",
                    moneyColumns[cellIndex] && "min-w-[5.5rem] text-right whitespace-nowrap tabular-nums",
                    cell === "—" && "text-muted-foreground",
                  )}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {totalRows.length > 0 ? (
          <tfoot>
            {totalRows.map((totalRow, rowIndex) => (
              <tr key={`review-total-${rowIndex}`} className="border-t border-border bg-muted/50 font-bold">
                {totalRow.map((total, cellIndex) => (
                  <td
                    key={`review-total-${rowIndex}-${cellIndex}`}
                    className={cn(
                      "border border-border align-bottom",
                      printLayout ? "px-2 py-1" : "px-3 py-2",
                      moneyColumns[cellIndex] && "min-w-[5.5rem] text-right",
                    )}
                  >
                    {total ? (
                      printLayout && rowIndex === 0 ? (
                        <span className="whitespace-nowrap tabular-nums">{total.value}</span>
                      ) : (
                        <ReviewTotalCell total={total} />
                      )
                    ) : rowIndex === 0 && cellIndex === 0 ? (
                      <span className="text-[10px] tracking-wide text-foreground uppercase">
                        Totals
                      </span>
                    ) : null}
                  </td>
                ))}
              </tr>
            ))}
          </tfoot>
        ) : null}
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
        <EApprovalLineItemsTable
          table={row.table}
          totals={row.totals}
          printLayout={row.fieldName === "expense_lines"}
        />
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
