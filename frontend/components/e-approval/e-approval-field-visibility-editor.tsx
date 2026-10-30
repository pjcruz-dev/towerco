"use client";

import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  E_APPROVAL_VISIBILITY_OPERATORS,
  fieldCanControlVisibility,
  parseFieldVisibility,
  patchFieldVisibility,
  type EApprovalFieldVisibilityRule,
  type EApprovalVisibilityMode,
} from "@/modules/e-approval/field-visibility";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  allFields: EApprovalFormFieldInput[];
  fieldIndex: number;
  onChange: (options: Record<string, unknown>) => void;
};

export function EApprovalFieldVisibilityEditor({ field, allFields, fieldIndex, onChange }: Props) {
  const rule = parseFieldVisibility(field);
  const enabled = rule !== null;

  const controllerOptions = allFields
    .filter((f, i) => i !== fieldIndex && fieldCanControlVisibility(f.type))
    .map((f) => ({ name: f.name, label: f.label }));

  const updateRule = (patch: Partial<EApprovalFieldVisibilityRule> | null) => {
    if (patch === null) {
      onChange(patchFieldVisibility(field, null));
      return;
    }

    const current: EApprovalFieldVisibilityRule = rule ?? {
      mode: "show_when",
      field: controllerOptions[0]?.name ?? "",
      operator: "equals",
      value: "",
    };

    onChange(patchFieldVisibility(field, { ...current, ...patch }));
  };

  const needsValue = rule?.operator === "equals" || rule?.operator === "not_equals" || rule?.operator === "contains";

  return (
    <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-medium text-foreground">Conditional visibility</p>
        <label className="flex items-center gap-2 text-xs text-muted-foreground">
          <Checkbox
            checked={enabled}
            onCheckedChange={(v) => {
              if (v !== true) {
                updateRule(null);
                return;
              }
              updateRule({});
            }}
            className="size-4"
          />
          Enabled
        </label>
      </div>

      {enabled && rule ? (
        <div className="space-y-2">
          <div className="space-y-1">
            <Label htmlFor="ea-vis-mode">Behavior</Label>
            <Select
              id="ea-vis-mode"
              value={rule.mode}
              onChange={(e) => updateRule({ mode: e.target.value as EApprovalVisibilityMode })}
            >
              <option value="show_when">Show when</option>
              <option value="hide_when">Hide when</option>
            </Select>
          </div>
          {rule.operator === "all_filled" ? (
            <div className="space-y-1">
              <Label>When all of these are filled</Label>
              <div className="space-y-1">
                {controllerOptions.length === 0 ? (
                  <p className="text-[11px] text-muted-foreground">Add another field first.</p>
                ) : (
                  controllerOptions.map((opt) => {
                    const selected = (rule.fields ?? []).includes(opt.name);
                    return (
                      <label key={opt.name} className="flex items-center gap-2 text-sm">
                        <Checkbox
                          checked={selected}
                          onCheckedChange={(checked) => {
                            const current = rule.fields ?? [];
                            const fields =
                              checked === true
                                ? [...current, opt.name]
                                : current.filter((name) => name !== opt.name);
                            updateRule({ fields, field: fields[0] ?? "" });
                          }}
                          className="size-4"
                        />
                        {opt.label}
                      </label>
                    );
                  })
                )}
              </div>
            </div>
          ) : (
            <div className="space-y-1">
              <Label htmlFor="ea-vis-field">When field</Label>
              <Select
                id="ea-vis-field"
                value={rule.field}
                onChange={(e) => updateRule({ field: e.target.value })}
              >
                {controllerOptions.length === 0 ? (
                  <option value="">Add another field first</option>
                ) : (
                  controllerOptions.map((opt) => (
                    <option key={opt.name} value={opt.name}>
                      {opt.label} ({opt.name})
                    </option>
                  ))
                )}
              </Select>
            </div>
          )}
          <div className="space-y-1">
            <Label htmlFor="ea-vis-op">Condition</Label>
            <Select
              id="ea-vis-op"
              value={rule.operator}
              onChange={(e) => {
                const operator = e.target.value as EApprovalFieldVisibilityRule["operator"];
                if (operator === "all_filled") {
                  const fields = rule.fields && rule.fields.length > 0 ? rule.fields : rule.field ? [rule.field] : [];
                  updateRule({ operator, fields, field: fields[0] ?? "" });
                  return;
                }
                updateRule({ operator });
              }}
            >
              {E_APPROVAL_VISIBILITY_OPERATORS.map((op) => (
                <option key={op.value} value={op.value}>
                  {op.label}
                </option>
              ))}
            </Select>
          </div>
          {needsValue ? (
            <div className="space-y-1">
              <Label htmlFor="ea-vis-value">Value</Label>
              <Input
                id="ea-vis-value"
                value={rule.value ?? ""}
                onChange={(e) => updateRule({ value: e.target.value })}
                placeholder="e.g. sick, approved, 1000"
              />
            </div>
          ) : null}
          <p className="text-[11px] text-muted-foreground">
            Hidden fields are skipped in preview, submit validation, and server validation.
          </p>
        </div>
      ) : (
        <p className="text-[11px] text-muted-foreground">Field is always visible to requestors.</p>
      )}
    </div>
  );
}
