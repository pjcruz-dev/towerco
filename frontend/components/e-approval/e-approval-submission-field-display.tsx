"use client";

import {
  formatEApprovalFieldDisplayValue,
  shouldShowApproverDuplicateSubtitle,
  type EApprovalSubmissionFieldValue,
} from "@/modules/e-approval/display";
import { formatSubmissionCurrencyDisplay } from "@/modules/e-approval/submission-form-content";
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

  return (
    <div className="space-y-1.5">
      {table ? (
        <div className="overflow-x-auto rounded-lg border border-border bg-background">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-700 text-white dark:bg-slate-800">
                {table.columns.map((c) => (
                  <th key={c} className="px-2 py-2 text-left text-xs font-medium">
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
                    "border-b border-border/60 last:border-0",
                    rowIndex % 2 === 1 ? "bg-muted/20" : "bg-card",
                  )}
                >
                  {table.columns.map((col) => (
                    <td key={col} className="px-2 py-1.5 align-top break-words">
                      <span className="text-sm">{row[col] && row[col].trim() !== "" ? row[col] : "—"}</span>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
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
