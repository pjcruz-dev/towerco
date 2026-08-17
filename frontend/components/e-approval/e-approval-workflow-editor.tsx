"use client";

import { useMutation } from "@tanstack/react-query";
import { ChevronDown, ChevronUp, Plus, Trash2 } from "lucide-react";

import { Spinner } from "@/components/ui/spinner";
import { useEffect, useMemo, useState, type ReactNode } from "react";

import {
  EApprovalWorkflowAddMenu,
  type WorkflowAddKind,
} from "@/components/e-approval/e-approval-workflow-add-menu";
import { EApprovalWorkflowFieldMapEditor } from "@/components/e-approval/e-approval-workflow-field-map-editor";
import { EApprovalWorkflowOrderDiagram } from "@/components/e-approval/e-approval-workflow-order-diagram";
import { EApprovalWorkflowPathDiagram } from "@/components/e-approval/e-approval-workflow-path-diagram";
import { EApprovalWorkflowPreviewFields } from "@/components/e-approval/e-approval-workflow-preview-fields";
import { EApprovalWorkflowStepConditions } from "@/components/e-approval/e-approval-workflow-step-conditions";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { previewEApprovalFormWorkflow, testEApprovalManagerLookup } from "@/lib/api/modules/e-approval-api";
import { E_APPROVAL_STEP_TYPES } from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  alignNearMissBranchThresholds,
  branchGroupLabel,
  detectNearMissBranchPairs,
  insertIfElseBranchSteps,
  insertThresholdLadderSteps,
  ladderGroupLabel,
  parseThresholdBand,
  thresholdBandLabel,
  toWorkflowEditorSegments,
  updateExclusiveBranchThreshold,
  updateExclusiveLadderThresholds,
  type ExclusiveBranchGroup,
  type ExclusiveThresholdLadder,
  type NearMissBranchPair,
  type WorkflowEditorSegment,
} from "@/modules/e-approval/workflow-branch-groups";
import { workflowStepTypeLabel } from "@/modules/e-approval/workflow-path-diagram";
import { insertWorkflowStepAt } from "@/modules/e-approval/workflow-band-reorder";
import {
  addMemberToParallelGroup,
  compactWorkflowStepOrdersPreservingTies,
  insertParallelApprovalSteps,
  parallelModeLabel,
  removeParallelApprovalGroup,
  setParallelGroupMode,
  type ParallelApprovalGroup,
  type ParallelCompletionMode,
} from "@/modules/e-approval/workflow-parallel-groups";
import {
  collectWorkflowPreviewFieldNames,
  mergeFieldMapMappings,
  stepRunsAlways,
  whenSummary,
} from "@/modules/e-approval/workflow-conditions";
import {
  getApproverFieldOptions,
  getApproverListFieldOptions,
  getUsedApproverFieldIds,
  getValidEApprovalWorkflowSteps,
  hasValidEApprovalWorkflowSteps,
  pickNextApproverFieldId,
  pickNextApproverListFieldId,
  suggestNextWorkflowStep,
} from "@/modules/e-approval/workflow-steps";
import {
  getConditionFieldOptions,
  getFieldMapSourceOptions,
} from "@/modules/e-approval/workflow-rules";
import { parseSelectChoices } from "@/modules/e-approval/field-options";
import { E_APPROVAL_WORKFLOW_SHELL_CLASS } from "@/modules/e-approval/form-layout";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  onStepsChange: (steps: EApprovalWorkflowStepInput[]) => void;
  approverOptions: { id: string; label: string }[];
  formId?: string;
};

type StepCardProps = {
  step: EApprovalWorkflowStepInput;
  index: number;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  approverOptions: { id: string; label: string }[];
  approverFieldOptions: { id: string; label: string }[];
  approverListFieldOptions: { id: string; label: string }[];
  fieldMapSourceOptions: { id: string; label: string }[];
  showWhen: boolean;
  onToggleWhen: () => void;
  onUpdate: (patch: Partial<EApprovalWorkflowStepInput>) => void;
  onRemove: () => void;
  /** When set, replaces the default “next step” footer (used inside If/Else groups). */
  footerOverride?: ReactNode;
  compactHeader?: boolean;
  /** Hide free-form when editor (If/Else / ladder thresholds are edited at group level). */
  hideWhenEditor?: boolean;
};

function approverLabelForStep(
  step: EApprovalWorkflowStepInput,
  approverOptions: { id: string; label: string }[],
  approverFieldOptions: { id: string; label: string }[],
  approverListFieldOptions: { id: string; label: string }[],
  fieldMapSourceOptions: { id: string; label: string }[],
): string {
  if (step.type === "manager") {
    return "Direct manager (Entra)";
  }
  if (step.type === "field") {
    const field = approverFieldOptions.find((item) => item.id === step.approverId);
    return field ? `Approver field: ${field.label}` : "Approver field";
  }
  if (step.type === "user_list") {
    const field = approverListFieldOptions.find((item) => item.id === step.approverId);
    const mode = step.parallel_mode ?? "all";
    const modeHint =
      mode === "any"
        ? "any one"
        : mode === "n_of_m"
          ? `≥${step.parallel_quorum ?? 1}`
          : "all";
    return field
      ? `List: ${field.label} (${modeHint})`
      : "Approver list (dynamic N)";
  }
  if (step.type === "field_map") {
    const field = fieldMapSourceOptions.find((item) => item.id === (step.source_field ?? step.approverId));
    return field ? `Mapped from ${field.label}` : "Mapped field value";
  }
  if (step.type === "role") {
    return step.approverId ? `Role: ${step.approverId}` : "Role";
  }
  const user = approverOptions.find((item) => item.id === step.approverId);
  return user?.label ?? "Fixed user";
}

function WorkflowStepCard({
  step,
  index,
  fields,
  steps,
  approverOptions,
  approverFieldOptions,
  approverListFieldOptions,
  fieldMapSourceOptions,
  showWhen,
  onToggleWhen,
  onUpdate,
  onRemove,
  footerOverride,
  compactHeader,
  hideWhenEditor = false,
}: StepCardProps) {
  const alwaysRuns = stepRunsAlways(step);
  const nextStep = steps[index + 1];
  const nextStepNumber = nextStep ? (nextStep.step_order ?? index + 2) : null;
  const stepNumber = step.step_order ?? index + 1;

  return (
    <article className="min-w-0 flex-1 rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-medium">
            {compactHeader
              ? `Step ${stepNumber}`
              : approverLabelForStep(
                  step,
                  approverOptions,
                  approverFieldOptions,
                  approverListFieldOptions,
                  fieldMapSourceOptions,
                )}
          </p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {compactHeader
              ? approverLabelForStep(
                  step,
                  approverOptions,
                  approverFieldOptions,
                  approverListFieldOptions,
                  fieldMapSourceOptions,
                )
              : alwaysRuns
                ? "Always runs"
                : `Runs when: ${whenSummary(step, fields)}`}
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          variant="ghost"
          className="text-muted-foreground hover:text-destructive"
          onClick={onRemove}
        >
          <Trash2 className="mr-1 h-3.5 w-3.5" />
          Remove
        </Button>
      </div>

      <div className="mt-4 grid gap-3">
        <label className="space-y-1">
          <span className="text-xs font-medium text-muted-foreground">Step type</span>
          <Select
            value={step.type}
            onChange={(e) => {
              const type = e.target.value;
              let nextStep: EApprovalWorkflowStepInput = { ...step, type };
              if (type === "manager") {
                nextStep = { ...nextStep, approverId: undefined };
              } else if (type === "field") {
                nextStep = {
                  ...nextStep,
                  approverId: pickNextApproverFieldId(fields, steps, index) || step.approverId || "",
                  parallel_mode: undefined,
                  parallel_quorum: undefined,
                };
              } else if (type === "user_list") {
                nextStep = {
                  ...nextStep,
                  approverId: pickNextApproverListFieldId(fields, steps, index) || step.approverId || "",
                  parallel_mode: step.parallel_mode ?? "all",
                };
              } else if (type === "field_map") {
                const sourceField = fieldMapSourceOptions[0]?.id ?? "";
                const field = fields.find((item) => item.name === sourceField);
                const choices = field ? parseSelectChoices(field) : [];
                nextStep = {
                  ...nextStep,
                  source_field: sourceField,
                  mappings:
                    choices.length > 0
                      ? mergeFieldMapMappings({}, choices)
                      : {},
                  default_approver_id: undefined,
                  parallel_mode: undefined,
                  parallel_quorum: undefined,
                };
              } else if (type === "user" && !step.approverId?.trim()) {
                nextStep = { ...nextStep, approverId: approverOptions[0]?.id ?? "" };
              }
              onUpdate(nextStep);
            }}
          >
            {E_APPROVAL_STEP_TYPES.map((type) => (
              <option key={type.value} value={type.value}>
                {type.label}
              </option>
            ))}
          </Select>
          {step.type === "field_map" ? (
            <p className="text-[11px] text-muted-foreground">
              Value → approver in one step. For exclusive ≤ / &gt; paths with different steps, use If/Else branch.
            </p>
          ) : null}
          {step.type === "user_list" ? (
            <p className="text-[11px] text-muted-foreground">
              Expands the selected multi-approver field into a parallel band at submit.
            </p>
          ) : null}
        </label>

        {step.type === "manager" ? (
          <p className="flex items-end pb-2 text-xs text-muted-foreground">Resolved from Entra at submit</p>
        ) : step.type === "field" ? (
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Approver field</span>
            <Select
              value={step.approverId ?? ""}
              onChange={(e) => onUpdate({ approverId: e.target.value })}
            >
              <option value="" disabled>
                Select field
              </option>
              {approverFieldOptions
                .filter(
                  (field) =>
                    field.id === step.approverId || !getUsedApproverFieldIds(steps, index).has(field.id),
                )
                .map((field) => (
                  <option key={field.id} value={field.id}>
                    {field.label}
                  </option>
                ))}
            </Select>
          </label>
        ) : step.type === "user_list" ? (
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Approver list field</span>
            <Select
              value={step.approverId ?? ""}
              onChange={(e) => onUpdate({ approverId: e.target.value })}
            >
              <option value="" disabled>
                {approverListFieldOptions.length === 0 ? "Add an Approver list field first" : "Select field"}
              </option>
              {approverListFieldOptions
                .filter(
                  (field) =>
                    field.id === step.approverId || !getUsedApproverFieldIds(steps, index).has(field.id),
                )
                .map((field) => (
                  <option key={field.id} value={field.id}>
                    {field.label}
                  </option>
                ))}
            </Select>
          </label>
        ) : step.type === "field_map" ? (
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Source field</span>
            <Select
              value={step.source_field ?? ""}
              onChange={(e) => {
                const sourceField = e.target.value;
                const field = fields.find((item) => item.name === sourceField);
                const choices = field ? parseSelectChoices(field) : [];
                onUpdate({
                  source_field: sourceField,
                  mappings:
                    choices.length > 0
                      ? mergeFieldMapMappings(step.mappings ?? {}, choices)
                      : step.mappings ?? {},
                });
              }}
            >
              <option value="" disabled>
                Select field
              </option>
              {fieldMapSourceOptions.map((field) => (
                <option key={field.id} value={field.id}>
                  {field.label}
                </option>
              ))}
            </Select>
          </label>
        ) : (
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Approver</span>
            <Select
              value={step.approverId ?? ""}
              onChange={(e) => onUpdate({ approverId: e.target.value })}
            >
              <option value="">Select user</option>
              {approverOptions.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.label}
                </option>
              ))}
            </Select>
          </label>
        )}
      </div>

      {step.type === "user_list" ? (
        <div className="mt-3 grid gap-3">
          <label className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">Completion rule</span>
            <Select
              value={step.parallel_mode ?? "all"}
              onChange={(e) => {
                const mode = e.target.value as ParallelCompletionMode;
                onUpdate({
                  parallel_mode: mode === "all" ? undefined : mode,
                  parallel_quorum: mode === "n_of_m" ? step.parallel_quorum ?? 1 : undefined,
                });
              }}
            >
              <option value="all">All must approve</option>
              <option value="any">Any one can approve</option>
              <option value="n_of_m">At least N of M</option>
            </Select>
          </label>
          {(step.parallel_mode ?? "all") === "n_of_m" ? (
            <label className="space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Required approvals (N)</span>
              <Input
                type="number"
                min={1}
                value={step.parallel_quorum ?? 1}
                onChange={(e) => onUpdate({ parallel_quorum: Math.max(1, Number(e.target.value) || 1) })}
              />
            </label>
          ) : null}
        </div>
      ) : null}

      {step.type !== "field_map" ? (
        <label className="mt-3 block space-y-1">
          <span className="text-xs font-medium text-muted-foreground">Fallback approver (optional)</span>
          <Select
            value={step.fallback_approver_id ?? ""}
            onChange={(e) => onUpdate({ fallback_approver_id: e.target.value || undefined })}
          >
            <option value="">None</option>
            {approverOptions.map((user) => (
              <option key={user.id} value={user.id}>
                {user.label}
              </option>
            ))}
          </Select>
          <p className="text-[11px] text-muted-foreground">
            Used when primary resolution fails (manager lookup, empty field, inactive user, or role with no
            member).
          </p>
        </label>
      ) : null}

      {step.type === "field_map" ? (
        <div className="mt-3">
          <EApprovalWorkflowFieldMapEditor
            fields={fields}
            step={step}
            onChange={(nextStep) => onUpdate(nextStep)}
            approverOptions={approverOptions}
          />
        </div>
      ) : null}

      {hideWhenEditor ? null : (
        <div className="mt-4">
          <button
            type="button"
            className="flex items-center gap-1 text-xs font-medium text-primary"
            onClick={onToggleWhen}
          >
            {showWhen ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
            {showWhen ? "Hide conditions" : "Edit conditions"}
          </button>
          {showWhen ? (
            <div className="mt-2">
              <EApprovalWorkflowStepConditions
                fields={fields}
                step={step}
                onChange={(nextStep: EApprovalWorkflowStepInput) => onUpdate(nextStep)}
              />
            </div>
          ) : null}
        </div>
      )}

      {footerOverride != null ? (
        footerOverride
      ) : (
        <div className="mt-4 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
          {nextStepNumber != null ? (
            alwaysRuns ? (
              <p>
                After this step finishes → continues to{" "}
                <span className="font-medium text-foreground">Step {nextStepNumber}</span>
                {nextStep
                  ? ` (${approverLabelForStep(nextStep, approverOptions, approverFieldOptions, approverListFieldOptions, fieldMapSourceOptions)})`
                  : ""}
                .
              </p>
            ) : (
              <ul className="space-y-1">
                <li>
                  If conditions match → run this step, then{" "}
                  <span className="font-medium text-foreground">Step {nextStepNumber}</span>
                  {nextStep
                    ? ` (${approverLabelForStep(nextStep, approverOptions, approverFieldOptions, approverListFieldOptions, fieldMapSourceOptions)})`
                    : ""}
                  .
                </li>
                <li>
                  If conditions do not match → skip this step, go to{" "}
                  <span className="font-medium text-foreground">Step {nextStepNumber}</span>
                  {nextStep
                    ? ` (${approverLabelForStep(nextStep, approverOptions, approverFieldOptions, approverListFieldOptions, fieldMapSourceOptions)})`
                    : ""}
                  .
                </li>
              </ul>
            )
          ) : (
            <p>
              {alwaysRuns
                ? "After this step finishes → workflow ends (final step)."
                : "If conditions match → run this final step, then workflow ends. If not → skip and end."}
            </p>
          )}
        </div>
      )}
    </article>
  );
}

function findSegmentForStepIndex(
  segments: WorkflowEditorSegment[],
  index: number,
): WorkflowEditorSegment | null {
  for (const segment of segments) {
    if (segment.type === "single" && segment.index === index) {
      return segment;
    }
    if (segment.type === "parallel" && segment.group.memberIndexes.includes(index)) {
      return segment;
    }
    if (
      segment.type === "branch" &&
      (segment.group.lowIndex === index || segment.group.highIndex === index)
    ) {
      return segment;
    }
    if (segment.type === "ladder" && segment.ladder.bandIndexes.includes(index)) {
      return segment;
    }
  }
  return null;
}

export function EApprovalWorkflowEditor({ fields, steps, onStepsChange, approverOptions, formId }: Props) {
  const push = useNotificationStore((s) => s.push);
  const [managerTestEmail, setManagerTestEmail] = useState("");
  const [managerTestResult, setManagerTestResult] = useState<string | null>(null);
  const [selectedStepIndex, setSelectedStepIndex] = useState<number | null>(null);
  const [expandedWhen, setExpandedWhen] = useState<Record<number, boolean>>({});
  const [previewValues, setPreviewValues] = useState<Record<string, string>>({});
  const [branchDialogOpen, setBranchDialogOpen] = useState(false);
  const [branchInsertAt, setBranchInsertAt] = useState<number | null>(null);
  const [branchMode, setBranchMode] = useState<"if_else" | "ladder" | "parallel">("if_else");
  const [branchField, setBranchField] = useState("");
  const [branchThreshold, setBranchThreshold] = useState("5000");
  const [branchIfApproverId, setBranchIfApproverId] = useState("");
  const [branchElseApproverId, setBranchElseApproverId] = useState("");
  const [ladderThresholds, setLadderThresholds] = useState<string[]>(["5000", "20000"]);
  const [ladderApproverIds, setLadderApproverIds] = useState<string[]>(["", "", ""]);
  const [parallelApproverIds, setParallelApproverIds] = useState<string[]>(["", ""]);
  const [parallelMode, setParallelMode] = useState<ParallelCompletionMode>("all");
  const [parallelQuorum, setParallelQuorum] = useState(2);

  const hasManagerStep = steps.some((s) => s.type === "manager");
  const approverFieldOptions = useMemo(() => getApproverFieldOptions(fields), [fields]);
  const approverListFieldOptions = useMemo(() => getApproverListFieldOptions(fields), [fields]);
  const fieldMapSourceOptions = useMemo(() => getFieldMapSourceOptions(fields), [fields]);
  const conditionFieldOptions = useMemo(() => getConditionFieldOptions(fields), [fields]);
  const previewFieldNames = useMemo(
    () => collectWorkflowPreviewFieldNames(fields, steps),
    [fields, steps],
  );
  const validSteps = useMemo(() => getValidEApprovalWorkflowSteps(steps), [steps]);
  const incompleteStepCount = steps.length - validSteps.length;
  const segments = useMemo(() => toWorkflowEditorSegments(steps), [steps]);
  const nearMissPairs = useMemo(() => detectNearMissBranchPairs(steps), [steps]);
  const selectedSegment =
    selectedStepIndex != null ? findSegmentForStepIndex(segments, selectedStepIndex) : null;

  useEffect(() => {
    if (steps.length === 0) {
      setSelectedStepIndex(null);
      return;
    }
    setSelectedStepIndex((prev) => {
      if (prev == null || prev >= steps.length) {
        return 0;
      }
      return prev;
    });
  }, [steps.length]);

  const addSuggestedStep = (atIndex?: number) => {
    const nextStep = suggestNextWorkflowStep(fields, steps, approverOptions);
    const insertAt = atIndex ?? steps.length;
    const next = insertWorkflowStepAt(steps, nextStep, insertAt);
    onStepsChange(next);
    setSelectedStepIndex(Math.max(0, Math.min(insertAt, next.length - 1)));
  };

  const managerTestMutation = useMutation({
    mutationFn: () => testEApprovalManagerLookup(managerTestEmail.trim()),
    onSuccess: (data) => {
      setManagerTestResult(data.message);
      push({
        level: data.ok ? "success" : "warning",
        title: data.ok ? "Manager lookup OK" : "Manager lookup",
        message: data.message,
      });
    },
    onError: (e) => {
      const msg = getErrorMessage(e);
      setManagerTestResult(msg);
      push({ level: "error", title: "Lookup failed", message: msg });
    },
  });

  const previewMutation = useMutation({
    mutationFn: () => {
      const payload: Record<string, string> = {};
      for (const fieldName of previewFieldNames) {
        payload[fieldName] = previewValues[fieldName] ?? "";
      }
      return previewEApprovalFormWorkflow(formId!, payload, managerTestEmail);
    },
    onError: (error) => {
      push({ level: "error", title: "Preview failed", message: getErrorMessage(error) });
    },
  });

  const updateStep = (index: number, patch: Partial<EApprovalWorkflowStepInput>) => {
    const next = [...steps];
    next[index] = { ...steps[index], ...patch };
    onStepsChange(next);
  };

  const removeStep = (index: number) => {
    const next = compactWorkflowStepOrdersPreservingTies(
      steps.filter((_, itemIndex) => itemIndex !== index),
    );
    onStepsChange(next);
    setSelectedStepIndex((prev) => {
      if (prev == null) {
        return next.length > 0 ? 0 : null;
      }
      if (prev === index) {
        return next.length > 0 ? Math.min(index, next.length - 1) : null;
      }
      if (prev > index) {
        return prev - 1;
      }
      return prev;
    });
  };

  const removeBranch = (group: ExclusiveBranchGroup) => {
    const remove = new Set([group.lowIndex, group.highIndex]);
    onStepsChange(
      compactWorkflowStepOrdersPreservingTies(steps.filter((_, itemIndex) => !remove.has(itemIndex))),
    );
  };

  const removeLadder = (ladder: ExclusiveThresholdLadder) => {
    const remove = new Set(ladder.bandIndexes);
    onStepsChange(
      compactWorkflowStepOrdersPreservingTies(steps.filter((_, itemIndex) => !remove.has(itemIndex))),
    );
  };

  const removeParallel = (group: ParallelApprovalGroup) => {
    onStepsChange(removeParallelApprovalGroup(steps, group));
  };

  const openBranchDialog = (
    insertAt?: number,
    mode: "if_else" | "ladder" | "parallel" = "if_else",
  ) => {
    const defaultField =
      conditionFieldOptions.find((field) =>
        ["amount", "requested_amount", "non_po", "non-po"].some((hint) =>
          field.id.toLowerCase().includes(hint),
        ),
      )?.id ??
      conditionFieldOptions[0]?.id ??
      "";
    const first = approverOptions[0]?.id ?? "";
    const second = approverOptions[1]?.id ?? first;
    const third = approverOptions[2]?.id ?? second;
    setBranchInsertAt(insertAt ?? steps.length);
    setBranchMode(mode);
    setBranchField(defaultField);
    setBranchThreshold("5000");
    setBranchIfApproverId(first);
    setBranchElseApproverId(second);
    setLadderThresholds(["5000", "20000"]);
    setLadderApproverIds([first, second, third]);
    setParallelApproverIds([first, second]);
    setParallelMode("all");
    setParallelQuorum(2);
    setBranchDialogOpen(true);
  };

  const handleInsert = (kind: WorkflowAddKind, insertAt: number) => {
    if (kind === "step") {
      addSuggestedStep(insertAt);
      return;
    }
    openBranchDialog(insertAt, kind);
  };

  const syncLadderApproverSlots = (thresholdCount: number, current: string[]): string[] => {
    const bandCount = thresholdCount + 1;
    const next = current.slice(0, bandCount);
    while (next.length < bandCount) {
      next.push(approverOptions[next.length]?.id ?? approverOptions[0]?.id ?? "");
    }
    return next;
  };

  const confirmBranchDialog = () => {
    if (branchMode !== "parallel" && !branchField.trim()) {
      push({ level: "warning", title: "Branch incomplete", message: "Choose a condition field." });
      return;
    }

    if (branchMode === "if_else") {
      if (!branchThreshold.trim()) {
        push({ level: "warning", title: "Branch incomplete", message: "Choose a threshold." });
        return;
      }
      if (!branchIfApproverId.trim() || !branchElseApproverId.trim()) {
        push({ level: "warning", title: "Branch incomplete", message: "Choose approvers for both branches." });
        return;
      }

      onStepsChange(
        insertIfElseBranchSteps(
          steps,
          {
            field: branchField,
            threshold: branchThreshold,
            ifApproverId: branchIfApproverId,
            elseApproverId: branchElseApproverId,
          },
          branchInsertAt ?? steps.length,
        ),
      );
      setBranchDialogOpen(false);
      setBranchInsertAt(null);
      push({
        level: "success",
        title: "If/Else branch added",
        message: "Two complementary steps were inserted. Shared steps below still run after either path.",
      });
      return;
    }

    if (branchMode === "parallel") {
      const approverIds = parallelApproverIds.map((id) => id.trim()).filter(Boolean);
      if (approverIds.length < 2) {
        push({
          level: "warning",
          title: "Parallel group incomplete",
          message: "Choose at least two approvers who must all approve.",
        });
        return;
      }

      onStepsChange(
        insertParallelApprovalSteps(
          steps,
          { approverIds, mode: parallelMode, quorum: parallelQuorum },
          branchInsertAt ?? steps.length,
        ),
      );
      setBranchDialogOpen(false);
      setBranchInsertAt(null);
      push({
        level: "success",
        title: "Parallel group added",
        message: `${approverIds.length} approvers share one step — ${parallelModeLabel(parallelMode, parallelQuorum, approverIds.length).toLowerCase()}.`,
      });
      return;
    }

    const thresholds = ladderThresholds.map((value) => value.trim()).filter(Boolean);
    if (thresholds.length < 1) {
      push({ level: "warning", title: "Ladder incomplete", message: "Add at least one threshold boundary." });
      return;
    }
    if (ladderApproverIds.length !== thresholds.length + 1 || ladderApproverIds.some((id) => !id.trim())) {
      push({
        level: "warning",
        title: "Ladder incomplete",
        message: "Assign an approver for each band (one more than the number of thresholds).",
      });
      return;
    }

    onStepsChange(
      insertThresholdLadderSteps(
        steps,
        {
          field: branchField,
          thresholds,
          approverIds: ladderApproverIds,
        },
        branchInsertAt ?? steps.length,
      ),
    );
    setBranchDialogOpen(false);
    setBranchInsertAt(null);
    push({
      level: "success",
      title: "Threshold ladder added",
      message: `${thresholds.length + 1} exclusive bands were inserted. Only one band runs per submission.`,
    });
  };

  const alignNearMiss = (nearMiss: NearMissBranchPair, threshold: string) => {
    onStepsChange(alignNearMissBranchThresholds(steps, nearMiss, threshold));
    push({
      level: "success",
      title: "Thresholds aligned",
      message: `Both steps now use ${threshold} — shown as one If/Else block.`,
    });
  };

  const fieldLabel = (fieldId: string): string =>
    conditionFieldOptions.find((field) => field.id === fieldId)?.label ?? fieldId;

  const renderStepCard = (
    index: number,
    options?: {
      footerOverride?: ReactNode;
      compactHeader?: boolean;
      hideWhenEditor?: boolean;
    },
  ) => {
    const step = steps[index];
    const showWhen = expandedWhen[index] ?? !stepRunsAlways(step);

    return (
      <WorkflowStepCard
        key={step.id ?? `workflow-step-${index}`}
        step={step}
        index={index}
        fields={fields}
        steps={steps}
        approverOptions={approverOptions}
        approverFieldOptions={approverFieldOptions}
        approverListFieldOptions={approverListFieldOptions}
        fieldMapSourceOptions={fieldMapSourceOptions}
        showWhen={showWhen}
        onToggleWhen={() => setExpandedWhen((prev) => ({ ...prev, [index]: !showWhen }))}
        onUpdate={(patch) => updateStep(index, patch)}
        onRemove={() => removeStep(index)}
        footerOverride={options?.footerOverride}
        compactHeader={options?.compactHeader}
        hideWhenEditor={options?.hideWhenEditor}
      />
    );
  };

  return (
    <section className={E_APPROVAL_WORKFLOW_SHELL_CLASS}>
      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h2 className="text-base font-medium">Approval workflow</h2>
            <p className="mt-1 text-xs text-muted-foreground">
            Build on the path diagram: Start → steps / parallel bands → End. Use <span className="font-medium">Add</span>{" "}
            to insert a step, If/Else, threshold ladder, or parallel group. Click a card to configure it in the
            side panel.
          </p>
        </div>
      </div>

      {steps.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border px-4 py-8 text-center">
          <p className="text-sm text-muted-foreground">No approval steps yet. Choose what to add to the path.</p>
          <div className="mt-3 flex flex-wrap justify-center gap-2">
            <EApprovalWorkflowAddMenu
              label="Add to path"
              variant="secondary"
              align="center"
              onSelect={(kind) => handleInsert(kind, 0)}
            />
          </div>
        </div>
      ) : null}

      {!hasValidEApprovalWorkflowSteps(steps) && steps.length > 0 ? (
        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
          One or more steps are incomplete. Assign an approver before publishing.
        </p>
      ) : null}
      {hasValidEApprovalWorkflowSteps(steps) && incompleteStepCount > 0 ? (
        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
          {incompleteStepCount} step{incompleteStepCount === 1 ? "" : "s"} still need
          {incompleteStepCount === 1 ? "s" : ""} an approver.
        </p>
      ) : null}

      {steps.length > 0 ? (
        <EApprovalWorkflowOrderDiagram
          steps={steps}
          fields={fields}
          onStepsChange={onStepsChange}
          selectedStepIndex={selectedStepIndex}
          onSelectStep={setSelectedStepIndex}
          onInsert={handleInsert}
          titleForStep={(step) =>
            approverLabelForStep(
              step,
              approverOptions,
              approverFieldOptions,
              approverListFieldOptions,
              fieldMapSourceOptions,
            )
          }
          subtitleForStep={(step) => {
            const typeLabel = workflowStepTypeLabel(step.type);
            if (step.type === "user" && step.approverId) {
              const user = approverOptions.find((item) => item.id === step.approverId);
              if (user?.label) {
                const emailMatch = user.label.match(/[^\s@]+@[^\s@]+\.[^\s@]+/);
                return emailMatch ? `${typeLabel} · ${emailMatch[0]}` : typeLabel;
              }
            }
            return typeLabel;
          }}
        />
      ) : null}

      {nearMissPairs.length > 0 ? (
        <div className="space-y-2">
          {nearMissPairs.map((nearMiss) => (
            <div
              key={`near-miss-${nearMiss.startIndex}`}
              className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-900/50 dark:bg-amber-950/30"
            >
              <p className="text-xs text-amber-950 dark:text-amber-100">
                Steps {nearMiss.lowIndex + 1} and {nearMiss.highIndex + 1} look like If/Else on{" "}
                <span className="font-medium">{fieldLabel(nearMiss.field)}</span>, but thresholds differ (
                <span className="font-medium">{nearMiss.lowThreshold}</span> vs{" "}
                <span className="font-medium">{nearMiss.highThreshold}</span>). They stay separate until both
                use the same number.
              </p>
              <div className="mt-2 flex flex-wrap gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  onClick={() => alignNearMiss(nearMiss, nearMiss.lowThreshold)}
                >
                  Align both to {nearMiss.lowThreshold}
                </Button>
                {nearMiss.highThreshold !== nearMiss.lowThreshold ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => alignNearMiss(nearMiss, nearMiss.highThreshold)}
                  >
                    Align both to {nearMiss.highThreshold}
                  </Button>
                ) : null}
              </div>
            </div>
          ))}
        </div>
      ) : null}

      <Sheet
        open={selectedStepIndex != null && steps[selectedStepIndex] != null}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedStepIndex(null);
          }
        }}
      >
        <SheetContent
          side="right"
          className="w-full gap-0 p-0 sm:max-w-xl md:max-w-2xl"
          showCloseButton
        >
          {selectedStepIndex != null && steps[selectedStepIndex] ? (
            <>
              <SheetHeader className="shrink-0 border-b border-border pr-12">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <SheetTitle>
                      Edit step {(steps[selectedStepIndex].step_order ?? selectedStepIndex + 1)}
                    </SheetTitle>
                    <SheetDescription>
                      Path stays visible. Changes apply to the canvas immediately.
                    </SheetDescription>
                  </div>
                  <EApprovalWorkflowAddMenu
                    label="Add after"
                    variant="outline"
                    onSelect={(kind) => {
                      const segment = selectedSegment;
                      let insertAt = selectedStepIndex + 1;
                      if (segment?.type === "parallel") {
                        insertAt = Math.max(...segment.group.memberIndexes) + 1;
                      } else if (segment?.type === "branch") {
                        insertAt = Math.max(segment.group.lowIndex, segment.group.highIndex) + 1;
                      } else if (segment?.type === "ladder") {
                        insertAt = segment.ladder.endIndex + 1;
                      }
                      handleInsert(kind, insertAt);
                    }}
                  />
                </div>
              </SheetHeader>

              <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                {selectedSegment?.type === "parallel" ? (
                  <div className="rounded-lg border border-violet-200 bg-violet-50/40 px-3 py-2 dark:border-violet-900/60 dark:bg-violet-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-xs text-muted-foreground">
                        Parallel band —{" "}
                        {parallelModeLabel(
                          selectedSegment.group.mode,
                          selectedSegment.group.quorum,
                          selectedSegment.group.memberIndexes.length,
                        )}
                      </p>
                      <div className="flex flex-wrap gap-1">
                        <Select
                          value={selectedSegment.group.mode}
                          className="h-8 w-auto min-w-[10rem] text-xs"
                          onChange={(e) => {
                            const mode = e.target.value as ParallelCompletionMode;
                            const group = selectedSegment.group;
                            onStepsChange(
                              setParallelGroupMode(
                                steps,
                                group,
                                mode,
                                mode === "n_of_m"
                                  ? Math.min(group.quorum, group.memberIndexes.length)
                                  : group.quorum,
                              ),
                            );
                          }}
                        >
                          <option value="all">All must approve</option>
                          <option value="any">Any one can approve</option>
                          <option value="n_of_m">At least N of M</option>
                        </Select>
                        {selectedSegment.group.mode === "n_of_m" ? (
                          <Select
                            value={String(selectedSegment.group.quorum)}
                            className="h-8 w-20 text-xs"
                            onChange={(e) =>
                              onStepsChange(
                                setParallelGroupMode(
                                  steps,
                                  selectedSegment.group,
                                  "n_of_m",
                                  Number(e.target.value),
                                ),
                              )
                            }
                          >
                            {selectedSegment.group.memberIndexes.map((_, index) => (
                              <option key={`quorum-${index + 1}`} value={index + 1}>
                                {index + 1}
                              </option>
                            ))}
                          </Select>
                        ) : null}
                        {(() => {
                          const unusedApprover = approverOptions.find(
                            (user) =>
                              !selectedSegment.group.memberIndexes.some(
                                (memberIndex) => steps[memberIndex].approverId === user.id,
                              ),
                          );
                          return unusedApprover ? (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                onStepsChange(
                                  addMemberToParallelGroup(
                                    steps,
                                    selectedSegment.group,
                                    unusedApprover.id,
                                  ),
                                )
                              }
                            >
                              <Plus className="mr-1 h-3.5 w-3.5" />
                              Add approver
                            </Button>
                          ) : null;
                        })()}
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            removeParallel(selectedSegment.group);
                            setSelectedStepIndex(null);
                          }}
                        >
                          Remove group
                        </Button>
                      </div>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-1">
                      {selectedSegment.group.memberIndexes.map((memberIndex, memberOffset) => (
                        <Button
                          key={`parallel-chip-${memberIndex}`}
                          type="button"
                          size="sm"
                          variant={memberIndex === selectedStepIndex ? "secondary" : "outline"}
                          onClick={() => setSelectedStepIndex(memberIndex)}
                        >
                          Approver {memberOffset + 1}
                        </Button>
                      ))}
                    </div>
                  </div>
                ) : null}

                {selectedSegment?.type === "branch" ? (
                  <div className="space-y-3 rounded-lg border border-sky-200 bg-sky-50/40 px-3 py-3 dark:border-sky-900/60 dark:bg-sky-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-xs font-medium text-foreground">
                        {branchGroupLabel(selectedSegment.group, fieldLabel(selectedSegment.group.field)).header}
                      </p>
                      <div className="flex flex-wrap gap-1">
                        <Button
                          type="button"
                          size="sm"
                          variant={
                            selectedSegment.group.lowIndex === selectedStepIndex ? "secondary" : "outline"
                          }
                          onClick={() => setSelectedStepIndex(selectedSegment.group.lowIndex)}
                        >
                          If
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant={
                            selectedSegment.group.highIndex === selectedStepIndex ? "secondary" : "outline"
                          }
                          onClick={() => setSelectedStepIndex(selectedSegment.group.highIndex)}
                        >
                          Else
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            removeBranch(selectedSegment.group);
                            setSelectedStepIndex(null);
                          }}
                        >
                          Remove branch
                        </Button>
                      </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      One shared threshold keeps both cases exclusive. Changing it updates If and Else together.
                    </p>
                    <div className="grid gap-2 sm:grid-cols-2">
                      <label className="space-y-1">
                        <span className="text-xs font-medium text-muted-foreground">Field</span>
                        <Select
                          value={selectedSegment.group.field}
                          onChange={(e) =>
                            onStepsChange(
                              updateExclusiveBranchThreshold(
                                steps,
                                selectedSegment.group,
                                e.target.value,
                                selectedSegment.group.threshold,
                              ),
                            )
                          }
                        >
                          {conditionFieldOptions.map((field) => (
                            <option key={field.id} value={field.id}>
                              {field.label}
                            </option>
                          ))}
                        </Select>
                      </label>
                      <label className="space-y-1">
                        <span className="text-xs font-medium text-muted-foreground">Threshold</span>
                        <Input
                          key={`branch-threshold-${selectedSegment.group.startIndex}-${selectedSegment.group.threshold}`}
                          defaultValue={selectedSegment.group.threshold}
                          inputMode="decimal"
                          onBlur={(e) => {
                            const next = e.target.value.trim();
                            if (!next || next === selectedSegment.group.threshold) {
                              return;
                            }
                            onStepsChange(
                              updateExclusiveBranchThreshold(
                                steps,
                                selectedSegment.group,
                                selectedSegment.group.field,
                                next,
                              ),
                            );
                          }}
                        />
                      </label>
                    </div>
                  </div>
                ) : null}

                {selectedSegment?.type === "ladder" ? (
                  <div className="space-y-3 rounded-lg border border-sky-200 bg-sky-50/40 px-3 py-3 dark:border-sky-900/60 dark:bg-sky-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-xs font-medium text-foreground">
                        {ladderGroupLabel(
                          selectedSegment.ladder,
                          fieldLabel(selectedSegment.ladder.field),
                        ).header}
                      </p>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                          removeLadder(selectedSegment.ladder);
                          setSelectedStepIndex(null);
                        }}
                      >
                        Remove ladder
                      </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      Edit boundaries here — all case cards stay side-by-side. Editing a single step&apos;s
                      conditions separately would break the exclusive group.
                    </p>
                    <label className="block space-y-1">
                      <span className="text-xs font-medium text-muted-foreground">Field</span>
                      <Select
                        value={selectedSegment.ladder.field}
                        onChange={(e) =>
                          onStepsChange(
                            updateExclusiveLadderThresholds(
                              steps,
                              selectedSegment.ladder,
                              e.target.value,
                              selectedSegment.ladder.thresholds,
                            ),
                          )
                        }
                      >
                        {conditionFieldOptions.map((field) => (
                          <option key={field.id} value={field.id}>
                            {field.label}
                          </option>
                        ))}
                      </Select>
                    </label>
                    <div className="space-y-2">
                      <span className="text-xs font-medium text-muted-foreground">
                        Thresholds ({selectedSegment.ladder.thresholds.length} boundary
                        {selectedSegment.ladder.thresholds.length === 1 ? "" : "ies"} →{" "}
                        {selectedSegment.ladder.bandIndexes.length} cases)
                      </span>
                      {selectedSegment.ladder.thresholds.map((threshold, thresholdIndex) => (
                        <Input
                          key={`ladder-threshold-edit-${selectedSegment.ladder.startIndex}-${thresholdIndex}-${threshold}`}
                          defaultValue={threshold}
                          inputMode="decimal"
                          placeholder={`Boundary ${thresholdIndex + 1}`}
                          onBlur={(e) => {
                            const nextValue = e.target.value.trim();
                            const nextThresholds = [...selectedSegment.ladder.thresholds];
                            if (!nextValue || nextValue === nextThresholds[thresholdIndex]) {
                              e.target.value = nextThresholds[thresholdIndex] ?? "";
                              return;
                            }
                            nextThresholds[thresholdIndex] = nextValue;
                            onStepsChange(
                              updateExclusiveLadderThresholds(
                                steps,
                                selectedSegment.ladder,
                                selectedSegment.ladder.field,
                                nextThresholds,
                              ),
                            );
                          }}
                        />
                      ))}
                    </div>
                    <div className="flex flex-wrap gap-1">
                      {selectedSegment.ladder.bandIndexes.map((bandIndex, bandOffset) => {
                        const band = parseThresholdBand(steps[bandIndex]);
                        const title = band
                          ? thresholdBandLabel(band, fieldLabel(selectedSegment.ladder.field))
                          : `Band ${bandOffset + 1}`;
                        return (
                          <Button
                            key={`ladder-chip-${bandIndex}`}
                            type="button"
                            size="sm"
                            variant={bandIndex === selectedStepIndex ? "secondary" : "outline"}
                            onClick={() => setSelectedStepIndex(bandIndex)}
                          >
                            {title}
                          </Button>
                        );
                      })}
                    </div>
                  </div>
                ) : null}

                {renderStepCard(selectedStepIndex, {
                  compactHeader:
                    selectedSegment?.type === "parallel" ||
                    selectedSegment?.type === "branch" ||
                    selectedSegment?.type === "ladder",
                  hideWhenEditor:
                    selectedSegment?.type === "branch" || selectedSegment?.type === "ladder",
                  footerOverride:
                    selectedSegment?.type === "parallel" ? (
                      <div className="mt-4 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                        Approves in parallel with the other members of this step.
                      </div>
                    ) : selectedSegment?.type === "branch" || selectedSegment?.type === "ladder" ? (
                      <div className="mt-4 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                        When this band matches → run this step, then merge to shared path. Change thresholds
                        in the panel above (not per-step conditions).
                      </div>
                    ) : undefined,
                })}
              </div>
            </>
          ) : null}
        </SheetContent>
      </Sheet>

      {hasManagerStep ? (
        <div className="space-y-2 rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-foreground">Test Entra manager lookup</p>
          <div className="flex flex-wrap gap-2">
            <Input
              type="email"
              className="h-9 min-w-[200px] flex-1 text-sm"
              placeholder="requestor@company.com"
              value={managerTestEmail}
              onChange={(e) => setManagerTestEmail(e.target.value)}
            />
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={!managerTestEmail.trim() || managerTestMutation.isPending}
              onClick={() => managerTestMutation.mutate()}
            >
              {managerTestMutation.isPending ? <Spinner className="mr-1 size-3.5" /> : null}
              Test lookup
            </Button>
          </div>
          {managerTestResult ? (
            <p
              className={
                managerTestMutation.data?.ok === false || (!managerTestMutation.data && managerTestResult)
                  ? "text-xs text-destructive"
                  : "text-xs text-muted-foreground"
              }
            >
              {managerTestResult}
            </p>
          ) : null}
        </div>
      ) : null}

      {formId ? (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div>
            <h3 className="text-sm font-medium">Preview approval path</h3>
            <p className="mt-1 text-xs text-muted-foreground">
              Enter sample field values (e.g. Non-PO), then preview. Matching steps appear under Runs; the other
              If/Else or ladder cases appear under Skipped. Save the form first — preview uses saved step
              definitions, not unsaved editor drafts.
            </p>
          </div>
          {hasManagerStep ? (
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Requestor email (for manager steps)</span>
              <Input
                type="email"
                className="h-9 text-sm"
                placeholder="requestor@company.com"
                value={managerTestEmail}
                onChange={(e) => setManagerTestEmail(e.target.value)}
              />
            </label>
          ) : null}
          <EApprovalWorkflowPreviewFields
            fields={fields}
            fieldNames={previewFieldNames}
            values={previewValues}
            onChange={setPreviewValues}
          />
          <Button
            type="button"
            size="sm"
            variant="secondary"
            disabled={previewMutation.isPending || !formId}
            onClick={() => previewMutation.mutate()}
          >
            {previewMutation.isPending ? <Spinner className="mr-1" /> : null}
            Preview workflow
          </Button>
          {previewMutation.data ? (
            <div className="space-y-3">
              <p className="text-sm font-medium">
                {previewMutation.data.matched_rule_label ?? "No active steps"}
              </p>
              <EApprovalWorkflowPathDiagram preview={previewMutation.data} />
            </div>
          ) : null}
        </div>
      ) : null}

      <Dialog open={branchDialogOpen} onOpenChange={setBranchDialogOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Add routing group</DialogTitle>
            <DialogDescription>
              Insert exclusive If/Else or a threshold ladder, or a parallel group with a completion rule
              (all, any one, or at least N of M).
            </DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-3">
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Mode</span>
              <Select
                value={branchMode}
                onChange={(e) => {
                  const value = e.target.value;
                  if (value === "ladder" || value === "parallel" || value === "if_else") {
                    setBranchMode(value);
                  }
                }}
              >
                <option value="if_else">If / Else (one threshold)</option>
                <option value="ladder">Threshold ladder (multiple bands)</option>
                <option value="parallel">Parallel (all / any / N of M)</option>
              </Select>
            </label>
            {branchMode !== "parallel" ? (
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Condition field</span>
              <Select value={branchField} onChange={(e) => setBranchField(e.target.value)}>
                <option value="" disabled>
                  Select field
                </option>
                {conditionFieldOptions.map((field) => (
                  <option key={field.id} value={field.id}>
                    {field.label}
                  </option>
                ))}
              </Select>
            </label>
            ) : null}

            {branchMode === "parallel" ? (
              <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                  <span className="text-xs font-medium text-muted-foreground">Parallel approvers</span>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      setParallelApproverIds([
                        ...parallelApproverIds,
                        approverOptions[parallelApproverIds.length]?.id ?? approverOptions[0]?.id ?? "",
                      ])
                    }
                  >
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add approver
                  </Button>
                </div>
                {parallelApproverIds.map((approverId, index) => (
                  <div key={`parallel-dialog-${index}`} className="flex gap-2">
                    <Select
                      value={approverId}
                      onChange={(e) => {
                        const next = [...parallelApproverIds];
                        next[index] = e.target.value;
                        setParallelApproverIds(next);
                      }}
                    >
                      <option value="">Select user</option>
                      {approverOptions.map((user) => (
                        <option key={user.id} value={user.id}>
                          {user.label}
                        </option>
                      ))}
                    </Select>
                    {parallelApproverIds.length > 2 ? (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                          setParallelApproverIds(parallelApproverIds.filter((_, itemIndex) => itemIndex !== index))
                        }
                      >
                        Remove
                      </Button>
                    ) : null}
                  </div>
                ))}
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">Completion rule</span>
                  <Select
                    value={parallelMode}
                    onChange={(e) => setParallelMode(e.target.value as ParallelCompletionMode)}
                  >
                    <option value="all">All must approve</option>
                    <option value="any">Any one can approve</option>
                    <option value="n_of_m">At least N of M</option>
                  </Select>
                </label>
                {parallelMode === "n_of_m" ? (
                  <label className="block space-y-1">
                    <span className="text-xs font-medium text-muted-foreground">Required approvals (N)</span>
                    <Select
                      value={String(Math.min(parallelQuorum, parallelApproverIds.length))}
                      onChange={(e) => setParallelQuorum(Number(e.target.value))}
                    >
                      {parallelApproverIds.map((_, index) => (
                        <option key={`dialog-quorum-${index + 1}`} value={index + 1}>
                          {index + 1} of {parallelApproverIds.length}
                        </option>
                      ))}
                    </Select>
                  </label>
                ) : null}
                <p className="text-[11px] text-muted-foreground">
                  All listed approvers are notified together. Remaining pending members are cleared once the
                  completion rule is met.
                </p>
              </div>
            ) : branchMode === "if_else" ? (
              <>
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">Threshold</span>
                  <Input
                    value={branchThreshold}
                    onChange={(e) => setBranchThreshold(e.target.value)}
                    placeholder="5000"
                    inputMode="decimal"
                  />
                </label>
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">Approver when ≤ threshold (If)</span>
                  <Select value={branchIfApproverId} onChange={(e) => setBranchIfApproverId(e.target.value)}>
                    <option value="">Select user</option>
                    {approverOptions.map((user) => (
                      <option key={user.id} value={user.id}>
                        {user.label}
                      </option>
                    ))}
                  </Select>
                </label>
                <label className="block space-y-1">
                  <span className="text-xs font-medium text-muted-foreground">
                    Approver when &gt; threshold (Else)
                  </span>
                  <Select value={branchElseApproverId} onChange={(e) => setBranchElseApproverId(e.target.value)}>
                    <option value="">Select user</option>
                    {approverOptions.map((user) => (
                      <option key={user.id} value={user.id}>
                        {user.label}
                      </option>
                    ))}
                  </Select>
                </label>
              </>
            ) : (
              <>
                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-medium text-muted-foreground">Threshold boundaries</span>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      onClick={() => {
                        const next = [...ladderThresholds, ""];
                        setLadderThresholds(next);
                        setLadderApproverIds(syncLadderApproverSlots(next.length, ladderApproverIds));
                      }}
                    >
                      <Plus className="mr-1 h-3.5 w-3.5" />
                      Add boundary
                    </Button>
                  </div>
                  {ladderThresholds.map((value, index) => (
                    <div key={`ladder-threshold-${index}`} className="flex gap-2">
                      <Input
                        value={value}
                        onChange={(e) => {
                          const next = [...ladderThresholds];
                          next[index] = e.target.value;
                          setLadderThresholds(next);
                        }}
                        placeholder={index === 0 ? "5000" : "20000"}
                        inputMode="decimal"
                        className="h-9"
                      />
                      {ladderThresholds.length > 1 ? (
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            const next = ladderThresholds.filter((_, itemIndex) => itemIndex !== index);
                            setLadderThresholds(next);
                            setLadderApproverIds(syncLadderApproverSlots(next.length, ladderApproverIds));
                          }}
                        >
                          Remove
                        </Button>
                      ) : null}
                    </div>
                  ))}
                  <p className="text-[11px] text-muted-foreground">
                    Creates {ladderThresholds.filter((value) => value.trim()).length + 1 || 2} exclusive bands
                    (≤ first, ranges between, then &gt; last).
                  </p>
                </div>
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground">Approver per band</span>
                  {ladderApproverIds.map((approverId, index) => (
                    <label key={`ladder-approver-${index}`} className="block space-y-1">
                      <span className="text-[11px] text-muted-foreground">Band {index + 1}</span>
                      <Select
                        value={approverId}
                        onChange={(e) => {
                          const next = [...ladderApproverIds];
                          next[index] = e.target.value;
                          setLadderApproverIds(next);
                        }}
                      >
                        <option value="">Select user</option>
                        {approverOptions.map((user) => (
                          <option key={user.id} value={user.id}>
                            {user.label}
                          </option>
                        ))}
                      </Select>
                    </label>
                  ))}
                </div>
              </>
            )}

            <p className="text-[11px] text-muted-foreground">
              Works for any comparable field. Routing is always evaluated from the submission values.
            </p>
          </DialogBody>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setBranchDialogOpen(false)}>
              Cancel
            </Button>
            <Button type="button" onClick={confirmBranchDialog}>
              {branchMode === "ladder"
                ? "Insert ladder"
                : branchMode === "parallel"
                  ? "Insert parallel group"
                  : "Insert branch"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </section>
  );
}
