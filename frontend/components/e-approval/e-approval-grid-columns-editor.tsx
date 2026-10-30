"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { ChevronDown, ChevronUp, Plus, Trash2 } from "lucide-react";
import { useEffect } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalMasterDataSets, type EApprovalMasterDataColumnDef } from "@/lib/api/modules/e-approval-api";
import {
  GRID_COLUMN_PRESETS,
  type EApprovalGridColumnPresetId,
} from "@/modules/e-approval/custom-form-presets";
import {
  GRID_COLUMN_TYPE_LABELS,
  type GridColumnDef,
  type GridColumnType,
} from "@/modules/e-approval/field-options";

const BUILTIN_SITE_FIELDS: EApprovalMasterDataColumnDef[] = [
  { key: "label", label: "Label", type: "text" },
  { key: "code", label: "Code", type: "text" },
  { key: "address", label: "Address", type: "text" },
  { key: "name", label: "Name", type: "text" },
];

function siteFieldsForSet(
  set: { config_json?: { columns?: EApprovalMasterDataColumnDef[] } | null } | undefined,
): EApprovalMasterDataColumnDef[] {
  const saved = Array.isArray(set?.config_json?.columns) ? set.config_json.columns : [];
  const savedByKey = new Map(saved.filter((field) => field.key !== "").map((field) => [field.key, field]));
  const seen = new Set<string>();
  const fields: EApprovalMasterDataColumnDef[] = [];
  const push = (field: EApprovalMasterDataColumnDef) => {
    if (field.key === "" || seen.has(field.key)) {
      return;
    }
    seen.add(field.key);
    fields.push(field);
  };
  for (const builtin of BUILTIN_SITE_FIELDS) {
    push(savedByKey.get(builtin.key) ?? builtin);
    savedByKey.delete(builtin.key);
  }
  for (const field of savedByKey.values()) {
    push(field);
  }
  return fields;
}

function toggleSiteField(current: string[], key: string, on: boolean): string[] {
  const next = new Set(current);
  if (on) {
    next.add(key);
  } else {
    next.delete(key);
  }
  return [...next];
}

type Props = {
  columns: GridColumnDef[];
  onChange: (columns: GridColumnDef[]) => void;
  disabled?: boolean;
  formFields?: { name: string; label: string; type: string }[];
};

const GRID_COLUMN_TYPES = Object.keys(GRID_COLUMN_TYPE_LABELS) as GridColumnType[];

export function EApprovalGridColumnsEditor({ columns, onChange, disabled, formFields = [] }: Props) {
  const setsQuery = useQuery({
    queryKey: ["e-approval", "master-data-sets"],
    queryFn: fetchEApprovalMasterDataSets,
    staleTime: 60_000,
  });
  const masterSets = setsQuery.data ?? [];
  const siteSet = masterSets.find((set) => set.key === "sites");
  const siteFieldKeys = siteFieldsForSet(siteSet).map((field) => field.key);

  useEffect(() => {
    if (!siteSet) {
      return;
    }
    const allowed = new Set(siteFieldKeys);
    let changed = false;
    const next = columns.map((col) => {
      if (col.master_data_key !== "sites" || !Array.isArray(col.show_fields)) {
        return col;
      }
      const cleaned = col.show_fields.filter((key) => allowed.has(key));
      if (cleaned.length === col.show_fields.length && cleaned.every((key, index) => key === col.show_fields?.[index])) {
        return col;
      }
      changed = true;
      return { ...col, show_fields: cleaned };
    });
    if (changed) {
      onChange(next);
    }
  }, [columns, onChange, siteFieldKeys, siteSet]);

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
    if (merged.type !== "date_range") {
      delete merged.include_time;
    }
    if (merged.type !== "select" || merged.master_data_key !== "sites") {
      delete merged.show_fields;
      delete merged.multiple;
      delete merged.subsidiary_field;
    }
    if (merged.type !== "currency" && merged.type !== "number") {
      delete merged.show_total;
    }
    if (merged.type !== "date" && merged.type !== "date_range") {
      delete merged.date_min;
      delete merged.lead_days;
      delete merged.date_min_field;
    } else if (!merged.date_min) {
      delete merged.date_min;
      delete merged.lead_days;
      delete merged.date_min_field;
    } else if (merged.date_min !== "lead") {
      delete merged.lead_days;
    }
    if (merged.date_min !== "field") {
      delete merged.date_min_field;
    }
    if (merged.locked) {
      delete merged.required;
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
          Amount, VAT, Non-VATable, and VATable net can join the total row. A site column can hold several sites.
          Expense type flags mark VATable, per diem, and lodging. Per diem and lodging use the dates on the line.
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
              <div className="flex flex-col gap-2">
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Header label</Label>
                  <Input
                    disabled={disabled}
                    value={col.label}
                    onChange={(e) => updateColumn(index, { label: e.target.value })}
                    placeholder={`Column ${index + 1}`}
                    className="h-9 w-full text-sm"
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Cell field type</Label>
                  <Select
                    disabled={disabled}
                    value={col.type}
                    className="h-9 w-full"
                    onChange={(e) => updateColumn(index, { type: e.target.value as GridColumnType })}
                  >
                    {GRID_COLUMN_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {GRID_COLUMN_TYPE_LABELS[t]}
                      </option>
                    ))}
                  </Select>
                </div>
                <div className="flex items-center justify-end gap-0.5">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-8 w-8"
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
                    className="h-8 w-8"
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
                    className="h-8 w-8 text-destructive"
                    disabled={disabled}
                    onClick={() => removeColumn(index)}
                    aria-label="Remove column"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  disabled={disabled}
                  checked={col.hidden !== true}
                  onCheckedChange={(checked) => updateColumn(index, { hidden: checked !== true })}
                  className="size-4"
                />
                Show this column
              </label>
              {col.type === "currency" || col.type === "number" ? (
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    disabled={disabled}
                    checked={col.show_total === true}
                    onCheckedChange={(checked) => updateColumn(index, { show_total: checked === true })}
                    className="size-4"
                  />
                  Add to the total row
                </label>
              ) : null}
              {col.locked ? null : (
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    disabled={disabled}
                    checked={col.required === true}
                    onCheckedChange={(checked) => updateColumn(index, { required: checked === true })}
                    className="size-4"
                  />
                  Required
                </label>
              )}

              {col.type === "date" || col.type === "date_range" ? (
                <div className="space-y-2 border-t border-border/40 pt-2">
                  {col.type === "date_range" ? (
                    <label className="flex items-center gap-2 text-sm">
                      <Checkbox
                        disabled={disabled}
                        checked={col.include_time !== false}
                        onCheckedChange={(checked) => updateColumn(index, { include_time: checked === true })}
                        className="size-4"
                      />
                      Include time
                    </label>
                  ) : null}
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Earliest date</Label>
                    <Select
                      disabled={disabled}
                      value={col.date_min ?? ""}
                      onChange={(e) => {
                        const dateMin = e.target.value;
                        if (dateMin === "today" || dateMin === "lead" || dateMin === "field") {
                          updateColumn(index, {
                            date_min: dateMin,
                            lead_days: dateMin === "lead" ? col.lead_days ?? 3 : undefined,
                            date_min_field: dateMin === "field" ? col.date_min_field ?? "" : undefined,
                          });
                          return;
                        }
                        updateColumn(index, { date_min: undefined, lead_days: undefined, date_min_field: undefined });
                      }}
                    >
                      <option value="">Any day</option>
                      <option value="today">Today — past dates disabled</option>
                      <option value="lead">Today plus a lead</option>
                      <option value="field">On or after a form date</option>
                    </Select>
                  </div>
                  {col.date_min === "lead" ? (
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">Lead days</Label>
                      <Input
                        disabled={disabled}
                        type="number"
                        min={1}
                        value={col.lead_days ?? 3}
                        onChange={(e) => updateColumn(index, { lead_days: Number(e.target.value) || 3 })}
                      />
                    </div>
                  ) : null}
                  {col.date_min === "field" ? (
                    <div className="space-y-1">
                      <Label className="text-xs text-muted-foreground">On or after</Label>
                      <Select
                        disabled={disabled}
                        value={col.date_min_field ?? ""}
                        onChange={(e) => updateColumn(index, { date_min_field: e.target.value })}
                      >
                        <option value="">Choose a date field</option>
                        {formFields
                          .filter((candidate) => candidate.type === "date" || candidate.type === "date_range")
                          .map((candidate) => (
                            <option key={candidate.name} value={candidate.name}>
                              {candidate.label}
                            </option>
                          ))}
                      </Select>
                    </div>
                  ) : null}
                </div>
              ) : null}

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
                    <div className="space-y-2">
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
                      {col.master_data_key === "sites" ? (
                        <div className="space-y-1.5">
                          <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                              disabled={disabled}
                              checked={col.multiple === true}
                              onCheckedChange={(checked) => updateColumn(index, { multiple: checked === true })}
                              className="size-4"
                            />
                            Allow multiple sites
                          </label>
                          <div className="space-y-1">
                            <Label className="text-xs text-muted-foreground">Must match</Label>
                            <Select
                              disabled={disabled}
                              value={col.subsidiary_field ?? ""}
                              onChange={(e) =>
                                updateColumn(index, { subsidiary_field: e.target.value.trim() || undefined })
                              }
                            >
                              <option value="">Do not check</option>
                              {formFields
                                .filter((candidate) => candidate.type !== "grid" && candidate.type !== "section")
                                .map((candidate) => (
                                  <option key={candidate.name} value={candidate.name}>
                                    {candidate.label}
                                  </option>
                                ))}
                            </Select>
                          </div>
                          <p className="text-xs text-muted-foreground">
                            Include on the line. Label and Code are on every site and turn on by themselves. Address, Site Code, and the other columns are separate.
                          </p>
                          {siteFieldsForSet(siteSet).map((field) => {
                            const allowed = new Set(siteFieldKeys);
                            const selected = (col.show_fields ?? (allowed.has("address") ? ["address"] : [])).filter(
                              (key) => allowed.has(key),
                            );
                            return (
                              <label key={field.key} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                  disabled={disabled}
                                  checked={selected.includes(field.key)}
                                  onCheckedChange={(checked) =>
                                    updateColumn(index, {
                                      show_fields: toggleSiteField(selected, field.key, checked === true),
                                    })
                                  }
                                  className="size-4"
                                />
                                {field.label}
                              </label>
                            );
                          })}
                        </div>
                      ) : null}
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
                      {(col.choices ?? []).map((choice, choiceIndex) => {
                        const setFlag = (key: "vatable" | "per_diem" | "hotel", on: boolean) => {
                          const choices = [...(col.choices ?? [])];
                          const data = { ...(choice.data ?? {}) };
                          if (on) {
                            data[key] = true;
                          } else {
                            delete data[key];
                          }
                          choices[choiceIndex] = {
                            ...choice,
                            data: Object.keys(data).length > 0 ? data : undefined,
                          };
                          updateColumn(index, { choices });
                        };
                        return (
                        <div key={choiceIndex} className="space-y-1 rounded-md border border-border/50 p-2">
                          <div className="flex gap-1">
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
                          <div className="flex flex-wrap gap-x-3 gap-y-1">
                            <label className="flex items-center gap-1.5 text-xs">
                              <Checkbox
                                disabled={disabled}
                                checked={choice.data?.vatable === true}
                                onCheckedChange={(checked) => setFlag("vatable", checked === true)}
                                className="size-3.5"
                              />
                              VATable
                            </label>
                            <label className="flex items-center gap-1.5 text-xs">
                              <Checkbox
                                disabled={disabled}
                                checked={choice.data?.per_diem === true}
                                onCheckedChange={(checked) => setFlag("per_diem", checked === true)}
                                className="size-3.5"
                              />
                              Per diem cap
                            </label>
                            <label className="flex items-center gap-1.5 text-xs">
                              <Checkbox
                                disabled={disabled}
                                checked={choice.data?.hotel === true}
                                onCheckedChange={(checked) => setFlag("hotel", checked === true)}
                                className="size-3.5"
                              />
                              Lodging cap
                            </label>
                          </div>
                        </div>
                        );
                      })}
                      <p className="text-[11px] leading-snug text-muted-foreground">
                        VATable puts the line in the VAT split. Per diem and Lodging use the daily caps and multiply the rate by the dates. A master data row uses the same three flags.
                      </p>
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
          E-Forms → Master data
        </Link>
        .
      </p>
    </div>
  );
}
