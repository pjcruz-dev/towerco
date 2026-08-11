"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { ChevronDown, ChevronUp, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalMasterDataSets } from "@/lib/api/modules/e-approval-api";
import {
  GRID_COLUMN_PRESETS,
  type EApprovalGridColumnPresetId,
} from "@/modules/e-approval/custom-form-presets";
import {
  GRID_COLUMN_TYPE_LABELS,
  type GridColumnDef,
  type GridColumnType,
} from "@/modules/e-approval/field-options";

type Props = {
  columns: GridColumnDef[];
  onChange: (columns: GridColumnDef[]) => void;
  disabled?: boolean;
};

const GRID_COLUMN_TYPES = Object.keys(GRID_COLUMN_TYPE_LABELS) as GridColumnType[];

export function EApprovalGridColumnsEditor({ columns, onChange, disabled }: Props) {
  const setsQuery = useQuery({
    queryKey: ["e-approval", "master-data-sets"],
    queryFn: fetchEApprovalMasterDataSets,
    staleTime: 60_000,
  });
  const masterSets = setsQuery.data ?? [];

  const updateColumn = (index: number, patch: Partial<GridColumnDef>) => {
    const next = [...columns];
    const current = next[index];
    if (!current) {
      return;
    }
    const merged = { ...current, ...patch };
    if (merged.type === "select") {
      if (!merged.master_data_key && !(merged.choices && merged.choices.length > 0)) {
        merged.choices = [{ value: "a", label: "Option A" }];
      }
    } else {
      delete merged.master_data_key;
      delete merged.choices;
    }
    next[index] = merged;
    onChange(next);
  };

  const addColumn = () => {
    onChange([...columns, { label: `Column ${columns.length + 1}`, type: "text" }]);
  };

  const removeColumn = (index: number) => {
    onChange(columns.filter((_, i) => i !== index));
  };

  const moveColumn = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= columns.length) {
      return;
    }
    const next = [...columns];
    const [item] = next.splice(index, 1);
    next.splice(target, 0, item);
    onChange(next);
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
    <div className="space-y-3">
      <div className="space-y-2">
        <Label>Column presets</Label>
        <div className="flex flex-wrap gap-1.5">
          {(Object.keys(GRID_COLUMN_PRESETS) as EApprovalGridColumnPresetId[]).map((presetId) => (
            <Button
              key={presetId}
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled}
              className="h-7 text-xs"
              onClick={() => onChange(GRID_COLUMN_PRESETS[presetId].columns)}
            >
              {GRID_COLUMN_PRESETS[presetId].label}
            </Button>
          ))}
        </div>
        <p className="text-[11px] text-muted-foreground">
          Presets match auto-total shortcuts. Use Amount for expense sums; Qty + Unit price for purchase lines.
        </p>
      </div>

      <div className="flex items-center justify-between gap-2">
        <Label>Grid columns</Label>
        <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={addColumn}>
          <Plus className="mr-1 h-3.5 w-3.5" />
          Add column
        </Button>
      </div>

      {columns.length === 0 ? (
        <p className="rounded-md border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
          No columns yet. Add columns with a header label and cell field type (text, number, date, dropdown, etc.).
        </p>
      ) : (
        <ul className="space-y-3">
          {columns.map((col, index) => (
            <li
              key={`col-${index}`}
              className="space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3"
            >
              <div className="flex items-start gap-1">
                <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Header label</Label>
                    <Input
                      disabled={disabled}
                      value={col.label}
                      onChange={(e) => updateColumn(index, { label: e.target.value })}
                      placeholder={`Column ${index + 1}`}
                      className="h-8 text-sm"
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Cell field type</Label>
                    <Select
                      disabled={disabled}
                      value={col.type}
                      onChange={(e) => updateColumn(index, { type: e.target.value as GridColumnType })}
                    >
                      {GRID_COLUMN_TYPES.map((t) => (
                        <option key={t} value={t}>
                          {GRID_COLUMN_TYPE_LABELS[t]}
                        </option>
                      ))}
                    </Select>
                  </div>
                </div>
                <div className="flex shrink-0 flex-col gap-0.5 pt-5">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7"
                    disabled={disabled || index === 0}
                    onClick={() => moveColumn(index, -1)}
                    aria-label="Move column up"
                  >
                    <ChevronUp className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7"
                    disabled={disabled || index === columns.length - 1}
                    onClick={() => moveColumn(index, 1)}
                    aria-label="Move column down"
                  >
                    <ChevronDown className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 text-destructive"
                    disabled={disabled}
                    onClick={() => removeColumn(index)}
                    aria-label="Remove column"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              {col.type === "select" ? (
                <div className="space-y-2 border-t border-border/40 pt-2">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Dropdown options</Label>
                    <Select
                      disabled={disabled || setsQuery.isLoading}
                      value={"master_data_key" in col && col.master_data_key !== undefined ? "master_data" : "static"}
                      onChange={(e) => {
                        if (e.target.value === "master_data") {
                          const { choices: _c, ...rest } = col;
                          updateColumn(index, {
                            ...rest,
                            master_data_key: masterSets[0]?.key ?? "",
                          });
                        } else {
                          const { master_data_key: _k, ...rest } = col;
                          updateColumn(index, {
                            ...rest,
                            choices: col.choices ?? [{ value: "a", label: "Option A" }],
                          });
                        }
                      }}
                    >
                      <option value="static">Static choices</option>
                      <option value="master_data">Master data lookup</option>
                    </Select>
                  </div>

                  {"master_data_key" in col && col.master_data_key !== undefined ? (
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">Master data set</Label>
                      <Select
                        disabled={disabled || setsQuery.isLoading}
                        value={col.master_data_key}
                        onChange={(e) => updateColumn(index, { master_data_key: e.target.value })}
                      >
                        <option value="">Select set…</option>
                        {masterSets.map((set) => (
                          <option key={set.id} value={set.key}>
                            {set.name} ({set.key})
                          </option>
                        ))}
                      </Select>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">Static choices</span>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          className="h-7 text-xs"
                          disabled={disabled}
                          onClick={() => addChoiceToColumn(index)}
                        >
                          <Plus className="mr-1 h-3 w-3" />
                          Add
                        </Button>
                      </div>
                      {(col.choices ?? []).map((choice, choiceIndex) => (
                        <div key={choiceIndex} className="flex gap-1">
                          <Input
                            disabled={disabled}
                            className="h-8 flex-1 text-sm"
                            value={choice.label}
                            onChange={(e) => {
                              const choices = [...(col.choices ?? [])];
                              choices[choiceIndex] = { ...choice, label: e.target.value };
                              updateColumn(index, { choices });
                            }}
                            placeholder="Label"
                          />
                          <Input
                            disabled={disabled}
                            className="h-8 w-24 font-mono text-xs"
                            value={choice.value}
                            onChange={(e) => {
                              const choices = [...(col.choices ?? [])];
                              choices[choiceIndex] = { ...choice, value: e.target.value };
                              updateColumn(index, { choices });
                            }}
                            placeholder="Value"
                          />
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      <p className="text-xs text-muted-foreground">
        Requestors see a table with one row per line item. Configure each column&apos;s input type (aligned with legacy
        form builder). Master data sets are managed in{" "}
        <Link href="/e-approval/master-data" className="text-primary underline-offset-2 hover:underline">
          E-Approval → Master data
        </Link>
        .
      </p>
    </div>
  );
}
