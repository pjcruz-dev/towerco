"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { fetchEApprovalMasterDataSets } from "@/lib/api/modules/e-approval-api";
import {
  getMasterDataLookupKey,
  parseSelectChoices,
  type SelectChoice,
  type SelectChoiceCompanionInput,
} from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type OptionSource = "static" | "master_data";

type Props = {
  field: EApprovalFormFieldInput;
  onChange: (patch: Record<string, unknown>) => void;
  disabled?: boolean;
};

function resolveSource(field: EApprovalFormFieldInput): OptionSource {
  return getMasterDataLookupKey(field) ? "master_data" : "static";
}

function nextCompanionKey(inputs: SelectChoiceCompanionInput[]): string {
  let n = inputs.length + 1;
  const used = new Set(inputs.map((input) => input.key));
  while (used.has(`input_${n}`)) {
    n += 1;
  }

  return `input_${n}`;
}

export function EApprovalSelectOptionsEditor({ field, onChange, disabled }: Props) {
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [advancedJson, setAdvancedJson] = useState("");

  const source = resolveSource(field);
  const staticChoices = useMemo(() => parseSelectChoices(field), [field]);
  const masterKey = getMasterDataLookupKey(field) ?? "";
  const allowCompanions = field.type === "checkbox";

  const setsQuery = useQuery({
    queryKey: ["e-approval", "master-data-sets"],
    queryFn: fetchEApprovalMasterDataSets,
    staleTime: 60_000,
  });

  const masterSets = setsQuery.data ?? [];

  const setSource = (next: OptionSource) => {
    if (next === "master_data") {
      const defaultKey = masterSets[0]?.key ?? masterKey ?? "";
      onChange({
        master_data_key: defaultKey,
        masterDataKey: undefined,
        lookup_key: undefined,
        lookupKey: undefined,
      });
      return;
    }

    onChange({
      master_data_key: undefined,
      masterDataKey: undefined,
      lookup_key: undefined,
      lookupKey: undefined,
      choices:
        staticChoices.length > 0
          ? staticChoices
          : [
              { value: "a", label: "Option A" },
              { value: "b", label: "Option B" },
            ],
    });
  };

  const updateStaticChoices = (choices: SelectChoice[]) => {
    onChange({ choices });
  };

  const updateChoice = (index: number, patch: Partial<SelectChoice>) => {
    const next = [...staticChoices];
    const current = next[index] ?? { value: "", label: "" };
    next[index] = { ...current, ...patch };
    updateStaticChoices(next);
  };

  const addChoice = () => {
    const n = staticChoices.length + 1;
    updateStaticChoices([...staticChoices, { value: `opt_${n}`, label: `Option ${n}` }]);
  };

  const removeChoice = (index: number) => {
    updateStaticChoices(staticChoices.filter((_, i) => i !== index));
  };

  const updateCompanion = (
    choiceIndex: number,
    inputIndex: number,
    patch: Partial<SelectChoiceCompanionInput>,
  ) => {
    const choice = staticChoices[choiceIndex];
    if (!choice) {
      return;
    }
    const inputs = [...(choice.inputs ?? [])];
    const current = inputs[inputIndex] ?? { key: nextCompanionKey(inputs), type: "number" as const };
    inputs[inputIndex] = { ...current, ...patch };
    updateChoice(choiceIndex, { inputs });
  };

  const addCompanion = (choiceIndex: number) => {
    const choice = staticChoices[choiceIndex];
    if (!choice) {
      return;
    }
    const inputs = [...(choice.inputs ?? [])];
    inputs.push({
      key: nextCompanionKey(inputs),
      type: "number",
      suffix: "m.(AGL)",
      required: true,
    });
    updateChoice(choiceIndex, { inputs });
  };

  const removeCompanion = (choiceIndex: number, inputIndex: number) => {
    const choice = staticChoices[choiceIndex];
    if (!choice) {
      return;
    }
    const inputs = (choice.inputs ?? []).filter((_, i) => i !== inputIndex);
    const next = [...staticChoices];
    const updated: SelectChoice = { ...choice };
    if (inputs.length > 0) {
      updated.inputs = inputs;
    } else {
      delete updated.inputs;
    }
    next[choiceIndex] = updated;
    updateStaticChoices(next);
  };

  return (
    <div className="min-w-0 space-y-3 overflow-x-hidden">
      <div className="space-y-1">
        <Label>Option source</Label>
        <Select
          disabled={disabled}
          value={source}
          onChange={(e) => setSource(e.target.value as OptionSource)}
        >
          <option value="static">Static choices</option>
          <option value="master_data">Master data lookup</option>
        </Select>
      </div>

      {source === "master_data" ? (
        <div className="space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3">
          <div className="space-y-1">
            <Label>Master data set</Label>
            <Select
              disabled={disabled || setsQuery.isLoading}
              value={masterKey}
              onChange={(e) => onChange({ master_data_key: e.target.value })}
            >
              <option value="">
                {setsQuery.isLoading ? "Loading sets…" : "Select a master data set…"}
              </option>
              {masterSets.map((set) => (
                <option key={set.id} value={set.key}>
                  {set.name} ({set.key})
                </option>
              ))}
            </Select>
          </div>
          {masterSets.length === 0 && !setsQuery.isLoading ? (
            <p className="text-xs text-muted-foreground">
              No master data sets yet.{" "}
              <Link href="/e-approval/master-data" className="text-primary underline-offset-2 hover:underline">
                Create sets under E-Approval → Master data
              </Link>
              .
            </p>
          ) : (
            <p className="text-xs text-muted-foreground">
              Options load at runtime from <code className="rounded bg-muted px-1">GET /e-approval/master-data/{`{key}`}</code>.
              Rows use code/value and label from the set.
              {allowCompanions ? " Inline companion fields require static choices." : null}
            </p>
          )}
        </div>
      ) : (
        <div className="min-w-0 space-y-2">
          <div className="flex items-center justify-between gap-2">
            <Label>Choices</Label>
            <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={addChoice}>
              <Plus className="mr-1 h-3.5 w-3.5" />
              Add option
            </Button>
          </div>
          {staticChoices.length === 0 ? (
            <p className="rounded-md border border-dashed border-border px-3 py-3 text-center text-xs text-muted-foreground">
              No static choices. Add options or switch to master data.
            </p>
          ) : (
            <ul className="min-w-0 space-y-2">
              {staticChoices.map((choice, index) => (
                <li
                  key={`choice-${index}`}
                  className="min-w-0 space-y-2 overflow-hidden rounded-lg border border-border/60 bg-muted/20 p-2.5"
                >
                  <div className="flex min-w-0 items-start gap-1.5">
                    <div className="min-w-0 flex-1 space-y-1.5">
                      <Input
                        disabled={disabled}
                        value={choice.label}
                        onChange={(e) => updateChoice(index, { label: e.target.value })}
                        placeholder="Label"
                        className="h-8 w-full text-sm"
                      />
                      <Input
                        disabled={disabled}
                        value={choice.value}
                        onChange={(e) => updateChoice(index, { value: e.target.value })}
                        placeholder="Value / API key"
                        className="h-8 w-full font-mono text-xs"
                      />
                    </div>
                    <Button
                      type="button"
                      size="icon"
                      variant="ghost"
                      className="h-8 w-8 shrink-0 text-destructive"
                      disabled={disabled}
                      onClick={() => removeChoice(index)}
                      aria-label="Remove option"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>

                  {allowCompanions ? (
                    <div className="min-w-0 space-y-2 rounded-md border border-border/50 bg-background/70 p-2">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-[11px] font-medium text-foreground">Inline fields</p>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          className="h-7 shrink-0 px-2 text-xs"
                          disabled={disabled}
                          onClick={() => addCompanion(index)}
                        >
                          <Plus className="mr-1 h-3 w-3" />
                          Add
                        </Button>
                      </div>
                      {(choice.inputs ?? []).length === 0 ? (
                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                          Optional blanks on the same row (e.g. height + m.(AGL)).
                        </p>
                      ) : (
                        <ul className="min-w-0 space-y-2">
                          {(choice.inputs ?? []).map((input, inputIndex) => (
                            <li
                              key={`${choice.value}-input-${input.key}-${inputIndex}`}
                              className="min-w-0 space-y-1.5 rounded-md border border-border/40 bg-muted/15 p-2"
                            >
                              <div className="flex min-w-0 items-center gap-2">
                                <Select
                                  disabled={disabled}
                                  value={input.type}
                                  onChange={(e) =>
                                    updateCompanion(index, inputIndex, {
                                      type:
                                        e.target.value === "number"
                                          ? "number"
                                          : e.target.value === "size"
                                            ? "size"
                                            : "text",
                                      ...(e.target.value === "size"
                                        ? { suffix: input.suffix ?? "" }
                                        : {}),
                                    })
                                  }
                                  className="h-8 min-w-0 flex-1 text-xs"
                                >
                                  <option value="number">Number</option>
                                  <option value="text">Text</option>
                                  <option value="size">Size (W × H)</option>
                                </Select>
                                <label className="flex shrink-0 items-center gap-1.5 text-[11px] text-muted-foreground">
                                  <Checkbox
                                    disabled={disabled}
                                    checked={input.required === true}
                                    onCheckedChange={(v) =>
                                      updateCompanion(index, inputIndex, { required: v === true })
                                    }
                                    className="size-3.5"
                                  />
                                  Required
                                </label>
                                <Button
                                  type="button"
                                  size="icon"
                                  variant="ghost"
                                  className="h-8 w-8 shrink-0 text-destructive"
                                  disabled={disabled}
                                  onClick={() => removeCompanion(index, inputIndex)}
                                  aria-label="Remove inline field"
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                              </div>
                              <Input
                                disabled={disabled}
                                value={input.suffix ?? ""}
                                onChange={(e) => updateCompanion(index, inputIndex, { suffix: e.target.value })}
                                placeholder={
                                  input.type === "size" ? "Optional unit after size, e.g. m" : "Suffix, e.g. m.(AGL)"
                                }
                                className="h-8 w-full text-sm"
                              />
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  ) : null}
                  <div className="min-w-0 space-y-1">
                    <p className="text-[11px] font-medium text-foreground">Option help</p>
                    <textarea
                      disabled={disabled}
                      value={choice.help ?? ""}
                      onChange={(e) =>
                        updateChoice(index, {
                          help: e.target.value.trim() === "" ? null : e.target.value,
                        })
                      }
                      placeholder="Optional guidance under this option (bullets, reminders…)"
                      rows={2}
                      className="w-full min-w-0 rounded-md border border-input bg-background px-2 py-1.5 text-xs text-foreground"
                    />
                  </div>
                </li>
              ))}
            </ul>
          )}
          <p className="text-xs text-muted-foreground">
            Legacy import format <code className="rounded bg-muted px-1">{`["Label|CODE"]`}</code> is still supported via
            advanced JSON.
          </p>
        </div>
      )}

      <div className="border-t border-border/60 pt-2">
        <button
          type="button"
          className="text-xs font-medium text-muted-foreground hover:text-foreground"
          onClick={() => {
            if (!showAdvanced) {
              setAdvancedJson(
                source === "master_data"
                  ? JSON.stringify({ master_data_key: masterKey }, null, 2)
                  : JSON.stringify(staticChoices, null, 2),
              );
            }
            setShowAdvanced((v) => !v);
          }}
        >
          {showAdvanced ? "Hide" : "Show"} advanced JSON
        </button>
        {showAdvanced ? (
          <Textarea
            className="mt-2 min-h-[100px] font-mono text-xs"
            value={advancedJson}
            disabled={disabled}
            onChange={(e) => {
              setAdvancedJson(e.target.value);
              try {
                const parsed: unknown = JSON.parse(e.target.value);
                if (parsed && typeof parsed === "object") {
                  if (Array.isArray(parsed)) {
                    onChange({ choices: parsed });
                  } else {
                    onChange(parsed as Record<string, unknown>);
                  }
                }
              } catch {
                /* ignore while typing */
              }
            }}
          />
        ) : null}
      </div>
    </div>
  );
}
