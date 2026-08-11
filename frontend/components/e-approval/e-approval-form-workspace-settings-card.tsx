"use client";

import Link from "next/link";
import { useMemo } from "react";
import { ExternalLink } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
  isIsoDocumentControlMetadata,
  isValidWorkspaceSlug,
  slugifyWorkspaceSlug,
  suggestIsoPilotWorkspaceSettings,
  workspaceEditorReadiness,
  workspacePreviewHref,
  type FormWorkspaceEditorSettings,
} from "@/modules/e-approval/form-workspace-config";
import { cn } from "@/lib/utils";

type Props = {
  value: FormWorkspaceEditorSettings;
  onChange: (next: FormWorkspaceEditorSettings) => void;
  formName: string;
  formId?: string;
  formPublished: boolean;
  metadata: Record<string, unknown>;
  disabled?: boolean;
};

export function EApprovalFormWorkspaceSettingsCard({
  value,
  onChange,
  formName,
  formId,
  formPublished,
  metadata,
  disabled,
}: Props) {
  const patch = (partial: Partial<FormWorkspaceEditorSettings>) => {
    onChange({ ...value, ...partial });
  };

  const readiness = useMemo(() => workspaceEditorReadiness(value), [value]);
  const slugPreview = slugifyWorkspaceSlug(value.slug || formName);
  const showIsoPreset = isIsoDocumentControlMetadata(metadata);
  const previewHref = workspacePreviewHref(slugPreview);

  return (
    <EApprovalSectionCard
      title="Form workspace"
      description="Give this form its own dashboard with KPIs, filtered submissions, and a sidebar entry — no code required."
    >
      <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
        <span>
          <span className="block text-sm font-medium text-foreground">Enable workspace</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">
            Published forms get a dedicated page at /e-approval/w/your-slug.
          </span>
        </span>
        <Switch
          checked={value.enabled}
          disabled={disabled}
          onCheckedChange={(checked) => {
            if (checked && !value.slug.trim()) {
              onChange({
                ...value,
                enabled: true,
                slug: slugifyWorkspaceSlug(formName),
                title: value.title.trim() || formName.trim(),
              });
              return;
            }
            patch({ enabled: checked });
          }}
        />
      </label>

      {value.enabled ? (
        <div className="mt-4 space-y-4">
          {!readiness.ok ? (
            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
              {readiness.message}
            </p>
          ) : null}

          {showIsoPreset ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={disabled}
                onClick={() => onChange(suggestIsoPilotWorkspaceSettings(formName))}
              >
                Apply ISO pilot defaults
              </Button>
              <span className="text-xs text-muted-foreground">Slug: iso-approval · focused new request</span>
            </div>
          ) : null}

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="ea-workspace-title">Workspace title</Label>
              <Input
                id="ea-workspace-title"
                value={value.title}
                disabled={disabled}
                onChange={(e) => patch({ title: e.target.value })}
                placeholder={formName}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="ea-workspace-slug">URL slug</Label>
              <Input
                id="ea-workspace-slug"
                value={value.slug}
                disabled={disabled}
                onChange={(e) => patch({ slug: e.target.value })}
                placeholder={slugifyWorkspaceSlug(formName)}
                className={cn(!isValidWorkspaceSlug(slugPreview) && value.slug.trim() !== "" && "border-destructive")}
              />
              <p className="text-xs text-muted-foreground">/e-approval/w/{slugPreview || "your-slug"}</p>
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="ea-workspace-desc">Description</Label>
              <Textarea
                id="ea-workspace-desc"
                className="min-h-[72px]"
                value={value.description}
                disabled={disabled}
                onChange={(e) => patch({ description: e.target.value })}
                placeholder="Shown on the workspace dashboard header."
              />
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="ea-workspace-visibility">Coordinator visibility</Label>
              <Select
                id="ea-workspace-visibility"
                value={value.visibility}
                disabled={disabled}
                onChange={(e) =>
                  patch({ visibility: e.target.value as FormWorkspaceEditorSettings["visibility"] })
                }
              >
                <option value="own">Requestors see own submissions only</option>
                <option value="workspace_all">Form admins see all for this form</option>
                <option value="tenant_all">Auditors see all (uses audit permission)</option>
              </Select>
              <p className="text-xs text-muted-foreground">
                Approvers always see items assigned to them. Auditors with audit:view see everything.
              </p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="ea-workspace-request-mode">New request opens in</Label>
              <Select
                id="ea-workspace-request-mode"
                value={value.new_request_mode}
                disabled={disabled}
                onChange={(e) =>
                  patch({ new_request_mode: e.target.value as "focused" | "standard" })
                }
              >
                <option value="focused">Focused view (minimal chrome)</option>
                <option value="standard">Standard request page</option>
              </Select>
            </div>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
              <span className="text-sm text-foreground">Show in sidebar</span>
              <Switch
                checked={value.show_in_sidebar}
                disabled={disabled}
                onCheckedChange={(checked) => patch({ show_in_sidebar: checked })}
              />
            </label>
            <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
              <span className="text-sm text-foreground">Show export on workspace</span>
              <Switch
                checked={value.show_export}
                disabled={disabled}
                onCheckedChange={(checked) => patch({ show_export: checked })}
              />
            </label>
          </div>

          {formId && formPublished && readiness.ok ? (
            <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
              <Link
                href={previewHref}
                target="_blank"
                rel="noopener noreferrer"
                className={buttonVariants({ size: "sm", variant: "outline" })}
              >
                <ExternalLink className="h-3.5 w-3.5" />
                Open workspace preview
              </Link>
              <span className="text-xs text-muted-foreground">Save the form first if you changed settings.</span>
            </div>
          ) : null}
        </div>
      ) : null}
    </EApprovalSectionCard>
  );
}
