"use client";

import { useRef, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, Plus, Trash2, Undo2 } from "lucide-react";

import { fetchEApprovalSettingsPublic } from "@/lib/api/modules/e-approval-api";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { EApprovalGridCell, type GridCellMeta } from "@/components/e-approval/e-approval-grid-cell";
import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import {
  columnKey,
  emptyGridValue,
  gridColumnAsSelectField,
  parseGridColumnDefs,
  parseGridValue,
  serializeGridValue,
  type GridColumnDef,
  type GridFieldValue,
} from "@/modules/e-approval/field-options";
import {
  activityLinkChoices,
  applyGridRowAmountFormula,
  expenseCellApplies,
  expenseColumnVisible,
  expenseLineVatParts,
  expenseRowNeeds,
  formatGridCurrencyDisplay,
  expenseGridKeepsTypedAmount,
  isHighlightedTotalColumn,
  leadingNonSummableColSpan,
  lodgingNightsOnRow,
  perDiemDaysOnRow,
  perDiemLineTotal,
  rowIsLodging,
  sumGridColumnValues,
  summableGridColumnIndexes,
} from "@/modules/e-approval/grid-row-formulas";
import { placeReviewTotals, type ComposeReviewTotal } from "@/modules/e-approval/form-compose-review";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput, EApprovalOpenCashAdvance } from "@/modules/e-approval/types";

function expenseLineSplitDisplay(
  columns: GridColumnDef[],
  row: Record<string, string>,
  kind: "non" | "net",
): string {
  const blank = Object.values(row).every((value) => String(value ?? "").trim() === "");
  if (blank) {
    return "—";
  }
  const parts = expenseLineVatParts(columns, row);
  const amount = kind === "non" ? (parts?.nonVatable ?? 0) : (parts?.vatableNet ?? 0);
  return formatGridCurrencyDisplay(amount.toFixed(2));
}

function gridColumnTitle(column: GridColumnDef): string {
  if (/^days$/i.test(column.label.trim())) {
    return "Each day on this per diem line, including the first day and the last day.";
  }
  if (/^forecast amount$/i.test(column.label.trim())) {
    return "Copied from the cash advance. Compare the actual Amount with this figure.";
  }
  if (/^amount$/i.test(column.label.trim())) {
    return "Actual amount for this line. This is the figure that totals.";
  }

  return column.label;
}

/** Liquidation shows the cash-advance figure beside the amount the requestor enters. */
function liquidationMoneyRole(
  columns: GridColumnDef[],
  column: GridColumnDef,
): "forecast" | "actual" | null {
  const hasForecast = columns.some((item) => /^forecast amount$/i.test(item.label.trim()));
  const hasAmount = columns.some((item) => /^amount$/i.test(item.label.trim()));
  if (!hasForecast || !hasAmount) {
    return null;
  }
  const label = column.label.trim();
  if (/^forecast amount$/i.test(label)) {
    return "forecast";
  }
  if (/^amount$/i.test(label)) {
    return "actual";
  }
  return null;
}

/** Keep typed values visible. The grid scrolls sideways instead of crushing the cell. */
function gridColumnMinWidth(column: GridColumnDef): string {
  const label = column.label.trim();
  if (column.type === "date_range") {
    return column.include_time === false ? "11.5rem" : "20rem";
  }
  if (column.type === "date") {
    return "9.5rem";
  }
  if (column.master_data_key === "sites" || /site/i.test(label)) {
    return "12rem";
  }
  if (column.type === "currency") {
    return "8rem";
  }
  if (column.type === "number") {
    return "5.75rem";
  }
  if (column.type === "boolean") {
    return "4.5rem";
  }
  if (column.type === "textarea") {
    return "11rem";
  }
  if (column.type === "select" && /vat treatment/i.test(label)) {
    return "8.75rem";
  }
  if (column.type === "select") {
    return "12rem";
  }
  if (/^or\s*no\.?$/i.test(label)) {
    return "6rem";
  }

  return "10rem";
}

type PerDiemNote = { text: string; error: boolean };

function perDiemAmountNote(
  columns: GridColumnDef[],
  row: Record<string, string>,
  column: GridColumnDef,
  amountIsCalculated: boolean,
  maxPerDay: number | null,
): PerDiemNote | null {
  if (!expenseRowNeeds(columns, row).perDiem) {
    return null;
  }
  const label = column.label.trim();
  if (expenseGridKeepsTypedAmount(columns)) {
    if (!/^amount$/i.test(label)) {
      return null;
    }
    const amountIndex = columns.findIndex((item) => /^amount$/i.test(item.label.trim()));
    const amount = Math.max(0, parseSubmissionAmount(row[columnKey(amountIndex, columns.length)] ?? "") ?? 0);
    const days = perDiemDaysOnRow(columns, row);
    if (days === null || days < 1) {
      return amount > 0 ? { text: "Per diem needs the date on the line.", error: true } : { text: "Per day", error: false };
    }
    const perDay = amount / days;
    if (maxPerDay !== null && perDay > maxPerDay + 0.001) {
      return {
        text: `Per diem cannot exceed ${formatGridCurrencyDisplay(maxPerDay.toFixed(2))} per day.`,
        error: true,
      };
    }
    if (days === 1) {
      return { text: "Per day", error: false };
    }

    return {
      text: `${days} days · ${formatGridCurrencyDisplay(perDay.toFixed(2))} per day`,
      error: false,
    };
  }
  const showsDailyRate = amountIsCalculated ? /^rate$/i.test(label) : /^amount$/i.test(label);
  if (!showsDailyRate) {
    return null;
  }

  const dailyIndex = columns.findIndex((item) =>
    (amountIsCalculated ? /^rate$/i : /^amount$/i).test(item.label.trim()),
  );
  const rate = Math.max(0, parseSubmissionAmount(row[columnKey(dailyIndex, columns.length)] ?? "") ?? 0);
  if (rate <= 0) {
    return amountIsCalculated ? { text: "Per day", error: false } : null;
  }
  const days = perDiemDaysOnRow(columns, row);
  if (days === null || days < 1) {
    return { text: "Per diem needs the date on the line.", error: true };
  }
  if (maxPerDay !== null && rate > maxPerDay + 0.001) {
    return {
      text: `Per diem cannot exceed ${formatGridCurrencyDisplay(maxPerDay.toFixed(2))} per day.`,
      error: true,
    };
  }
  if (amountIsCalculated) {
    return { text: "Per day", error: false };
  }
  const total = perDiemLineTotal(rate, days);
  const dayLabel = days === 1 ? "day" : "days";

  return { text: `${days} ${dayLabel} · ${formatGridCurrencyDisplay(total.toFixed(2))}`, error: false };
}

function hotelAmountNote(
  columns: GridColumnDef[],
  row: Record<string, string>,
  column: GridColumnDef,
  amountIsCalculated: boolean,
  maxRate: number | null,
): PerDiemNote | null {
  if (!rowIsLodging(columns, row)) {
    return null;
  }
  const label = column.label.trim();
  if (expenseGridKeepsTypedAmount(columns)) {
    if (!/^amount$/i.test(label)) {
      return null;
    }
    const amountIndex = columns.findIndex((item) => /^amount$/i.test(item.label.trim()));
    const amount = Math.max(0, parseSubmissionAmount(row[columnKey(amountIndex, columns.length)] ?? "") ?? 0);
    const nights = lodgingNightsOnRow(columns, row);
    if (nights === null || nights < 1) {
      return amount > 0 ? { text: "Lodging needs the date on the line.", error: true } : { text: "Per day", error: false };
    }
    const perNight = amount / nights;
    if (maxRate !== null && perNight > maxRate + 0.001) {
      return {
        text: `Lodging cannot exceed ${formatGridCurrencyDisplay(maxRate.toFixed(2))} per day.`,
        error: true,
      };
    }
    const nightLabel = nights === 1 ? "day" : "days";

    return {
      text: `${nights} ${nightLabel} · ${formatGridCurrencyDisplay(amount.toFixed(2))}`,
      error: false,
    };
  }
  const showsRate = amountIsCalculated ? /^rate$/i.test(label) : /^amount$/i.test(label);
  if (!showsRate) {
    return null;
  }
  const rateIndex = columns.findIndex((item) =>
    (amountIsCalculated ? /^rate$/i : /^amount$/i).test(item.label.trim()),
  );
  const rate = Math.max(0, parseSubmissionAmount(row[columnKey(rateIndex, columns.length)] ?? "") ?? 0);
  if (rate <= 0) {
    return null;
  }
  const nights = lodgingNightsOnRow(columns, row);
  if (nights === null || nights < 1) {
    return { text: "Lodging needs the date on the line.", error: true };
  }
  if (maxRate !== null && rate > maxRate + 0.001) {
    return {
      text: `Lodging cannot exceed ${formatGridCurrencyDisplay(maxRate.toFixed(2))}.`,
      error: true,
    };
  }
  const total = perDiemLineTotal(rate, nights);
  const nightLabel = nights === 1 ? "day" : "days";

  return { text: `${nights} ${nightLabel} · ${formatGridCurrencyDisplay(total.toFixed(2))}`, error: false };
}

function gridHasNoEntries(rows: Array<Record<string, string>>): boolean {
  return rows.every((row) => Object.values(row).every((cell) => cell.trim() === ""));
}

function cashAdvanceExpenseLines(
  lists: Array<[readonly unknown[], EApprovalOpenCashAdvance[] | undefined]>,
  documentNo: string,
): string {
  const doc = documentNo.trim();
  if (doc === "") {
    return "";
  }
  for (const [, items] of lists) {
    const match = items?.find((item) => item.document_no === doc);
    const lines = match?.prefill_values?.expense_lines?.trim() ?? "";
    if (lines !== "") {
      return lines;
    }
  }
  return "";
}

function PerDiemAmountCell({ note, children }: { note: PerDiemNote | null; children: ReactNode }) {
  return (
    <>
      {children}
      {note ? (
        <p
          className={cn(
            "mt-0.5 px-1 text-right text-[10px] leading-tight",
            note.error ? "text-destructive" : "text-muted-foreground",
          )}
        >
          {note.text}
        </p>
      ) : null}
    </>
  );
}

type Props = {
  /** @deprecated Use field — column definitions include cell types. */
  columns?: string[];
  field?: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  /** Larger inputs and spacing for requestor submit dialogs. */
  density?: "compact" | "comfortable";
  allowRemoteLookups?: boolean;
  allFields?: EApprovalFormFieldInput[];
  allValues?: Record<string, string>;
  /** Money totals placed under the matching expense columns. */
  summaryTotals?: Array<ComposeReviewTotal & { required?: boolean }>;
  summaryErrors?: string[];
};

export function EApprovalGridField({
  columns: columnsLegacy,
  field,
  value,
  onChange,
  disabled,
  density = "compact",
  allowRemoteLookups = true,
  allFields = [],
  allValues = {},
  summaryTotals = [],
  summaryErrors = [],
}: Props) {
  const parsedColumns: GridColumnDef[] = field
    ? parseGridColumnDefs(field)
    : (columnsLegacy ?? []).map((label) => ({ label, type: "text" as const }));
  const amountIsCalculated =
    parsedColumns.some((column) => /^quantity$/i.test(column.label)) &&
    parsedColumns.some((column) => /^rate$/i.test(column.label));
  const comparesForecast = parsedColumns.some((column) => /^forecast amount$/i.test(column.label.trim()));
  const keepsTypedAmount = expenseGridKeepsTypedAmount(parsedColumns);
  const columnDefs: GridColumnDef[] = parsedColumns.map((column) => {
    if (column.linked_grid) {
      return {
        ...column,
        choices: activityLinkChoices(allFields, allValues, column.linked_grid),
      };
    }
    if (comparesForecast && /^forecast amount$/i.test(column.label.trim())) {
      return { ...column, locked: true };
    }
    if (keepsTypedAmount && /^amount$/i.test(column.label.trim())) {
      return { ...column, locked: false };
    }
    if (amountIsCalculated && !keepsTypedAmount && /^amount$/i.test(column.label.trim())) {
      return { ...column, locked: true };
    }
    if (field?.name === "expense_lines" && /^days$/i.test(column.label.trim())) {
      return { ...column, locked: true };
    }
    return column;
  });

  const columns = columnDefs.map((c) => c.label);
  const grid = parseGridValue(value, columns.length);
  const expenseTypeColumn = columnDefs.find((column) => /^expense type$/i.test(column.label.trim()));
  const { choices: expenseTypeChoices } = useEApprovalFieldChoices(
    gridColumnAsSelectField(expenseTypeColumn ?? { label: "Expense type", type: "select" }),
    Boolean(expenseTypeColumn),
    allowRemoteLookups,
  );
  const flagColumns: GridColumnDef[] = columnDefs.map((column) => {
    if (!expenseTypeColumn || column !== expenseTypeColumn || expenseTypeChoices.length === 0) {
      return column;
    }
    if (column.choices && column.choices.length > 0) {
      return column;
    }

    return { ...column, choices: expenseTypeChoices };
  });
  const visibleColumns = columnDefs
    .map((column, index) => ({ column, index, synthetic: null as "non" | "net" | null }))
    .filter(({ column }) => expenseColumnVisible(flagColumns, grid.rows, column));
  const displayColumns = [...visibleColumns];
  if (field?.name === "expense_lines" && expenseLineVatParts(columnDefs, {}) !== null) {
    const extras: Array<{ label: string; synthetic: "non" | "net" }> = [];
    if (!columnDefs.some((column) => /^non-?vatable$/i.test(column.label.trim()))) {
      extras.push({ label: "Non-VATable", synthetic: "non" });
    }
    if (!columnDefs.some((column) => /net of vat/i.test(column.label.trim()))) {
      extras.push({ label: "VATable, net of VAT", synthetic: "net" });
    }
    const vatAt = displayColumns.findIndex(({ column }) => /^vat$/i.test(column.label.trim()));
    const insertAt = vatAt >= 0 ? vatAt + 1 : displayColumns.length;
    extras.forEach((extra, offset) => {
      displayColumns.splice(insertAt + offset, 0, {
        column: { label: extra.label, type: "currency", locked: true },
        index: -1 - offset,
        synthetic: extra.synthetic,
      });
    });
  }
  const summaryRows =
    summaryTotals.length > 0
      ? placeReviewTotals(
          displayColumns.map(({ column }) => column.label),
          summaryTotals,
        )
      : [];
  // Wide expense grids stay dense even in comfortable compose dialogs.
  const comfortable = false;
  const touchComfortable = density === "comfortable";
  const summable = summableGridColumnIndexes(visibleColumns.map((entry) => entry.column));
  const perDiemPolicy = useQuery({
    queryKey: ["e-approval", "settings", "public"],
    queryFn: fetchEApprovalSettingsPublic,
    enabled: field?.name === "expense_lines",
    staleTime: 60_000,
  });
  const perDiemMaxRaw = perDiemPolicy.data?.per_diem_max_per_day ?? "";
  const perDiemMax = perDiemMaxRaw.trim() === "" ? null : Number(perDiemMaxRaw);
  const perDiemMaxPerDay = perDiemMax !== null && Number.isFinite(perDiemMax) ? perDiemMax : null;
  const hotelMaxRaw = perDiemPolicy.data?.hotel_accommodation_max ?? "";
  const hotelMaxParsed = hotelMaxRaw.trim() === "" ? null : Number(hotelMaxRaw);
  const hotelMax = hotelMaxParsed !== null && Number.isFinite(hotelMaxParsed) ? hotelMaxParsed : null;
  const showTotalsFooter =
    field?.name !== "expense_lines" && summable.some(Boolean) && grid.rows.length > 0;
  const labelColSpan = leadingNonSummableColSpan(summable);

  const commit = (next: GridFieldValue) => {
    onChange(serializeGridValue(next));
  };
  const deletedGrids = useRef<GridFieldValue[]>([]);
  const [undoCount, setUndoCount] = useState(0);

  const rememberDeletedGrid = () => {
    deletedGrids.current = [...deletedGrids.current, grid].slice(-30);
    setUndoCount(deletedGrids.current.length);
  };

  const undoDelete = () => {
    const previous = deletedGrids.current.pop();
    setUndoCount(deletedGrids.current.length);
    if (previous) {
      commit(previous);
    }
  };
  const queryClient = useQueryClient();
  const forecastLines = comparesForecast
    ? cashAdvanceExpenseLines(
        queryClient.getQueriesData<EApprovalOpenCashAdvance[]>({
          queryKey: ["e-approval", "cash-advances", "open"],
        }),
        allValues.cash_advance_document_no ?? "",
      )
    : "";
  const canRestoreForecast = forecastLines !== "" && gridHasNoEntries(grid.rows);

  const restoreForecastLines = () => {
    if (!canRestoreForecast) {
      return;
    }
    rememberDeletedGrid();
    commit(parseGridValue(forecastLines, columns.length));
  };

  const updateCell = (rowIndex: number, colIndex: number, cellValue: string, meta?: GridCellMeta) => {
    const source = columnDefs[colIndex];
    const rows = grid.rows.map((row, i) => {
      if (i !== rowIndex) {
        return row;
      }
      const nextRow = { ...row, [columnKey(colIndex, columns.length)]: cellValue };
      if (source && typeof meta?.address === "string") {
        columnDefs.forEach((column, index) => {
          if (column.filled_from === source.label) {
            nextRow[columnKey(index, columns.length)] = meta.address ?? "";
          }
        });
      }
      if (meta?.vatable !== undefined || meta?.per_diem !== undefined) {
        const vatIndex = columnDefs.findIndex((column) => column.label.trim().toLowerCase() === "vat treatment");
        if (vatIndex >= 0) {
          const nonVatable = meta.per_diem === true || meta.vatable === false;
          nextRow[columnKey(vatIndex, columns.length)] = nonVatable ? "non_vatable" : "vatable";
        }
      }
      return nextRow;
    });
    let next: GridFieldValue = { rows };
    if (field) {
      const serialized = serializeGridValue(next);
      const withFormulas = applyGridRowAmountFormula(field, serialized);
      next = parseGridValue(withFormulas, columns.length);
    }
    commit(next);
  };

  const addRow = () => {
    commit({ rows: [...grid.rows, emptyGridValue(columns.length).rows[0] ?? {}] });
  };

  const duplicatePreviousRow = () => {
    if (grid.rows.length === 0) {
      addRow();
      return;
    }

    const previous = grid.rows[grid.rows.length - 1] ?? {};
    const copy: Record<string, string> = {};
    for (let i = 0; i < columns.length; i += 1) {
      const key = columnKey(i, columns.length);
      copy[key] = previous[key] ?? "";
    }

    commit({ rows: [...grid.rows, copy] });
  };

  const removeRow = (rowIndex: number) => {
    rememberDeletedGrid();
    if (grid.rows.length <= 1) {
      commit(emptyGridValue(columns.length));
      return;
    }
    commit({ rows: grid.rows.filter((_, i) => i !== rowIndex) });
  };

  if (columns.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        This grid has no columns configured. Ask an administrator to update the form definition.
      </p>
    );
  }

  return (
    <div className={cn("w-full min-w-0 space-y-1.5", touchComfortable && "-mx-1 sm:mx-0")}>
      <div className="w-full rounded-xl border border-border bg-card shadow-sm">
        {comparesForecast ? (
          <p className="border-b border-border px-3 py-2 text-[11px] text-muted-foreground">
            Type the actual in <span className="font-medium text-foreground">Amount</span>.{" "}
            <span className="font-medium text-foreground">Forecast amount</span> is the cash advance figure.
          </p>
        ) : null}
        <div className="w-full overflow-x-auto">
        <table className="w-max min-w-full border-collapse text-[11px] leading-tight">
          <thead>
            <tr className="border-b border-border bg-muted/80">
              {displayColumns.map(({ column: col, index }) => {
                const moneyRole = liquidationMoneyRole(columnDefs, col);
                return (
                <th
                  key={`${col.label}-h-${index}`}
                  title={gridColumnTitle(col)}
                  style={{ minWidth: gridColumnMinWidth(col) }}
                  className={cn(
                    "border border-border px-1.5 py-1.5 text-center text-[10px] font-medium text-foreground",
                    isHighlightedTotalColumn(col) && "bg-muted text-foreground",
                    moneyRole === "forecast" && "bg-muted text-muted-foreground",
                    moneyRole === "actual" && "bg-primary/15 text-foreground",
                  )}
                >
                  {moneyRole === "forecast" ? (
                    <span className="mb-0.5 block text-[9px] font-medium uppercase tracking-wide">
                      Reference
                    </span>
                  ) : null}
                  {moneyRole === "actual" ? (
                    <span className="mb-0.5 block text-[9px] font-medium uppercase tracking-wide text-primary">
                      Actual
                    </span>
                  ) : null}
                  <span className="line-clamp-2 break-words">{col.label}</span>
                </th>
                );
              })}
              <th
                className="w-9 shrink-0 border border-border px-0.5 py-1.5"
                aria-label="Row actions"
              />
            </tr>
          </thead>
          <tbody>
            {grid.rows.map((row, rowIndex) => (
              <tr
                key={rowIndex}
                className={cn(
                  "border-b border-border last:border-0",
                  rowIndex % 2 === 1 ? "bg-muted/30" : "bg-card",
                )}
              >
                {displayColumns.map(({ column: colDef, index: colIndex, synthetic }) => {
                  const moneyRole = liquidationMoneyRole(columnDefs, colDef);
                  return (
                  <td
                    key={`${rowIndex}-${colDef.label}-${colIndex}`}
                    style={{ minWidth: gridColumnMinWidth(colDef) }}
                    className={cn(
                      "border border-border px-1 py-1 align-middle",
                      (isHighlightedTotalColumn(colDef) || synthetic) && "bg-muted/50",
                      synthetic && "text-right tabular-nums",
                      moneyRole === "forecast" && "bg-muted/70",
                      moneyRole === "actual" && "bg-primary/10",
                    )}
                  >
                    {synthetic ? (
                      <span className="block px-1 text-right text-[11px] tabular-nums">
                        {expenseLineSplitDisplay(flagColumns, row, synthetic)}
                      </span>
                    ) : expenseCellApplies(flagColumns, row, colDef) ? (
                      <PerDiemAmountCell
                        note={
                          perDiemAmountNote(
                            flagColumns,
                            row,
                            colDef,
                            amountIsCalculated,
                            perDiemMaxPerDay,
                          ) ??
                          hotelAmountNote(flagColumns, row, colDef, amountIsCalculated, hotelMax)
                        }
                      >
                        <EApprovalGridCell
                          column={colDef}
                          disabled={disabled || colDef.locked === true || isHighlightedTotalColumn(colDef)}
                          comfortable={comfortable}
                          allowRemoteLookups={allowRemoteLookups}
                          subsidiary={allValues.subsidiary ?? ""}
                          value={row[columnKey(colIndex, columns.length)] ?? ""}
                          onChange={(cellValue, meta) => updateCell(rowIndex, colIndex, cellValue, meta)}
                        />
                      </PerDiemAmountCell>
                    ) : (
                      <span className="block px-1 text-center text-muted-foreground">—</span>
                    )}
                  </td>
                  );
                })}
                <td className="border border-border px-0.5 py-1 align-middle">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 text-muted-foreground"
                    disabled={disabled}
                    onClick={() => removeRow(rowIndex)}
                    aria-label="Remove row"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
          {showTotalsFooter || summaryRows.length > 0 ? (
            <tfoot>
              {summaryRows.map((totalRow, rowIndex) => (
                <tr key={`summary-${rowIndex}`} className="border-t border-border bg-muted/40">
                  {totalRow.map((total, cellIndex) => {
                    const required = summaryTotals.find((item) => item.label === total?.label)?.required;
                    const moneyRole = displayColumns[cellIndex]
                      ? liquidationMoneyRole(columnDefs, displayColumns[cellIndex].column)
                      : null;
                    return (
                      <td
                        key={`summary-${rowIndex}-${cellIndex}`}
                        className={cn(
                          "border border-border px-1.5 py-1.5 text-right align-bottom",
                          moneyRole === "forecast" && "bg-muted/70",
                          moneyRole === "actual" && "bg-primary/10",
                        )}
                      >
                        {total ? (
                          <>
                            <div className="text-[10px] font-medium leading-tight text-foreground">
                              {total.label}
                              {required ? <span className="text-destructive"> *</span> : null}
                            </div>
                            <div className="text-xs font-medium tabular-nums text-foreground">{total.value}</div>
                          </>
                        ) : null}
                      </td>
                    );
                  })}
                  <td className="border border-border bg-muted/40" />
                </tr>
              ))}
              {showTotalsFooter ? (
              <tr className="border-t border-border">
                <td
                  colSpan={labelColSpan}
                  className="border border-border bg-muted px-2 py-1.5 text-center text-[10px] font-medium tracking-wide text-foreground uppercase"
                >
                  Total expenses
                </td>
                {visibleColumns.map(({ column: colDef, index: colIndex }, visibleIndex) => {
                  if (visibleIndex < labelColSpan) {
                    return null;
                  }
                  if (!summable[visibleIndex]) {
                    return (
                      <td
                        key={`foot-${colIndex}`}
                        className="border border-border bg-card px-1.5 py-1.5"
                      />
                    );
                  }
                  const total = sumGridColumnValues(grid.rows, colIndex, columns.length);
                  return (
                    <td
                      key={`foot-${colIndex}`}
                      className={cn(
                        "border border-border px-1.5 py-1.5 text-right text-[11px] font-medium tabular-nums text-foreground",
                        isHighlightedTotalColumn(colDef)
                          ? "bg-muted/80"
                          : "bg-muted/40",
                      )}
                    >
                      {formatGridCurrencyDisplay(String(total))}
                    </td>
                  );
                })}
                <td className="border border-border bg-muted/40" />
              </tr>
              ) : null}
            </tfoot>
          ) : null}
        </table>
        {summaryErrors.map((message) => (
          <p key={message} className="px-3 py-1 text-right text-xs text-destructive">
            {message}
          </p>
        ))}
        <div className="flex flex-wrap items-center gap-1.5 border-t border-border px-2 py-1.5">
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="h-7 px-2 text-xs"
            disabled={disabled}
            onClick={addRow}
          >
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add row
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="h-7 px-2 text-xs"
            disabled={disabled || grid.rows.length === 0}
            onClick={duplicatePreviousRow}
          >
            <Copy className="mr-1 h-3.5 w-3.5" />
            Duplicate previous row
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="h-7 px-2 text-xs"
            disabled={disabled || undoCount === 0}
            onClick={undoDelete}
            title="Brings back the lines removed with the trash button. This lasts until you leave the page."
          >
            <Undo2 className="mr-1 h-3.5 w-3.5" />
            {undoCount > 1 ? `Undo delete (${undoCount})` : "Undo delete"}
          </Button>
          {canRestoreForecast ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="h-7 px-2 text-xs"
              disabled={disabled}
              onClick={restoreForecastLines}
              title="Puts the cash advance forecast lines back into this table."
            >
              <Undo2 className="mr-1 h-3.5 w-3.5" />
              Restore cash advance lines
            </Button>
          ) : null}
        </div>
        </div>
      </div>
    </div>
  );
}
