"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { FileStack, Plus, Trash2 } from "lucide-react";

import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  createEApprovalFormFromTemplate,
  deleteEApprovalCustomFormTemplate,
  fetchEApprovalCustomFormTemplate,
  fetchEApprovalFormTemplates,
  saveEApprovalCustomFormTemplate,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { defaultFieldForType } from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const EMPTY_TEMPLATE = {
  name: "Custom template",
  description: "",
  category: "general",
  fields: [defaultFieldForType("text", 0)] as EApprovalFormFieldInput[],
  steps: [] as EApprovalWorkflowStepInput[],
};

export function EApprovalFormTemplatesAdminPageClient() {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const [editingId, setEditingId] = useState<string | null>(null);
  const [draft, setDraft] = useState(EMPTY_TEMPLATE);
  const [fieldsJson, setFieldsJson] = useState(() => JSON.stringify(EMPTY_TEMPLATE.fields, null, 2));
  const [stepsJson, setStepsJson] = useState("[]");

  const templatesQuery = useQuery({
    queryKey: ["e-approval", "form-templates"],
    queryFn: fetchEApprovalFormTemplates,
  });

  const editQuery = useQuery({
    queryKey: ["e-approval", "form-template-custom", editingId],
    queryFn: () => fetchEApprovalCustomFormTemplate(editingId!),
    enabled: !!editingId,
  });

  const startNew = () => {
    setEditingId("new");
    setDraft(EMPTY_TEMPLATE);
    setFieldsJson(JSON.stringify(EMPTY_TEMPLATE.fields, null, 2));
    setStepsJson("[]");
  };

  const startEdit = (id: string) => {
    setEditingId(id);
  };

  useEffect(() => {
    if (!editQuery.data || editingId === "new") {
      return;
    }
    const t = editQuery.data;
    setDraft({
      name: t.name,
      description: t.description ?? "",
      category: t.category,
      fields: t.fields,
      steps: t.steps ?? [],
    });
    setFieldsJson(JSON.stringify(t.fields, null, 2));
    setStepsJson(JSON.stringify(t.steps ?? [], null, 2));
  }, [editQuery.data, editingId]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      let fields: EApprovalFormFieldInput[];
      let steps: EApprovalWorkflowStepInput[];
      try {
        fields = JSON.parse(fieldsJson) as EApprovalFormFieldInput[];
        steps = JSON.parse(stepsJson) as EApprovalWorkflowStepInput[];
      } catch {
        throw new Error("Fields and steps must be valid JSON.");
      }

      return saveEApprovalCustomFormTemplate({
        id: editingId === "new" ? undefined : editingId!,
        name: draft.name,
        description: draft.description || null,
        category: draft.category,
        fields,
        steps,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form-templates"] });
      push({ level: "success", title: "Template saved" });
      setEditingId(null);
    },
    onError: (e) => push({ level: "error", title: "Save failed", message: getErrorMessage(e) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteEApprovalCustomFormTemplate(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form-templates"] });
      push({ level: "success", title: "Template deleted" });
      if (editingId) {
        setEditingId(null);
      }
    },
    onError: (e) => push({ level: "error", title: "Delete failed", message: getErrorMessage(e) }),
  });

  const createFormMutation = useMutation({
    mutationFn: (templateId: string) => createEApprovalFormFromTemplate(templateId),
    onSuccess: (form) => {
      push({ level: "success", title: "Form created from template" });
      window.location.href = `/e-approval/forms/${form.id}`;
    },
    onError: (e) => push({ level: "error", title: "Create failed", message: getErrorMessage(e) }),
  });

  const templates = templatesQuery.data ?? [];
  const tenantTemplates = templates.filter((t) => t.source === "tenant");
  const systemTemplates = templates.filter((t) => t.source !== "tenant");

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalFormsManage]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title="Form templates"
          description={
            <>
              <EApprovalBackLink href="/e-approval/forms">Back to forms</EApprovalBackLink>
              {" · "}System templates ship with TowerOS. Tenant templates are stored per tenant.
            </>
          }
          actions={
            <Button type="button" size="sm" onClick={startNew}>
              <Plus className="mr-1 h-4 w-4" />
              New tenant template
            </Button>
          }
        />

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,400px)]">
          <div className="space-y-4">
            <EApprovalSectionCard title="System templates" description="Built-in definitions from application config (read-only).">
              <TemplateTable
                rows={systemTemplates}
                onUse={(id) => createFormMutation.mutate(id)}
                usePending={createFormMutation.isPending}
              />
            </EApprovalSectionCard>

            <EApprovalSectionCard title="Tenant templates" description="Custom templates saved for this tenant.">
              {tenantTemplates.length === 0 ? (
                <p className="text-sm text-muted-foreground">No custom templates yet.</p>
              ) : (
                <TemplateTable
                  rows={tenantTemplates}
                  onEdit={startEdit}
                  onDelete={(id) => {
                    if (window.confirm("Delete this tenant template?")) {
                      deleteMutation.mutate(id);
                    }
                  }}
                  onUse={(id) => createFormMutation.mutate(id)}
                  usePending={createFormMutation.isPending}
                  deletePending={deleteMutation.isPending}
                />
              )}
            </EApprovalSectionCard>
          </div>

          {editingId ? (
            <EApprovalSectionCard
              title={editingId === "new" ? "New tenant template" : "Edit tenant template"}
              description="Define fields and workflow steps as JSON (same shape as form import/export)."
            >
              <div className="space-y-3">
                <div className="space-y-1">
                  <Label htmlFor="tpl-name">Name</Label>
                  <Input
                    id="tpl-name"
                    value={draft.name}
                    onChange={(e) => setDraft((d) => ({ ...d, name: e.target.value }))}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="tpl-desc">Description</Label>
                  <Textarea
                    id="tpl-desc"
                    className="min-h-[60px]"
                    value={draft.description}
                    onChange={(e) => setDraft((d) => ({ ...d, description: e.target.value }))}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="tpl-cat">Category</Label>
                  <Input
                    id="tpl-cat"
                    value={draft.category}
                    onChange={(e) => setDraft((d) => ({ ...d, category: e.target.value }))}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="tpl-fields">Fields (JSON)</Label>
                  <Textarea
                    id="tpl-fields"
                    className="min-h-[160px] font-mono text-xs"
                    value={fieldsJson}
                    onChange={(e) => setFieldsJson(e.target.value)}
                    spellCheck={false}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="tpl-steps">Workflow steps (JSON)</Label>
                  <Textarea
                    id="tpl-steps"
                    className="min-h-[100px] font-mono text-xs"
                    value={stepsJson}
                    onChange={(e) => setStepsJson(e.target.value)}
                    spellCheck={false}
                  />
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" size="sm" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
                    {saveMutation.isPending ? "Saving…" : "Save template"}
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>
                    Cancel
                  </Button>
                </div>
              </div>
            </EApprovalSectionCard>
          ) : (
            <p className="text-sm text-muted-foreground">
              Select <span className="font-medium text-foreground">New tenant template</span> or edit an existing
              tenant template.
            </p>
          )}
        </div>
      </div>
    </PermissionGate>
  );
}

function TemplateTable({
  rows,
  onEdit,
  onDelete,
  onUse,
  usePending,
  deletePending,
}: {
  rows: { id: string; name: string; description: string; field_count: number; step_count: number; source?: string }[];
  onEdit?: (id: string) => void;
  onDelete?: (id: string) => void;
  onUse: (id: string) => void;
  usePending?: boolean;
  deletePending?: boolean;
}) {
  return (
    <ul className="divide-y divide-border rounded-lg border border-border">
      {rows.map((t) => (
        <li key={t.id} className="flex flex-wrap items-start justify-between gap-2 px-3 py-2.5">
          <div className="min-w-0">
            <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
              <FileStack className="h-3.5 w-3.5 text-primary" />
              {t.name}
              <Badge variant="outline" className="text-[10px]">
                {t.source === "tenant" ? "Tenant" : "System"}
              </Badge>
            </p>
            <p className="text-xs text-muted-foreground">{t.description}</p>
            <p className="text-[11px] text-muted-foreground">
              {t.field_count} fields · {t.step_count} steps
            </p>
          </div>
          <div className="flex flex-wrap gap-1">
            <Button type="button" size="sm" variant="outline" className="h-7 text-xs" disabled={usePending} onClick={() => onUse(t.id)}>
              Use
            </Button>
            {onEdit ? (
              <Button type="button" size="sm" variant="ghost" className="h-7 text-xs" onClick={() => onEdit(t.id)}>
                Edit
              </Button>
            ) : null}
            {onDelete ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="h-7 text-xs text-destructive"
                disabled={deletePending}
                onClick={() => onDelete(t.id)}
              >
                <Trash2 className="h-3 w-3" />
              </Button>
            ) : null}
          </div>
        </li>
      ))}
    </ul>
  );
}
