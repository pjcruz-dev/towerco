"use client";

import {
  formatEApprovalFieldDisplayValue,
  shouldShowApproverDuplicateSubtitle,
  type EApprovalSubmissionFieldValue,
} from "@/modules/e-approval/display";
import { formatSubmissionCurrencyDisplay } from "@/modules/e-approval/submission-form-content";
import { formatGridCurrencyDisplay } from "@/modules/e-approval/grid-row-formulas";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import { cn } from "@/lib/utils";

type Props = {
  field: EApprovalSubmissionFieldValue;
  duplicateApproverIds: Set<string>;
};

function parseGridDisplay(display: string): { columns: string[]; rows: Record<string, string>[] } | null {
  const trimmed = display.trim();
  if (!trimmed || !trimmed.includes("\n")) {
    return null;
  }

  const rowLines = trimmed
    .split("\n")
    .map((l) => l.trim())
    .filter((l) => /^Row\s+\d+:\s*/i.test(l));

  if (rowLines.length === 0) {
    return null;
  }

  const columns: string[] = [];
  const seen = new Set<string>();
  const rows: Record<string, string>[] = [];

  for (const line of rowLines) {
    const rest = line.replace(/^Row\s+\d+:\s*/i, "");

    // Backend formats cells like: "Col A: value; Col B: value"
    let tokens = rest.split("; ").map((t) => t.trim()).filter(Boolean);
    if (tokens.length <= 1) {
      tokens = rest.split(";").map((t) => t.trim()).filter(Boolean);
    }

    const row: Record<string, string> = {};
    for (const token of tokens) {
      // Prefer splitting on the ": " delimiter to avoid issues with "Label: value" in the value itself.
      const idx = token.indexOf(": ");
      const sepLen = idx >= 0 ? 2 : 1;
      const splitIdx = idx >= 0 ? idx : token.indexOf(":");
      if (splitIdx < 0) {
        continue;
      }

      const label = token.slice(0, splitIdx).trim();
      const value = token.slice(splitIdx + sepLen).trim();
      if (!label) {
        continue;
      }

      row[label] = value;
      if (!seen.has(label)) {
        seen.add(label);
        columns.push(label);
      }
    }

    rows.push(row);
  }

  return columns.length === 0 ? null : { columns, rows };
}

function parseChecklistMatrixDisplay(display: string): { columns: string[]; rows: Record<string, string>[] } | null {
  const lines = display
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);

  if (lines.length === 0) {
    return null;
  }

  const columns: string[] = ["Cost Application"];
  const seen = new Set<string>(columns);
  const rows: Record<string, string>[] = [];

  for (const line of lines) {
    const dashIdx = line.indexOf(" — ");
    const rowLabel = dashIdx >= 0 ? line.slice(0, dashIdx).trim() : line;
    const rest = dashIdx >= 0 ? line.slice(dashIdx + 3).trim() : "";
    const row: Record<string, string> = { "Cost Application": rowLabel || "—" };

    if (rest) {
      const tokens = rest.split(";").map((token) => token.trim()).filter(Boolean);
      for (const token of tokens) {
        const splitIdx = token.indexOf(": ");
        if (splitIdx < 0) {
          continue;
        }
        const label = token.slice(0, splitIdx).trim();
        const value = token.slice(splitIdx + 2).trim();
        if (!label) {
          continue;
        }
        row[label] = value;
        if (!seen.has(label)) {
          seen.add(label);
          columns.push(label);
        }
      }
    }

    rows.push(row);
  }

  return rows.length === 0 ? null : { columns, rows };
}

function isMoneyishColumn(label: string, values: string[]): boolean {
  if (/total|amount|price|cost|transport|gasoline|lodging|diem|vat|land|sea|air/i.test(label)) {
    return values.some((v) => parseSubmissionAmount(v) !== null);
  }
  const numeric = values.filter((v) => parseSubmissionAmount(v) !== null).length;
  return numeric > 0 && numeric >= Math.ceil(values.length / 2);
}

export function EApprovalSubmissionFieldDisplay({ field, duplicateApproverIds }: Props) {
  const currencyDisplay = formatSubmissionCurrencyDisplay(field);
  const primary = currencyDisplay ?? formatEApprovalFieldDisplayValue(field);
  const showSubtitle = shouldShowApproverDuplicateSubtitle(field, duplicateApproverIds);
  const subtitle = field.display_subtitle?.trim();
  const isMultiline = primary.includes("\n");

  const gridTable = field.field_type === "grid" ? parseGridDisplay(primary) : null;
  const checklistTable =
    field.field_type === "checklist_matrix" ? parseChecklistMatrixDisplay(primary) : null;
  const table = checklistTable ?? gridTable;
  const looksLikeLongText =
    field.field_type === "textarea" ||
    field.field_type === "signature" ||
    (isMultiline && field.field_type !== "grid" && field.field_type !== "checklist_matrix");

  const moneyFlags =
    gridTable?.columns.map((col) =>
      isMoneyishColumn(
        col,
        gridTable.rows.map((row) => row[col] ?? ""),
      ),
    ) ?? [];
  const showExpenseFooter = Boolean(gridTable && moneyFlags.some(Boolean));
  let labelSpan = 0;
  for (const flag of moneyFlags) {
    if (flag) break;
    labelSpan += 1;
  }
  labelSpan = Math.max(labelSpan, 1);

  return (
    <div className="space-y-1.5">
      {table ? (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
          <table className="w-full border-collapse text-[11px] leading-tight">
            <thead>
              <tr className="border-b border-border bg-muted/80">
                {table.columns.map((c) => (
                  <th
                    key={c}
                    className={cn(
                      "border border-border px-1.5 py-1.5 text-center text-[10px] font-medium text-foreground",
                      /^total$/i.test(c) && "bg-muted text-foreground",
                    )}
                  >
                    <span className="line-clamp-2 break-words">{c}</span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {table.rows.map((row, rowIndex) => (
                <tr
                  key={rowIndex}
                  className={cn(
                    "border-b border-border last:border-0",
                    rowIndex % 2 === 1 ? "bg-muted/30" : "bg-card",
                  )}
                >
                  {table.columns.map((col, colIndex) => {
                    const raw = row[col] ?? "";
                    const money = moneyFlags[colIndex];
                    const parsed = money ? parseSubmissionAmount(raw) : null;
                    return (
                      <td
                        key={col}
                        className={cn(
                          "border border-border px-1.5 py-1 align-top break-words",
                          /^total$/i.test(col) && "bg-muted/50",
                          money && "text-right tabular-nums",
                        )}
                      >
                        <span className="text-[11px]">
                          {parsed !== null
                            ? formatGridCurrencyDisplay(String(parsed))
                            : raw.trim() !== ""
                              ? raw
                              : "—"}
                        </span>
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
            {showExpenseFooter && gridTable ? (
              <tfoot>
                <tr>
                  <td
                    colSpan={labelSpan}
                    className="border border-border bg-muted px-2 py-1.5 text-center text-[10px] font-medium tracking-wide text-foreground uppercase"
                  >
                    Total expenses
                  </td>
                  {gridTable.columns.map((col, colIndex) => {
                    if (colIndex < labelSpan) return null;
                    if (!moneyFlags[colIndex]) {
                      return <td key={col} className="border border-border bg-card" />;
                    }
                    const total = gridTable.rows.reduce((sum, row) => {
                      const parsed = parseSubmissionAmount(row[col] ?? "");
                      return parsed === null ? sum : sum + parsed;
                    }, 0);
                    return (
                      <td
                        key={col}
                        className={cn(
                          "border border-border px-1.5 py-1.5 text-right text-[11px] font-medium tabular-nums",
                          /^total$/i.test(col) ? "bg-muted/80" : "bg-muted/40",
                        )}
                      >
                        {formatGridCurrencyDisplay(String(total))}
                      </td>
                    );
                  })}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>
      ) : looksLikeLongText ? (
        <div className="text-sm whitespace-pre-line break-words leading-relaxed">{primary}</div>
      ) : field.field_type === "checkbox" ? (
        <p className="text-sm">{primary}</p>
      ) : (
        <p className={cn("text-sm break-words", field.field_type === "currency" && "tabular-nums")}>
          {primary}
        </p>
      )}
      {showSubtitle && subtitle ? (
        <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p>
      ) : null}
    </div>
  );
}
