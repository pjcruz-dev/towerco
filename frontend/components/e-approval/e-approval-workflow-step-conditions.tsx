"use client";

import { Plus, Trash2 } from "lucide-react";
import { useMemo } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type {
  EApprovalFormFieldInput,
  EApprovalWorkflowCondition,
  EApprovalWorkflowConditionOperator,
  EApprovalWorkflowStepInput,
} from "@/modules/e-approval/types";
import {
  createEmptyCondition,
  parseStepWhen,
  parseStepWhenLogic,
  patchStepWhen,
  patchStepWhenLogic,
} from "@/modules/e-approval/workflow-conditions";
import { E_APPROVAL_WORKFLOW_CONDITION_OPERATORS } from "@/modules/e-approval/workflow-rules";

type Props = {
  fields: EApprovalFormFieldInput[];
  step: EApprovalWorkflowStepInput;
  onChange: (step: EApprovalWorkflowStepInput) => void;
};

export function EApprovalWorkflowStepConditions({ fields, step, onChange }: Props) {
  const conditionFieldOptions = useMemo(() => {
    return fields
      .filter((field) => !["section", "divider", "grid", "file", "signature"].includes(field.type))
      .filter((field) => field.name.trim() !== "")
      .map((field) => ({
        id: field.name,
        label: field.label?.trim() || field.name,
      }));
  }, [fields]);

  const when = parseStepWhen(step);
  const whenLogic = parseStepWhenLogic(step);

  const updateCondition = (index: number, patch: Partial<EApprovalWorkflowCondition>) => {
    const next = [...when];
    next[index] = { ...next[index], ...patch };
    onChange(patchStepWhen(step, next, whenLogic));
  };

  return (
    <div className="space-y-2 rounded-lg border border-border/60 bg-muted/10 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs font-medium text-foreground">Run this step when</p>
        <div className="flex flex-wrap items-center gap-2">
          {when.length > 1 ? (
            <Select
              value={whenLogic}
              className="h-8 w-auto min-w-[9.5rem] text-xs"
              onChange={(e) => onChange(patchStepWhenLogic(step, e.target.value as "and" | "or"))}
            >
              <option value="and">Match all (AND)</option>
              <option value="or">Match any (OR)</option>
            </Select>
          ) : null}
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => onChange(patchStepWhen(step, [...when, createEmptyCondition(fields)], whenLogic))}
          >
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add condition
          </Button>
        </div>
      </div>
      {when.length === 0 ? (
        <p className="text-xs text-muted-foreground">No conditions — this step always runs.</p>
      ) : (
        <p className="text-xs text-muted-foreground">
          {whenLogic === "or"
            ? "Any one condition below can match (OR)."
            : "All conditions below must match (AND)."}
        </p>
      )}
      {when.map((condition, index) => {
        const operatorMeta = E_APPROVAL_WORKFLOW_CONDITION_OPERATORS.find((item) => item.value === condition.operator);

        return (
          <div key={`${step.id ?? "step"}-when-${index}`} className="space-y-2">
            {index > 0 ? (
              <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {whenLogic === "or" ? "or" : "and"}
              </p>
            ) : null}
            <div className="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
              <Select value={condition.field} onChange={(e) => updateCondition(index, { field: e.target.value })}>
                <option value="">Field</option>
                {conditionFieldOptions.map((field) => (
                  <option key={field.id} value={field.id}>
                    {field.label}
                  </option>
                ))}
              </Select>
              <Select
                value={condition.operator}
                onChange={(e) =>
                  updateCondition(index, { operator: e.target.value as EApprovalWorkflowConditionOperator })
                }
              >
                {E_APPROVAL_WORKFLOW_CONDITION_OPERATORS.map((operator) => (
                  <option key={operator.value} value={operator.value}>
                    {operator.label}
                  </option>
                ))}
              </Select>
              {operatorMeta?.needsValue ? (
                <Input
                  value={condition.value ?? ""}
                  placeholder="Value"
                  onChange={(e) => updateCondition(index, { value: e.target.value })}
                />
              ) : (
                <p className="flex items-center text-xs text-muted-foreground">No value required</p>
              )}
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() =>
                  onChange(patchStepWhen(step, when.filter((_, itemIndex) => itemIndex !== index), whenLogic))
                }
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </div>
        );
      })}
    </div>
  );
}
