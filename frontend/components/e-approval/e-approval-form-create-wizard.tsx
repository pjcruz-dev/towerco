"use client";

import { useMutation } from "@tanstack/react-query";
import { ArrowLeft, ArrowRight, Check, FileStack, FileText, PenLine, Workflow } from "lucide-react";
import { useMemo, useState } from "react";

import { EApprovalFormTemplateGallery } from "@/components/e-approval/e-approval-form-template-gallery";
import { EApprovalWorkflowEditor } from "@/components/e-approval/e-approval-workflow-editor";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { createEApprovalForm } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";
import {
  getValidEApprovalWorkflowSteps,
  hasValidEApprovalWorkflowSteps,
} from "@/modules/e-approval/workflow-steps";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type StarterMode = "summary" | "minimal" | "empty";

type Props = {
  name: string;
  description: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  approverOptions: { id: string; label: string }[];
  onNameChange: (value: string) => void;
  onDescriptionChange: (value: string) => void;
  onFieldsChange: (fields: EApprovalFormFieldInput[]) => void;
  onStepsChange: (steps: EApprovalWorkflowStepInput[]) => void;
  onTemplateCreated: (formId: string) => void;
  onOpenImport: () => void;
  onSkipToEditor: () => void;
  onCreated: (formId: string) => void;
};

const STEPS = [
  { id: "start", label: "Start", icon: FileStack },
  { id: "details", label: "Details", icon: FileText },
  { id: "fields", label: "Fields", icon: PenLine },
  { id: "workflow", label: "Workflow", icon: Workflow },
  { id: "review", label: "Review", icon: Check },
] as const;

type StepId = (typeof STEPS)[number]["id"];

function buildStarterFields(mode: StarterMode): EApprovalFormFieldInput[] {
  if (mode === "empty") {
    return [];
  }
  if (mode === "minimal") {
    return [defaultFieldForType("text", 0)];
  }

  return [
    defaultFieldForType("text", 0),
    { ...defaultFieldForType("approver", 1), label: "Approver", name: "approver" },
  ];
}

export function EApprovalFormCreateWizard({
  name,
  description,
  fields,
  steps,
  approverOptions,
  onNameChange,
  onDescriptionChange,
  onFieldsChange,
  onStepsChange,
  onTemplateCreated,
  onOpenImport,
  onSkipToEditor,
  onCreated,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const [stepIndex, setStepIndex] = useState(0);
  const [starterMode, setStarterMode] = useState<StarterMode>("summary");

  const step = STEPS[stepIndex]!.id;
  const workflowReady = hasValidEApprovalWorkflowSteps(steps);
  const validSteps = useMemo(() => getValidEApprovalWorkflowSteps(steps), [steps]);

  const createMutation = useMutation({
    mutationFn: () => {
      const trimmedName = name.trim();
      if (!trimmedName) {
        throw new Error("Form name is required.");
      }
      if (!workflowReady) {
        throw new Error("Add at least one workflow step before creating the form.");
      }

      return createEApprovalForm({
        name: trimmedName,
        description: description.trim() || null,
        status: "draft",
        fields,
        steps: validSteps.map((s, i) => ({ ...s, step_order: s.step_order ?? i + 1 })),
        metadata_json: null,
        brand_logo_url: null,
      });
    },
    onSuccess: (form) => {
      push({ level: "success", title: "Form created" });
      onCreated(form.id);
    },
    onError: (e) => push({ level: "error", title: "Create failed", message: getErrorMessage(e) }),
  });

  const goNext = () => {
    if (step === "details" && !name.trim()) {
      push({ level: "warning", title: "Form name required", message: "Enter a name before continuing." });
      return;
    }
    if (step === "fields") {
      onFieldsChange(buildStarterFields(starterMode));
      if (steps.length === 0 && approverOptions[0]) {
        onStepsChange([{ type: "user", approverId: approverOptions[0].id, step_order: 1 }]);
      }
    }
    setStepIndex((i) => Math.min(i + 1, STEPS.length - 1));
  };

  const goBack = () => setStepIndex((i) => Math.max(i - 1, 0));

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">Create form</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Step {stepIndex + 1} of {STEPS.length} — guided setup, then open the full editor.
          </p>
        </div>
        <Button type="button" size="sm" variant="ghost" onClick={onSkipToEditor}>
          Skip to full editor
        </Button>
      </div>

      <nav aria-label="Wizard progress" className="flex flex-wrap gap-2">
        {STEPS.map((s, i) => {
          const Icon = s.icon;
          const active = i === stepIndex;
          const done = i < stepIndex;
          return (
            <div
              key={s.id}
              className={cn(
                "flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium",
                active ? "border-primary bg-primary/5 text-primary" : "border-border text-muted-foreground",
                done && !active && "border-primary/30 text-foreground",
              )}
            >
              <Icon className="h-3.5 w-3.5" />
              {s.label}
            </div>
          );
        })}
      </nav>

      <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
        {step === "start" ? (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">Choose how to start. Templates create a draft immediately.</p>
            <EApprovalFormTemplateGallery persistMinimize={false} onCreated={onTemplateCreated} />
            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" onClick={onOpenImport}>
                Import JSON
              </Button>
              <Button type="button" size="sm" onClick={goNext}>
                Start blank
                <ArrowRight className="ml-1 h-4 w-4" />
              </Button>
            </div>
          </div>
        ) : null}

        {step === "details" ? (
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="wizard-form-name">Form name</Label>
              <Input
                id="wizard-form-name"
                value={name}
                onChange={(e) => onNameChange(e.target.value)}
                placeholder="e.g. Leave request"
                autoFocus
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="wizard-form-desc">Description</Label>
              <Textarea
                id="wizard-form-desc"
                className="min-h-[100px]"
                value={description}
                onChange={(e) => onDescriptionChange(e.target.value)}
                placeholder="Shown when requestors pick this form."
              />
            </div>
          </div>
        ) : null}

        {step === "fields" ? (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Pick a starter layout. You can add more fields in the Design tab after create.</p>
            <div className="grid gap-2 sm:grid-cols-3">
              {(
                [
                  { id: "summary" as const, title: "Summary + approver", desc: "Text field and approver picker" },
                  { id: "minimal" as const, title: "Single field", desc: "One short text field" },
                  { id: "empty" as const, title: "Empty canvas", desc: "Add fields from the catalog later" },
                ] as const
              ).map((opt) => (
                <button
                  key={opt.id}
                  type="button"
                  onClick={() => setStarterMode(opt.id)}
                  className={cn(
                    "rounded-xl border p-3 text-left transition-colors",
                    starterMode === opt.id
                      ? "border-primary bg-primary/5"
                      : "border-border hover:border-primary/30",
                  )}
                >
                  <p className="text-sm font-medium text-foreground">{opt.title}</p>
                  <p className="mt-1 text-xs text-muted-foreground">{opt.desc}</p>
                </button>
              ))}
            </div>
          </div>
        ) : null}

        {step === "workflow" ? (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">At least one approval step is required before the form can be saved.</p>
            <EApprovalWorkflowEditor
              fields={fields.length > 0 ? fields : buildStarterFields(starterMode)}
              steps={steps}
              onStepsChange={onStepsChange}
              approverOptions={approverOptions}
            />
          </div>
        ) : null}

        {step === "review" ? (
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Name</dt>
              <dd className="font-medium text-foreground">{name.trim() || "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Starter fields</dt>
              <dd className="text-foreground">
                {starterMode === "empty" ? "Empty" : starterMode === "minimal" ? "1 field" : "2 fields"}
              </dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-xs font-medium text-muted-foreground">Description</dt>
              <dd className="text-foreground">{description.trim() || "—"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Workflow steps</dt>
              <dd className="text-foreground">{validSteps.length}</dd>
            </div>
          </dl>
        ) : null}
      </section>

      {step !== "start" ? (
        <div className="flex flex-wrap justify-between gap-2">
          <Button type="button" variant="outline" onClick={goBack} disabled={stepIndex === 0 || createMutation.isPending}>
            <ArrowLeft className="mr-1 h-4 w-4" />
            Back
          </Button>
          {step === "review" ? (
            <Button
              type="button"
              onClick={() => {
                onFieldsChange(buildStarterFields(starterMode));
                createMutation.mutate();
              }}
              disabled={!name.trim() || !workflowReady || createMutation.isPending}
            >
              {createMutation.isPending ? "Creating…" : "Create form"}
            </Button>
          ) : (
            <Button type="button" onClick={goNext}>
              Continue
              <ArrowRight className="ml-1 h-4 w-4" />
            </Button>
          )}
        </div>
      ) : null}
    </div>
  );
}
