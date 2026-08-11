"use client";

import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { MatrixAxisOption } from "@/modules/e-approval/field-matrix";

type Props = {
  rows: MatrixAxisOption[];
  columns: MatrixAxisOption[];
  rowNotes?: boolean;
  rowNotesLabel?: string;
  onChange: (next: {
    rows: MatrixAxisOption[];
    columns: MatrixAxisOption[];
    row_notes?: boolean;
    row_notes_label?: string;
  }) => void;
  disabled?: boolean;
};

function slugifyValue(label: string, fallback: string): string {
  const slug = label
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  return slug !== "" ? slug.slice(0, 64) : fallback;
}

function uniqueValue(base: string, used: Set<string>): string {
  if (!used.has(base)) {
    return base;
  }
  let n = 2;
  while (used.has(`${base}_${n}`)) {
    n += 1;
  }

  return `${base}_${n}`;
}

export function EApprovalMatrixOptionsEditor({
  rows,
  columns,
  rowNotes = false,
  rowNotesLabel = "Notes",
  onChange,
  disabled,
}: Props) {
  const updateRow = (index: number, patch: Partial<MatrixAxisOption>) => {
    const next = [...rows];
    const current = next[index];
    if (!current) {
      return;
    }
    next[index] = { ...current, ...patch };
    onChange({ rows: next, columns, row_notes: rowNotes, row_notes_label: rowNotesLabel });
  };

  const updateColumn = (index: number, patch: Partial<MatrixAxisOption>) => {
    const next = [...columns];
    const current = next[index];
    if (!current) {
      return;
    }
    next[index] = { ...current, ...patch };
    onChange({ rows, columns: next, row_notes: rowNotes, row_notes_label: rowNotesLabel });
  };

  const addRow = () => {
    const used = new Set(rows.map((r) => r.value));
    const n = rows.length + 1;
    const label = `${String.fromCharCode(64 + Math.min(n, 26))}. Item ${n}`;
    onChange({
      rows: [...rows, { value: uniqueValue(slugifyValue(label, `row_${n}`), used), label }],
      columns,
      row_notes: rowNotes,
      row_notes_label: rowNotesLabel,
    });
  };

  const removeRow = (index: number) => {
    if (rows.length <= 1) {
      return;
    }
    onChange({
      rows: rows.filter((_, i) => i !== index),
      columns,
      row_notes: rowNotes,
      row_notes_label: rowNotesLabel,
    });
  };

  const addColumn = () => {
    const used = new Set(columns.map((c) => c.value));
    const n = columns.length + 1;
    const label = `Option ${n}`;
    onChange({
      rows,
      columns: [...columns, { value: uniqueValue(slugifyValue(label, `col_${n}`), used), label }],
      row_notes: rowNotes,
      row_notes_label: rowNotesLabel,
    });
  };

  const removeColumn = (index: number) => {
    if (columns.length <= 2) {
      return;
    }
    onChange({
      rows,
      columns: columns.filter((_, i) => i !== index),
      row_notes: rowNotes,
      row_notes_label: rowNotesLabel,
    });
  };

  return (
    <div className="min-w-0 space-y-3">
      <div className="space-y-2">
        <div className="flex items-center justify-between gap-2">
          <Label>Rows</Label>
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={addRow}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add row
          </Button>
        </div>
        <ul className="min-w-0 space-y-2">
          {rows.map((row, index) => (
            <li
              key={`matrix-row-${index}`}
              className="flex min-w-0 items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 p-2"
            >
              <Input
                disabled={disabled}
                value={row.label}
                onChange={(e) => updateRow(index, { label: e.target.value })}
                placeholder="Row label"
                className="h-8 min-w-0 flex-1 text-sm"
              />
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="h-8 w-8 shrink-0 text-destructive"
                disabled={disabled || rows.length <= 1}
                onClick={() => removeRow(index)}
                aria-label="Remove row"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </li>
          ))}
        </ul>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between gap-2">
          <Label>Columns</Label>
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={addColumn}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add
          </Button>
        </div>
        <p className="text-[11px] text-muted-foreground">Default Yes / No. Keep at least two columns.</p>
        <ul className="min-w-0 space-y-2">
          {columns.map((column, index) => (
            <li
              key={`matrix-col-${index}`}
              className="flex min-w-0 items-center gap-1.5 rounded-lg border border-border/60 bg-muted/20 p-2"
            >
              <Input
                disabled={disabled}
                value={column.label}
                onChange={(e) => updateColumn(index, { label: e.target.value })}
                placeholder="Column label"
                className="h-8 min-w-0 flex-1 text-sm"
              />
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="h-8 w-8 shrink-0 text-destructive"
                disabled={disabled || columns.length <= 2}
                onClick={() => removeColumn(index)}
                aria-label="Remove column"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </li>
          ))}
        </ul>
      </div>

      <div className="space-y-2 rounded-lg border border-border/60 bg-muted/20 p-2">
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            disabled={disabled}
            checked={rowNotes}
            onCheckedChange={(v) =>
              onChange({
                rows,
                columns,
                row_notes: v === true,
                row_notes_label: rowNotesLabel,
              })
            }
            className="size-4"
          />
          Per-row notes
        </label>
        {rowNotes ? (
          <div className="space-y-1">
            <Label className="text-[11px]">Notes column label</Label>
            <Input
              disabled={disabled}
              value={rowNotesLabel}
              onChange={(e) =>
                onChange({
                  rows,
                  columns,
                  row_notes: true,
                  row_notes_label: e.target.value,
                })
              }
              placeholder="Notes / m (Approx.)"
              className="h-8 text-sm"
            />
          </div>
        ) : (
          <p className="text-[11px] text-muted-foreground">
            Enable for Yes/No rows that also need a free-text line (e.g. approx. meters).
          </p>
        )}
      </div>
    </div>
  );
}
