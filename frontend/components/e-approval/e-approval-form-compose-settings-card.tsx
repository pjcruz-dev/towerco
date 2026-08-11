"use client";

import { useMemo, useState } from "react";
import { Eye } from "lucide-react";

import { EApprovalFormComposePreviewDialog } from "@/components/e-approval/e-approval-form-compose-preview-dialog";
import { EApprovalFormRevisionSettingsCard } from "@/components/e-approval/e-approval-form-revision-settings-card";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { buildFormComposeSteps } from "@/modules/e-approval/form-compose-steps";
import {
  buildFormComposeDesignSummary,
  formComposeReadiness,
  type FormComposeEditorSettings,
} from "@/modules/e-approval/form-compose-config";
import { isComposeFillableFieldType, resolveEffectiveStepSource } from "@/modules/e-approval/form-compose-structural";
import type { FormRevisionEditorSettings } from "@/modules/e-approval/form-revision-config";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  value: FormComposeEditorSettings;
  onChange: (next: FormComposeEditorSettings) => void;
  fields: EApprovalFormFieldInput[];
  formName: string;
  formDescription?: string | null;
  approverOptions: { id: string; label: string }[];
  metadata: Record<string, unknown>;
  disabled?: boolean;
  revisionSettings?: FormRevisionEditorSettings;
  onRevisionSettingsChange?: (next: FormRevisionEditorSettings) => void;
};

export function EApprovalFormComposeSettingsCard({
  value,
  onChange,
  fields,
  formName,
  formDescription,
  approverOptions,
  metadata,
  disabled,
  revisionSettings,
  onRevisionSettingsChange,
}: Props) {
  const [previewOpen, setPreviewOpen] = useState(false);
  const patch = (partial: Partial<FormComposeEditorSettings>) => {
    onChange({ ...value, ...partial });
  };

  const readiness = useMemo(() => formComposeReadiness(value, fields), [value, fields]);
  const summary = useMemo(() => buildFormComposeDesignSummary(value, fields), [value, fields]);
  const steps = useMemo(() => buildFormComposeSteps(fields, value.stepSource), [fields, value.stepSource]);
  const stepped = value.mode === "stepped";
  const effectiveStepSource = useMemo(
    () => resolveEffectiveStepSource(fields, value.stepSource),
    [fields, value.stepSource],
  );

  return (
    <>
      <EApprovalSectionCard
        title="Form layout"
        description="Configure how requestors move through the form. Stepped mode uses sections or page breaks to define wizard steps."
      >
        <div className="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <span className="rounded-md border border-border bg-muted/30 px-2 py-1 font-medium text-foreground">
            {summary.modeLabel}
          </span>
          <span className="rounded-md border border-border bg-muted/30 px-2 py-1 tabular-nums">
            {summary.fillableFieldCount} fillable field{summary.fillableFieldCount === 1 ? "" : "s"}
          </span>
          {stepped ? (
            <span className="rounded-md border border-border bg-muted/30 px-2 py-1 tabular-nums">
              {summary.stepCount} step{summary.stepCount === 1 ? "" : "s"}
            </span>
          ) : null}
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="ea-compose-mode">Compose layout</Label>
            <Select
              id="ea-compose-mode"
              value={value.mode}
              disabled={disabled}
              onChange={(event) => {
                const mode = event.target.value === "stepped" ? "stepped" : "single_page";
                patch({ mode });
              }}
            >
              <option value="single_page">Single page (scroll)</option>
              <option value="stepped">Stepped (wizard)</option>
            </Select>
            <p className="text-xs text-muted-foreground">
              {stepped
                ? effectiveStepSource === "page_breaks"
                  ? "Requestors see one step at a time, split at each Page break field. Submit is enabled on the last step only."
                  : "Requestors see one section at a time with Next / Back. Submit is enabled on the last step only."
                : "All fields appear on one scrollable page. Long forms may show section progress while filling."}
            </p>
          </div>

          {stepped ? (
            <div className="space-y-2">
              <Label htmlFor="ea-compose-step-source">Step boundaries</Label>
              <Select
                id="ea-compose-step-source"
                value={value.stepSource}
                disabled={disabled}
                onChange={(event) => {
                  const raw = event.target.value;
                  const stepSource =
                    raw === "page_breaks" ? "page_breaks" : raw === "auto" ? "auto" : "sections";
                  patch({ stepSource });
                }}
              >
                <option value="auto">Auto (page breaks when present)</option>
                <option value="sections">Section headings</option>
                <option value="page_breaks">Page breaks only</option>
              </Select>
              <p className="text-xs text-muted-foreground">
                {value.stepSource === "auto"
                  ? `Currently using ${effectiveStepSource === "page_breaks" ? "page breaks" : "sections"}.`
                  : value.stepSource === "page_breaks"
                    ? "Add Page break fields on the canvas between step content."
                    : "Add Section fields on the canvas to title each step."}
              </p>
            </div>
          ) : null}

          {stepped ? (
            <div className="space-y-3 rounded-lg border border-border/80 bg-muted/20 p-3 md:col-span-2">
              <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-foreground">Show step progress</span>
                <Switch
                  checked={value.showProgress}
                  disabled={disabled}
                  onCheckedChange={(checked) => patch({ showProgress: checked })}
                />
              </label>
              <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-foreground">Validate on Next</span>
                <Switch
                  checked={value.validateOnNext}
                  disabled={disabled}
                  onCheckedChange={(checked) => patch({ validateOnNext: checked })}
                />
              </label>
              <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-foreground">Allow Back</span>
                <Switch
                  checked={value.allowBack}
                  disabled={disabled}
                  onCheckedChange={(checked) => patch({ allowBack: checked })}
                />
              </label>
              <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-foreground">Review & submit step</span>
                <Switch
                  checked={value.includeReviewStep}
                  disabled={disabled}
                  onCheckedChange={(checked) => patch({ includeReviewStep: checked })}
                />
              </label>
              <p className="text-xs text-muted-foreground">
                When on, requestors see a summary (payee, amount, bank, cost charge, and other answers)
                before Submit. Steps turn green only after they pass validation.
              </p>
            </div>
          ) : null}
        </div>

        {stepped && !readiness.ready ? (
          <p className={cn("mt-3 text-sm text-amber-800 dark:text-amber-200")} role="status">
            {readiness.message}
          </p>
        ) : null}

        {stepped && steps.length >= 2 ? (
          <ol className="mt-4 space-y-1 border-t border-border/60 pt-3 text-xs text-muted-foreground">
            {steps.map((step, index) => (
              <li key={step.id}>
                <span className="font-medium text-foreground">
                  Step {index + 1} — {step.label}
                </span>
                <span className="ml-2 tabular-nums">
                  ({step.fields.filter((field) => isComposeFillableFieldType(field.type)).length} fields)
                </span>
              </li>
            ))}
            {value.includeReviewStep ? (
              <li>
                <span className="font-medium text-foreground">
                  Step {steps.length + 1} — Review & submit
                </span>
                <span className="ml-2">summary before submit</span>
              </li>
            ) : null}
          </ol>
        ) : null}
      </EApprovalSectionCard>

      <div className="pointer-events-none fixed top-20 right-5 z-40 sm:top-24 sm:right-6">
        <Button
          type="button"
          size="sm"
          className="pointer-events-auto h-10 shadow-lg"
          disabled={disabled || fields.length === 0}
          onClick={() => setPreviewOpen(true)}
        >
          <Eye className="mr-1.5 h-4 w-4" />
          Preview requestor view
        </Button>
      </div>

      {revisionSettings && onRevisionSettingsChange ? (
        <div className="mt-4">
          <EApprovalFormRevisionSettingsCard
            value={revisionSettings}
            onChange={onRevisionSettingsChange}
            fields={fields}
            disabled={disabled}
          />
        </div>
      ) : null}

      <EApprovalFormComposePreviewDialog
        open={previewOpen}
        onOpenChange={setPreviewOpen}
        formName={formName}
        formDescription={formDescription}
        fields={fields}
        approverOptions={approverOptions}
        composeSettings={value}
        metadata={metadata}
      />
    </>
  );
}
