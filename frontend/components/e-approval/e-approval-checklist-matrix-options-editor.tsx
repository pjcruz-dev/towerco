"use client";

import { useQuery } from "@tanstack/react-query";
import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalMasterDataSets } from "@/lib/api/modules/e-approval-api";
import {
  CHECKLIST_MATRIX_COLUMN_TYPES,
  checklistMatrixColumnType,
  type ChecklistMatrixAxisOption,
  type ChecklistMatrixColumnDef,
  type ChecklistMatrixColumnType,
} from "@/modules/e-approval/field-checklist-matrix";
import { GRID_COLUMN_TYPE_LABELS } from "@/modules/e-approval/field-options";

type Props = {
  rows: ChecklistMatrixAxisOption[];
  columns: ChecklistMatrixColumnDef[];
  rowSelectLabel?: string;
  onChange: (next: {
    rows: ChecklistMatrixAxisOption[];
    columns: ChecklistMatrixColumnDef[];
    row_select_label?: string;
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

export function EApprovalChecklistMatrixOptionsEditor({
  rows,
  columns,
  rowSelectLabel = "Cost Application",
  onChange,
  disabled,
}: Props) {
  const setsQuery = useQuery({
    queryKey: ["e-approval", "master-data-sets"],
    queryFn: fetchEApprovalMasterDataSets,
    staleTime: 60_000,
  });
  const masterSets = setsQuery.data ?? [];

  const updateRow = (index: number, patch: Partial<ChecklistMatrixAxisOption>) => {
    const next = [...rows];
    const current = next[index];
    if (!current) {
      return;
    }
    next[index] = { ...current, ...patch };
    onChange({ rows: next, columns, row_select_label: rowSelectLabel });
  };

  const updateColumn = (index: number, patch: Partial<ChecklistMatrixColumnDef>) => {
    const next = [...columns];
    const current = next[index];
    if (!current) {
      return;
    }
    const merged: ChecklistMatrixColumnDef = { ...current, ...patch };
    const type = checklistMatrixColumnType(merged);
    merged.type = type;
    if (type === "select") {
      if (!merged.master_data_key && !(merged.choices && merged.choices.length > 0)) {
        merged.choices = [{ value: "a", label: "Option A" }];
      }
    } else {
      delete merged.master_data_key;
      delete merged.choices;
    }
    next[index] = merged;
    onChange({ rows, columns: next, row_select_label: rowSelectLabel });
  };

  const addRow = () => {
    const used = new Set(rows.map((r) => r.value));
    const n = rows.length + 1;
    const label = `Row ${n}`;
    onChange({
      rows: [...rows, { value: uniqueValue(slugifyValue(label, `row_${n}`), used), label }],
      columns,
      row_select_label: rowSelectLabel,
    });
  };

  const removeRow = (index: number) => {
    if (rows.length <= 1) {
      return;
    }
    onChange({
      rows: rows.filter((_, i) => i !== index),
      columns,
      row_select_label: rowSelectLabel,
    });
  };

  const addColumn = () => {
    const used = new Set(columns.map((c) => c.value));
    const n = columns.length + 1;
    const label = `Column ${n}`;
    onChange({
      rows,
      columns: [
        ...columns,
        {
          value: uniqueValue(slugifyValue(label, `col_${n}`), used),
          label,
          type: "text",
        },
      ],
      row_select_label: rowSelectLabel,
    });
  };

  const removeColumn = (index: number) => {
    if (columns.length <= 1) {
      return;
    }
    onChange({
      rows,
      columns: columns.filter((_, i) => i !== index),
      row_select_label: rowSelectLabel,
    });
  };

  const addChoiceToColumn = (index: number) => {
    const col = columns[index];
    if (!col) {
      return;
    }
    const choices = col.choices ?? [];
    const n = choices.length + 1;
    updateColumn(index, {
      choices: [...choices, { value: `opt_${n}`, label: `Option ${n}` }],
    });
  };

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="checklist-matrix-row-label">First column header</Label>
        <Input
          id="checklist-matrix-row-label"
          disabled={disabled}
          value={rowSelectLabel}
          onChange={(e) =>
            onChange({ rows, columns, row_select_label: e.target.value || "Cost Application" })
          }
          placeholder="Cost Application"
        />
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between gap-2">
          <Label>Rows (checkbox labels)</Label>
          <Button type="button" size="sm" variant="outline" className="h-7" onClick={addRow} disabled={disabled}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add row
          </Button>
        </div>
        <div className="space-y-2">
          {rows.map((row, index) => (
            <div key={row.value} className="flex items-center gap-2">
              <Input
                disabled={disabled}
                value={row.label}
                onChange={(e) => updateRow(index, { label: e.target.value })}
                placeholder="Row label"
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
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            </div>
          ))}
        </div>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between gap-2">
          <Label>Columns</Label>
          <Button type="button" size="sm" variant="outline" className="h-7" onClick={addColumn} disabled={disabled}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add column
          </Button>
        </div>
        <p className="text-[11px] text-muted-foreground">
          Each column can be short text, number, currency, date, or dropdown.
        </p>
        <ul className="space-y-3">
          {columns.map((column, index) => {
            const type = checklistMatrixColumnType(column);
            return (
              <li
                key={column.value}
                className="space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3"
              >
                <div className="flex items-start gap-2">
                  <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">Header label</Label>
                      <Input
                        disabled={disabled}
                        value={column.label}
                        onChange={(e) => updateColumn(index, { label: e.target.value })}
                        placeholder="Column label"
                        className="h-8 text-sm"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">Cell field type</Label>
                      <Select
                        disabled={disabled}
                        value={type}
                        onChange={(e) =>
                          updateColumn(index, {
                            type: e.target.value as ChecklistMatrixColumnType,
                          })
                        }
                      >
                        {CHECKLIST_MATRIX_COLUMN_TYPES.map((t) => (
                          <option key={t} value={t}>
                            {GRID_COLUMN_TYPE_LABELS[t]}
                          </option>
                        ))}
                      </Select>
                    </div>
                  </div>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="mt-5 h-8 w-8 shrink-0 text-destructive"
                    disabled={disabled || columns.length <= 1}
                    onClick={() => removeColumn(index)}
                    aria-label="Remove column"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>

                {type === "select" ? (
                  <div className="space-y-2 border-t border-border/40 pt-2">
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">Dropdown source</Label>
                      <Select
                        disabled={disabled || setsQuery.isLoading}
                        value={column.master_data_key !== undefined ? "master_data" : "static"}
                        onChange={(e) => {
                          if (e.target.value === "master_data") {
                            updateColumn(index, {
                              master_data_key: masterSets[0]?.key ?? "",
                              choices: undefined,
                            });
                          } else {
                            updateColumn(index, {
                              master_data_key: undefined,
                              choices: column.choices ?? [{ value: "a", label: "Option A" }],
                            });
                          }
                        }}
                      >
                        <option value="static">Static choices</option>
                        <option value="master_data">Master data lookup</option>
                      </Select>
                    </div>

                    {column.master_data_key !== undefined ? (
                      <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Master data set</Label>
                        <Select
                          disabled={disabled || setsQuery.isLoading}
                          value={column.master_data_key}
                          onChange={(e) => updateColumn(index, { master_data_key: e.target.value })}
                        >
                          <option value="">Select a set…</option>
                          {masterSets.map((set) => (
                            <option key={set.key} value={set.key}>
                              {set.name || set.key}
                            </option>
                          ))}
                        </Select>
                      </div>
                    ) : (
                      <div className="space-y-2">
                        <div className="flex items-center justify-between gap-2">
                          <Label className="text-xs text-muted-foreground">Choices</Label>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7"
                            disabled={disabled}
                            onClick={() => addChoiceToColumn(index)}
                          >
                            <Plus className="mr-1 h-3.5 w-3.5" />
                            Add
                          </Button>
                        </div>
                        {(column.choices ?? []).map((choice, choiceIndex) => (
                          <div key={`${column.value}-choice-${choiceIndex}`} className="flex items-center gap-2">
                            <Input
                              disabled={disabled}
                              value={choice.label}
                              onChange={(e) => {
                                const choices = [...(column.choices ?? [])];
                                const current = choices[choiceIndex];
                                if (!current) {
                                  return;
                                }
                                choices[choiceIndex] = { ...current, label: e.target.value };
                                updateColumn(index, { choices });
                              }}
                              placeholder="Choice label"
                              className="h-8 text-sm"
                            />
                            <Button
                              type="button"
                              size="icon"
                              variant="ghost"
                              className="h-8 w-8 shrink-0 text-destructive"
                              disabled={disabled || (column.choices?.length ?? 0) <= 1}
                              onClick={() => {
                                const choices = (column.choices ?? []).filter((_, i) => i !== choiceIndex);
                                updateColumn(index, {
                                  choices: choices.length > 0 ? choices : [{ value: "a", label: "Option A" }],
                                });
                              }}
                              aria-label="Remove choice"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ) : null}
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
}
