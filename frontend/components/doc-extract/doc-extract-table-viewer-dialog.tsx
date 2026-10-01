"use client";

import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import type { DocExtractField, DocExtractTableColumn } from "@/modules/doc-extract/types";
import type { DocExtractTableData } from "@/modules/doc-extract/table-values";
import { stringifyDocExtractTableValue } from "@/modules/doc-extract/table-values";

type DocExtractTableViewerDialogProps = {
  open: boolean;
  filename?: string;
  field: DocExtractField | null;
  data: DocExtractTableData | null;
  canEdit: boolean;
  saving?: boolean;
  onOpenChange: (open: boolean) => void;
  onChange: (data: DocExtractTableData) => void;
  onSave: (encoded: string, columns: DocExtractTableColumn[]) => void;
};

function emptyRow(columns: DocExtractTableColumn[]): Record<string, string> {
  const row: Record<string, string> = {};
  for (const column of columns) {
    row[column.key] = "";
  }
  return row;
}

function slugColumnKey(label: string, existing: Set<string>): string {
  const base =
    label
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_|_$/g, "") || "column";
  let key = base;
  let index = 2;
  while (existing.has(key)) {
    key = `${base}_${index}`;
    index += 1;
  }
  return key;
}

export function DocExtractTableViewerDialog({
  open,
  filename,
  field,
  data,
  canEdit,
  saving = false,
  onOpenChange,
  onChange,
  onSave,
}: DocExtractTableViewerDialogProps) {
  const columns = data?.columns ?? [];
  const rows = data?.rows ?? [];

  const updateCell = (rowIndex: number, columnKey: string, value: string) => {
    if (!data) return;
    const nextRows = data.rows.map((row, index) =>
      index === rowIndex ? { ...row, [columnKey]: value } : row,
    );
    onChange({ ...data, rows: nextRows });
  };

  const addRow = () => {
    if (!data) return;
    const cols = data.columns.length > 0 ? data.columns : [{ key: "col_1", label: "Column 1", type: "text" as const }];
    onChange({
      columns: cols,
      rows: [...data.rows, emptyRow(cols)],
    });
  };

  const removeRow = (rowIndex: number) => {
    if (!data) return;
    onChange({
      ...data,
      rows: data.rows.filter((_, index) => index !== rowIndex),
    });
  };

  const addColumn = () => {
    if (!data) return;
    if (data.columns.length >= 20) return;
    const keys = new Set(data.columns.map((column) => column.key));
    const label = `Column ${data.columns.length + 1}`;
    const key = slugColumnKey(label, keys);
    const column: DocExtractTableColumn = { key, label, type: "text", description: null };
    onChange({
      columns: [...data.columns, column],
      rows: data.rows.map((row) => ({ ...row, [key]: "" })),
    });
  };

  const removeColumn = (columnIndex: number) => {
    if (!data || data.columns.length <= 1) return;
    const removed = data.columns[columnIndex];
    if (!removed) return;
    onChange({
      columns: data.columns.filter((_, index) => index !== columnIndex),
      rows: data.rows.map((row) => {
        const next = { ...row };
        delete next[removed.key];
        return next;
      }),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[90vh] w-[min(96vw,64rem)] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none">
        <DialogHeader className="border-b border-border px-6 py-4">
          <DialogTitle className="flex flex-wrap items-center gap-2">
            {field?.label ?? "Table"}
            <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-xs font-normal text-muted-foreground">
              {rows.length} rows × {columns.length} columns
            </span>
          </DialogTitle>
          <p className="mt-1 text-sm text-muted-foreground">{filename}</p>
          <p className="mt-1 text-xs text-muted-foreground">
            Column headers stay fixed from the field definition. Edit cell values, or add/remove rows
            and columns as needed.
          </p>
        </DialogHeader>

        <DialogBody className="min-h-0 flex-1 space-y-3 overflow-auto px-6 py-4">
          {canEdit ? (
            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" onClick={addRow}>
                <Plus className="size-3.5" />
                Add row
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={columns.length >= 20}
                onClick={addColumn}
              >
                <Plus className="size-3.5" />
                Add column
              </Button>
            </div>
          ) : null}

          {columns.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border px-4 py-10 text-center">
              <p className="text-sm text-muted-foreground">No columns yet.</p>
              {canEdit ? (
                <Button type="button" variant="outline" size="sm" className="mt-3" onClick={addColumn}>
                  <Plus className="size-3.5" />
                  Add first column
                </Button>
              ) : null}
            </div>
          ) : (
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="min-w-full text-left text-[13px]">
                <thead className="border-b border-border bg-muted text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="w-10 px-2 py-2 text-center">#</th>
                    {columns.map((column, columnIndex) => (
                      <th key={column.key} className="min-w-[8rem] px-3 py-2">
                        <div className="flex items-center gap-1">
                          <span className="whitespace-nowrap font-medium text-foreground">
                            {column.label}
                          </span>
                          {canEdit ? (
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="h-7 w-7 shrink-0 px-0 text-muted-foreground hover:text-destructive"
                              disabled={columns.length <= 1}
                              onClick={() => removeColumn(columnIndex)}
                              aria-label={`Remove column ${column.label}`}
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                          ) : null}
                        </div>
                      </th>
                    ))}
                    {canEdit ? <th className="w-12 px-2 py-2" /> : null}
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr>
                      <td
                        colSpan={columns.length + (canEdit ? 2 : 1)}
                        className="px-4 py-8 text-center text-sm text-muted-foreground"
                      >
                        No rows yet.
                        {canEdit ? " Use Add row to capture missing lines from the document." : null}
                      </td>
                    </tr>
                  ) : (
                    rows.map((row, rowIndex) => (
                      <tr key={rowIndex} className="border-b border-border last:border-0">
                        <td className="px-2 py-2 text-center text-xs tabular-nums text-muted-foreground">
                          {rowIndex + 1}
                        </td>
                        {columns.map((column) => (
                          <td key={column.key} className="px-3 py-2 text-foreground">
                            {canEdit ? (
                              <Input
                                className="h-8"
                                value={row[column.key] ?? ""}
                                onChange={(event) => updateCell(rowIndex, column.key, event.target.value)}
                              />
                            ) : (
                              row[column.key] || "—"
                            )}
                          </td>
                        ))}
                        {canEdit ? (
                          <td className="px-2 py-2">
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="h-8 w-8 px-0 text-muted-foreground hover:text-destructive"
                              onClick={() => removeRow(rowIndex)}
                              aria-label={`Remove row ${rowIndex + 1}`}
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                          </td>
                        ) : null}
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}
        </DialogBody>

        <DialogFooter className="border-t border-border px-6 py-4">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
          {canEdit && data ? (
            <Button
              type="button"
              disabled={saving}
              onClick={() => onSave(stringifyDocExtractTableValue(data), data.columns)}
            >
              Save table
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
