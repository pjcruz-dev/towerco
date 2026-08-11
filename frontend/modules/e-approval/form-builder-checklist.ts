import { parseSelectChoices } from "@/modules/e-approval/field-options";
import {
  formComposeReadiness,
  type FormComposeEditorSettings,
} from "@/modules/e-approval/form-compose-config";
import { resolveEffectiveStepSource } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import { detectNearMissBranchPairs } from "@/modules/e-approval/workflow-branch-groups";
import { parseStepWhen, stepRunsAlways } from "@/modules/e-approval/workflow-conditions";
import {
  detectParallelApprovalGroups,
  parallelModeLabel,
} from "@/modules/e-approval/workflow-parallel-groups";
import { isWorkflowConditionComplete } from "@/modules/e-approval/workflow-rules";
import {
  getValidEApprovalWorkflowSteps,
  hasValidEApprovalWorkflowSteps,
} from "@/modules/e-approval/workflow-steps";

export type FormBuilderCheckItem = {
  level: "error" | "warning";
  message: string;
};

export function buildFormPublishChecklist(input: {
  formName: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  requireWorkflow: boolean;
  pendingSubmissionsCount?: number;
  planFeatures?: { file_uploads: boolean; max_file_fields: number | null; plan_tier: string };
  composeSettings?: FormComposeEditorSettings;
}): FormBuilderCheckItem[] {
  const items: FormBuilderCheckItem[] = [];
  const trimmedName = input.formName.trim();

  if (!trimmedName) {
    items.push({ level: "error", message: "Form name is required." });
  }

  if (input.fields.length === 0) {
    items.push({ level: "error", message: "Add at least one form field." });
  }

  if (input.composeSettings) {
    const composeReady = formComposeReadiness(input.composeSettings, input.fields);
    if (!composeReady.ready && composeReady.message) {
      items.push({ level: "error", message: composeReady.message });
    } else if (input.composeSettings.mode === "stepped" && composeReady.stepCount >= 2) {
      const effective = resolveEffectiveStepSource(input.fields, input.composeSettings.stepSource);
      const boundary =
        effective === "page_breaks" ? "page break boundaries" : "section headings";
      items.push({
        level: "warning",
        message: `Requestors will complete this form in ${composeReady.stepCount} steps (split by ${boundary}).`,
      });
    }
  }

  const nameCounts = new Map<string, number>();
  for (const field of input.fields) {
    const key = field.name?.trim();
    if (!key) {
      items.push({ level: "error", message: `Field "${field.label || "(unnamed)"}" is missing an API key.` });
      continue;
    }
    nameCounts.set(key, (nameCounts.get(key) ?? 0) + 1);
  }

  for (const [key, count] of nameCounts) {
    if (count > 1) {
      items.push({ level: "error", message: `Duplicate API key "${key}" — each field must have a unique name.` });
    }
  }

  for (const field of input.fields) {
    if (field.type === "divider" || field.type === "page_break") {
      continue;
    }
    if (!field.label?.trim()) {
      items.push({
        level: "error",
        message: `Field "${field.name}" needs a visible label.`,
      });
    }
    if ((field.type === "select" || field.type === "radio") && parseSelectChoices(field).length === 0) {
      const opts = field.options as Record<string, unknown> | null | undefined;
      const masterKey = typeof opts?.master_data_key === "string" ? opts.master_data_key.trim() : "";
      if (!masterKey) {
        items.push({
          level: "warning",
          message: `"${field.label}" has no dropdown options or master data set.`,
        });
      }
    }
  }

  const fileFields = input.fields.filter((f) => f.type === "file");
  const plan = input.planFeatures;
  if (fileFields.length > 0 && plan && !plan.file_uploads) {
    items.push({
      level: "error",
      message: `File upload fields require a Professional or Enterprise plan (current: ${plan.plan_tier}).`,
    });
  }
  if (fileFields.length > 0 && plan?.file_uploads && plan.max_file_fields !== null && fileFields.length > plan.max_file_fields) {
    items.push({
      level: "error",
      message: `Your ${plan.plan_tier} plan allows at most ${plan.max_file_fields} file field(s).`,
    });
  }

  const pending = input.pendingSubmissionsCount ?? 0;
  if (pending > 0) {
    items.push({
      level: "warning",
      message: `${pending} open submission${pending === 1 ? "" : "s"} on this form. In-flight requests keep their submit-time workflow; new requests use the updated definition after publish.`,
    });
    items.push({
      level: "warning",
      message:
        "If you changed field keys or workflow steps, confirm you understand the impact — or clone this form for a major upgrade.",
    });
  }

  if (input.requireWorkflow) {
    appendWorkflowHealthChecks(items, input.fields, input.steps);
  }

  return items;
}

function appendWorkflowHealthChecks(
  items: FormBuilderCheckItem[],
  fields: EApprovalFormFieldInput[],
  steps: EApprovalWorkflowStepInput[],
): void {
  if (!hasValidEApprovalWorkflowSteps(steps)) {
    items.push({
      level: "error",
      message:
        "Add at least one complete workflow step (fixed user, approver field, approver list, mapped field, or direct manager).",
    });
  } else {
    const incomplete = steps.length - getValidEApprovalWorkflowSteps(steps).length;
    if (incomplete > 0) {
      items.push({
        level: "warning",
        message: `${incomplete} workflow step${incomplete === 1 ? "" : "s"} still need an approver assignment.`,
      });
    }
  }

  if (steps.some((s) => s.type === "manager")) {
    items.push({
      level: "warning",
      message:
        "Workflow includes Direct manager (Entra). Set a fallback approver and test lookup with a sample requestor email before go-live.",
    });
  }

  if (steps.some((step) => step.type === "user_list")) {
    if (!fields.some((field) => field.type === "approver_list")) {
      items.push({
        level: "error",
        message: "Dynamic approver list steps require an Approver list (multi) field on the form.",
      });
    } else {
      items.push({
        level: "warning",
        message:
          "Workflow includes a dynamic approver list — at submit it expands into a parallel band from the selected users (all / any / N of M).",
      });
    }
  }

  if (steps.length > 0 && steps.every((step) => !stepRunsAlways(step))) {
    items.push({
      level: "warning",
      message:
        "Every workflow step has conditions. Add at least one always-on step so submissions still route when no fork matches.",
    });
  }

  let incompleteWhen = 0;
  for (const step of steps) {
    for (const condition of parseStepWhen(step)) {
      if (!isWorkflowConditionComplete(condition)) {
        incompleteWhen += 1;
      }
    }
  }
  if (incompleteWhen > 0) {
    items.push({
      level: "warning",
      message: `${incompleteWhen} workflow condition${incompleteWhen === 1 ? "" : "s"} incomplete (missing field or value).`,
    });
  }

  const nearMisses = detectNearMissBranchPairs(steps);
  for (const nearMiss of nearMisses) {
    items.push({
      level: "warning",
      message: `Steps ${nearMiss.lowIndex + 1} and ${nearMiss.highIndex + 1} look like If/Else on "${nearMiss.field}" but thresholds differ (${nearMiss.lowThreshold} vs ${nearMiss.highThreshold}). Align them to group as one branch.`,
    });
  }

  for (const group of detectParallelApprovalGroups(steps)) {
    items.push({
      level: "warning",
      message: `Step ${group.stepOrder} is a parallel band with ${group.memberIndexes.length} approvers — ${parallelModeLabel(group.mode, group.quorum, group.memberIndexes.length).toLowerCase()} before the workflow continues.`,
    });
  }

  for (const [index, step] of steps.entries()) {
    if (step.type !== "field_map") {
      continue;
    }

    const sourceField = (step.source_field ?? step.approverId ?? "").trim();
    const field = fields.find((item) => item.name === sourceField);
    const choices = field ? parseSelectChoices(field) : [];
    const mappings = step.mappings ?? {};
    const hasDefault = Boolean(step.default_approver_id?.trim());
    const mappedKeys = new Set(
      Object.entries(mappings)
        .filter(([, userId]) => Boolean(String(userId).trim()))
        .map(([key]) => key),
    );

    if (choices.length > 0) {
      const unmapped = choices.filter((choice) => !mappedKeys.has(choice.value));
      if (unmapped.length > 0 && !hasDefault) {
        items.push({
          level: "warning",
          message: `Step ${index + 1} (mapped field): ${unmapped.length} option${unmapped.length === 1 ? "" : "s"} unmapped and no default approver — those values will fail to assign.`,
        });
      }
    } else if (!hasDefault && mappedKeys.size === 0) {
      items.push({
        level: "warning",
        message: `Step ${index + 1} (mapped field): add mappings or a default approver before publish.`,
      });
    }
  }

  const dynamicWithoutFallback = steps.filter(
    (step) =>
      (step.type === "manager" ||
        step.type === "field" ||
        step.type === "user_list" ||
        step.type === "role") &&
      !step.fallback_approver_id?.trim(),
  );
  if (dynamicWithoutFallback.length > 0) {
    items.push({
      level: "warning",
      message: `${dynamicWithoutFallback.length} dynamic step${dynamicWithoutFallback.length === 1 ? "" : "s"} (manager / field / list / role) have no fallback approver. If resolution fails at submit, that step is skipped.`,
    });
  }
}

export function checklistHasBlockingErrors(items: FormBuilderCheckItem[]): boolean {
  return items.some((i) => i.level === "error");
}
