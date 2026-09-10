"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Pencil, Plus, X } from "lucide-react";
import { useMemo, useState } from "react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardBoardSkeleton } from "@/components/dashboard/dashboard-board-skeleton";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { DocExtractColumnsDefinitionEditor } from "@/components/doc-extract/doc-extract-columns-definition-editor";
import { PermissionGate } from "@/components/layout/permission-gate";
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
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import {
  createDocExtractTemplate,
  deleteDocExtractTemplate,
  fetchDocExtractTemplates,
  updateDocExtractTemplate,
} from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { emptyNormalizedData } from "@/lib/ui/dashboard-widget-data";
import { applyPageEnhancements, docExtractTemplatesEnhancements } from "@/lib/ui/page-enhancement-bags";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { DOC_EXTRACT_TEMPLATES_PAGE_CHROME } from "@/lib/ui/page-chrome-config";
import { formatDocExtractFieldTypeShort, slugifyDocExtractKey } from "@/modules/doc-extract/field-types";
import type {
  DocExtractField,
  DocExtractTemplate,
  DocExtractTemplateStatus,
} from "@/modules/doc-extract/types";
import { useNotificationStore } from "@/stores/notification-store";

const LAYOUT_KEY = "toweros.doc-extract.templates.layout";

function emptyField(): DocExtractField {
  return {
    key: "",
    label: "",
    type: "text",
    description: "",
    hint: "",
    keyManual: false,
    columns: [],
  };
}

function cloneFields(fields: DocExtractField[]): DocExtractField[] {
  return fields.map((field) => ({
    ...field,
    columns: field.columns?.map((column) => ({ ...column })) ?? [],
  }));
}

function StatusBadge({ status }: { status: DocExtractTemplateStatus }) {
  const published = status === "published";
  return (
    <span
      className={
        published
          ? "rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700"
          : "rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
      }
    >
      {published ? "Published" : "Draft"}
    </span>
  );
}

function TypeBadge({ type }: { type: DocExtractField["type"] }) {
  return (
    <span className="inline-flex items-center rounded-md border border-border bg-muted/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
      {formatDocExtractFieldTypeShort(type)}
    </span>
  );
}

function normalizeFieldsForSave(fields: DocExtractField[]): DocExtractField[] {
  return fields
    .filter((field) => field.label.trim() !== "")
    .map(({ keyManual: _keyManual, ...field }) => ({
      ...field,
      key: field.key.trim() || slugifyDocExtractKey(field.label),
      description: field.description?.trim() || null,
      hint: field.hint?.trim() || null,
      columns:
        field.type === "table"
          ? (field.columns ?? [])
              .filter((column) => column.label.trim() !== "")
              .map((column) => ({
                ...column,
                key: column.key.trim() || slugifyDocExtractKey(column.label),
                description: column.description?.trim() || null,
              }))
          : undefined,
    }));
}

type EditorState = {
  open: boolean;
  mode: "create" | "edit";
  templateId: string | null;
  name: string;
  description: string;
  status: DocExtractTemplateStatus;
  fields: DocExtractField[];
};

function initialEditor(mode: "create" | "edit" = "create"): EditorState {
  return {
    open: false,
    mode,
    templateId: null,
    name: "",
    description: "",
    status: "draft",
    fields: [emptyField()],
  };
}

export function DocExtractTemplatesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [editor, setEditor] = useState<EditorState>(initialEditor());
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault } = useDashboardLayoutPrefs(LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode();

  const templatesQuery = useQuery({
    queryKey: ["doc-extract", "templates"],
    queryFn: () => fetchDocExtractTemplates(),
  });

  const invalidateTemplates = async () => {
    await queryClient.invalidateQueries({ queryKey: ["doc-extract", "templates"] });
  };

  const templates = templatesQuery.data ?? [];
  const normalizedData = useMemo(
    () => applyPageEnhancements(emptyNormalizedData(), docExtractTemplatesEnhancements()),
    [],
  );

  const openCreate = () => {
    setEditor({
      ...initialEditor("create"),
      open: true,
      fields: [emptyField()],
    });
  };

  const openEdit = (template: DocExtractTemplate) => {
    const fields = cloneFields(template.fields);
    setEditor({
      open: true,
      mode: "edit",
      templateId: template.id,
      name: template.name,
      description: template.description ?? "",
      status: template.status === "published" ? "published" : "draft",
      fields: fields.length > 0 ? fields : [emptyField()],
    });
  };

  const closeEditor = () => setEditor(initialEditor());

  const saveMutation = useMutation({
    mutationFn: async () => {
      const fields = normalizeFieldsForSave(editor.fields);
      if (!editor.name.trim()) {
        throw new Error("Template name is required.");
      }
      if (fields.length === 0) {
        throw new Error("Add at least one column.");
      }
      const payload = {
        name: editor.name.trim(),
        description: editor.description.trim() || null,
        status: editor.status,
        fields,
      };
      if (editor.mode === "edit" && editor.templateId) {
        return updateDocExtractTemplate(editor.templateId, payload);
      }
      return createDocExtractTemplate(payload);
    },
    onSuccess: async (template) => {
      notify({
        level: "success",
        title: editor.mode === "edit" ? "Template updated" : "Template created",
        message: `"${template.name}" · ${template.fields.length} columns · ${template.status}`,
      });
      closeEditor();
      await invalidateTemplates();
    },
    onError: (error) => {
      notify({
        level: "error",
        title: editor.mode === "edit" ? "Could not update template" : "Could not create template",
        message: getErrorMessage(error),
      });
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: DocExtractTemplateStatus }) =>
      updateDocExtractTemplate(id, { status }),
    onSuccess: async (template) => {
      notify({
        level: "success",
        title: template.status === "published" ? "Template published" : "Template set to draft",
        message:
          template.status === "published"
            ? `"${template.name}" is available when extracting documents.`
            : `"${template.name}" is hidden from Extract until published.`,
      });
      await invalidateTemplates();
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not update status", message: getErrorMessage(error) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteDocExtractTemplate,
    onSuccess: async () => {
      notify({ level: "success", title: "Template deleted" });
      await invalidateTemplates();
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not delete template", message: getErrorMessage(error) });
    },
  });

  const layoutMeta = useMemo(() => [{ id: "template_grid", label: "Template catalog" }], []);

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    return [
      {
        id: "template_grid",
        label: "Template catalog",
        defaultSpan: "full",
        hideable: false,
        removable: false,
        render: () => {
          if (templatesQuery.isLoading) {
            return (
              <DashboardBoardSkeleton
                layout={layout}
                defaultEnabledIds={["template_grid"]}
              />
            );
          }
          if (templatesQuery.isError) {
            return <p className="text-sm text-destructive">{getErrorMessage(templatesQuery.error)}</p>;
          }
          if (templates.length === 0) {
            return (
              <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center">
                <p className="text-sm font-medium text-foreground">No templates yet</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Create one with custom columns, or save columns from an auto-detect batch.
                </p>
                <Button className="mt-4" size="sm" onClick={openCreate}>
                  <Plus className="size-4" />
                  Create template
                </Button>
              </div>
            );
          }
          return (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {templates.map((template) => {
                const status: DocExtractTemplateStatus =
                  template.status === "published" ? "published" : "draft";
                return (
                  <div
                    key={template.id}
                    className="flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="text-base font-medium text-foreground">{template.name}</h2>
                          <StatusBadge status={status} />
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                          {template.description?.trim() || "No description"}
                        </p>
                      </div>
                      <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] text-muted-foreground">
                        {template.fields.length} columns
                      </span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {template.fields.slice(0, 4).map((field) => (
                        <TypeBadge key={field.key} type={field.type} />
                      ))}
                      {template.fields.length > 4 ? (
                        <span className="text-[11px] text-muted-foreground">
                          +{template.fields.length - 4}
                        </span>
                      ) : null}
                    </div>
                    <div className="mt-auto flex flex-wrap gap-3 border-t border-border pt-3">
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 text-xs font-medium text-foreground hover:underline"
                        onClick={() => openEdit(template)}
                      >
                        <Pencil className="size-3.5" />
                        Edit
                      </button>
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:underline dark:text-sky-400"
                        disabled={statusMutation.isPending}
                        onClick={() =>
                          statusMutation.mutate({
                            id: template.id,
                            status: status === "published" ? "draft" : "published",
                          })
                        }
                      >
                        {status === "published" ? "Unpublish" : "Publish"}
                      </button>
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-destructive"
                        disabled={deleteMutation.isPending}
                        onClick={() => {
                          if (window.confirm(`Delete “${template.name}”?`)) {
                            deleteMutation.mutate(template.id);
                          }
                        }}
                      >
                        <X className="size-3.5" />
                        Remove
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          );
        },
      },
    ];
  }, [
    deleteMutation,
    layout,
    statusMutation,
    templates,
    templatesQuery.error,
    templatesQuery.isError,
    templatesQuery.isLoading,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "doc-extract",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [boardWidgets, layout.enabledWidgetIds, layout.widgetOptions, normalizedData, titleOverrides],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("doc-extract", boardWidgets, normalizedData),
    [boardWidgets, normalizedData],
  );

  const addableCatalog = useMemo(() => {
    const enabled = new Set(
      layout.enabledWidgetIds.length > 0
        ? layout.enabledWidgetIds
        : layoutMeta.map((widget) => widget.id),
    );
    return bindableCatalog.filter((entry) => !enabled.has(entry.id));
  }, [bindableCatalog, layout.enabledWidgetIds, layoutMeta]);

  const boardHandlers = useDashboardBoardLayoutHandlers(
    layout,
    setLayout,
    layoutMeta.map((widget) => widget.id),
  );

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractTemplatesManage]}>
      <div className="w-full space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <Button size="sm" variant="ghost" render={<Link href="/doc-extract" />}>
            <ArrowLeft className="size-4" />
            Batches
          </Button>
        </div>

        <ConfigurableModulePageHeader
          defaults={DOC_EXTRACT_TEMPLATES_PAGE_CHROME}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
          renderActions={({ isVisible }) => (
            <>
              {isVisible("customize") ? (
                <DashboardLayoutToolbar
                  widgets={layoutMeta}
                  catalogModule="doc-extract"
                  layout={layout}
                  editing={editing}
                  onEditingChange={setEditing}
                  onChange={setLayout}
                  bindableCatalog={bindableCatalog}
                  hasTenantDefault={Boolean(tenantDefault)}
                  onPublishTenantDefault={publishTenantDefault}
                  onResetToTenantDefault={resetToTenantDefault}
                  data={normalizedData}
                />
              ) : null}
              {isVisible("new") ? (
                <Button size="sm" onClick={openCreate}>
                  <Plus className="size-4" />
                  Create template
                </Button>
              ) : null}
            </>
          )}
        />

        <DashboardWidgetBoard
          widgets={catalogBoardWidgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={
            layout.enabledWidgetIds.length > 0
              ? layout.enabledWidgetIds
              : layoutMeta.map((widget) => widget.id)
          }
          layoutPrefs={layout}
          editing={editing}
          addableCatalog={addableCatalog}
          data={normalizedData}
          {...boardHandlers}
          onAddWidget={(entry, insertAt) => {
            boardHandlers.onAddWidget(entry, insertAt);
            setEditing(true);
          }}
        />

        <Dialog
          open={editor.open}
          onOpenChange={(open) => {
            if (!open) closeEditor();
          }}
        >
          <DialogContent
            className="flex max-h-[90vh] w-[min(98vw,90rem)] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none"
            showCloseButton
          >
            <DialogHeader className="border-b border-border px-6 py-4">
              <DialogTitle>
                {editor.mode === "edit" ? "Edit template" : "Create New Template"}
              </DialogTitle>
              <DialogDescription>
                Create a reusable template with custom column definitions for extracting data from
                documents. Drag columns to reorder.
              </DialogDescription>
            </DialogHeader>

            <DialogBody className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
              <div className="grid gap-4 lg:grid-cols-3">
                <div>
                  <Label htmlFor="create-template-name">
                    Template Name <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="create-template-name"
                    className="mt-1.5"
                    value={editor.name}
                    maxLength={100}
                    placeholder="e.g., Invoice, Resume, Contract"
                    onChange={(event) =>
                      setEditor((current) => ({ ...current, name: event.target.value }))
                    }
                  />
                  <p className="mt-1 text-right text-[11px] text-muted-foreground">
                    {editor.name.length}/100
                  </p>
                </div>
                <div>
                  <Label htmlFor="create-template-description">Description</Label>
                  <Textarea
                    id="create-template-description"
                    className="mt-1.5 min-h-10"
                    value={editor.description}
                    maxLength={200}
                    placeholder="Describe what this template is used for..."
                    onChange={(event) =>
                      setEditor((current) => ({ ...current, description: event.target.value }))
                    }
                  />
                  <p className="mt-1 text-right text-[11px] text-muted-foreground">
                    {editor.description.length}/200
                  </p>
                </div>
                <div>
                  <Label htmlFor="create-template-status">Status</Label>
                  <Select
                    id="create-template-status"
                    className="mt-1.5"
                    value={editor.status}
                    onChange={(event) =>
                      setEditor((current) => ({
                        ...current,
                        status: event.target.value === "published" ? "published" : "draft",
                      }))
                    }
                  >
                    <option value="draft">Draft — hidden from Extract</option>
                    <option value="published">Published — available for Extract</option>
                  </Select>
                </div>
              </div>

              <DocExtractColumnsDefinitionEditor
                fields={editor.fields}
                onChange={(fields) => setEditor((current) => ({ ...current, fields }))}
              />
            </DialogBody>

            <DialogFooter className="border-t border-border px-6 py-4 sm:justify-end">
              <Button type="button" variant="outline" onClick={closeEditor}>
                Cancel
              </Button>
              <Button
                type="button"
                disabled={saveMutation.isPending}
                onClick={() => saveMutation.mutate()}
              >
                {saveMutation.isPending
                  ? "Saving…"
                  : editor.mode === "edit"
                    ? "Save template"
                    : "Create Template"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
