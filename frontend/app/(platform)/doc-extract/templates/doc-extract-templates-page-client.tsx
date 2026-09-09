"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  Check,
  Hash,
  Pencil,
  Plus,
  Table2,
  Type,
  X,
} from "lucide-react";

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
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import {
  createDocExtractTemplate,
  deleteDocExtractTemplate,
  fetchDocExtractTemplates,
  updateDocExtractTemplate,
} from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import {
  DOC_EXTRACT_FIELD_TYPES,
  DOC_EXTRACT_TABLE_COLUMN_TYPES,
  emptyTableColumn,
  formatDocExtractFieldTypeShort,
  slugifyDocExtractKey,
} from "@/modules/doc-extract/field-types";
import type {
  DocExtractField,
  DocExtractFieldType,
  DocExtractTableColumn,
  DocExtractTemplate,
  DocExtractTemplateStatus,
} from "@/modules/doc-extract/types";
import { useNotificationStore } from "@/stores/notification-store";

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
      className={cn(
        "rounded-md border px-2 py-0.5 text-[11px] font-medium capitalize",
        published
          ? "border-emerald-200 bg-emerald-50 text-emerald-700"
          : "border-border bg-muted/40 text-muted-foreground",
      )}
    >
      {published ? "Published" : "Draft"}
    </span>
  );
}

function TypeBadge({ type }: { type: DocExtractFieldType }) {
  const Icon = type === "table" ? Table2 : type === "number" || type === "currency" ? Hash : Type;
  return (
    <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
      <Icon className="size-3" />
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
  editingIndex: number | null;
  draft: DocExtractField;
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
    editingIndex: 0,
    draft: emptyField(),
  };
}

export function DocExtractTemplatesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [editor, setEditor] = useState<EditorState>(initialEditor());

  const templatesQuery = useQuery({
    queryKey: ["doc-extract", "templates"],
    queryFn: () => fetchDocExtractTemplates(),
  });

  const invalidateTemplates = async () => {
    await queryClient.invalidateQueries({ queryKey: ["doc-extract", "templates"] });
  };

  const templates = templatesQuery.data ?? [];
  const columnCount = useMemo(
    () => editor.fields.filter((field) => field.label.trim() !== "" || editor.editingIndex !== null).length,
    [editor.fields, editor.editingIndex],
  );

  const openCreate = () => {
    setEditor({
      ...initialEditor("create"),
      open: true,
      editingIndex: 0,
      draft: emptyField(),
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
      editingIndex: null,
      draft: emptyField(),
    });
  };

  const closeEditor = () => setEditor(initialEditor());

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editor.editingIndex !== null) {
        throw new Error("Save or cancel the open column card first.");
      }
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

  const patchDraft = (patch: Partial<DocExtractField>) => {
    setEditor((current) => {
      const draft = { ...current.draft, ...patch };
      if (patch.label !== undefined && !draft.keyManual) {
        draft.key = slugifyDocExtractKey(patch.label);
      }
      if (patch.type === "table" && (!draft.columns || draft.columns.length === 0)) {
        draft.columns = [emptyTableColumn()];
      }
      return { ...current, draft };
    });
  };

  const patchTableColumn = (columnIndex: number, patch: Partial<DocExtractTableColumn>) => {
    setEditor((current) => {
      const columns = [...(current.draft.columns ?? [])];
      const existing = columns[columnIndex] ?? emptyTableColumn();
      const nextColumn = { ...existing, ...patch };
      if (patch.label !== undefined) {
        nextColumn.key = slugifyDocExtractKey(patch.label);
      }
      columns[columnIndex] = nextColumn;
      return { ...current, draft: { ...current.draft, columns } };
    });
  };

  const commitColumn = () => {
    if (editor.editingIndex === null) return;
    const label = editor.draft.label.trim();
    if (!label) {
      notify({ level: "error", title: "Column name required" });
      return;
    }
    if (
      editor.draft.type === "table" &&
      (editor.draft.columns ?? []).filter((column) => column.label.trim()).length === 0
    ) {
      notify({ level: "error", title: "Add at least one table column" });
      return;
    }
    const nextField: DocExtractField = {
      ...editor.draft,
      label,
      key: editor.draft.keyManual ? editor.draft.key : slugifyDocExtractKey(label),
      description: editor.draft.description ?? "",
      columns:
        editor.draft.type === "table"
          ? (editor.draft.columns ?? [])
              .filter((column) => column.label.trim() !== "")
              .map((column) => ({
                ...column,
                key: column.key || slugifyDocExtractKey(column.label),
              }))
          : [],
    };
    setEditor((current) => ({
      ...current,
      fields: current.fields.map((field, index) => (index === current.editingIndex ? nextField : field)),
      editingIndex: null,
      draft: emptyField(),
    }));
  };

  const cancelColumn = () => {
    setEditor((current) => {
      if (current.editingIndex === null) return current;
      const existing = current.fields[current.editingIndex];
      const isBlankNew = !existing?.label.trim() && current.fields.length > 1;
      return {
        ...current,
        fields: isBlankNew
          ? current.fields.filter((_, index) => index !== current.editingIndex)
          : current.fields,
        editingIndex: null,
        draft: emptyField(),
      };
    });
  };

  const addColumn = () => {
    if (editor.editingIndex !== null) {
      notify({ level: "error", title: "Save or cancel the open column first" });
      return;
    }
    setEditor((current) => {
      const fields = [...current.fields, emptyField()];
      return {
        ...current,
        fields,
        editingIndex: fields.length - 1,
        draft: emptyField(),
      };
    });
  };

  const startEditColumn = (index: number) => {
    setEditor((current) => ({
      ...current,
      editingIndex: index,
      draft: {
        ...current.fields[index],
        columns: current.fields[index]?.columns?.map((column) => ({ ...column })) ?? [],
      },
    }));
  };

  const removeColumn = (index: number) => {
    setEditor((current) => {
      if (current.fields.length <= 1) return current;
      return {
        ...current,
        fields: current.fields.filter((_, i) => i !== index),
        editingIndex: null,
        draft: emptyField(),
      };
    });
  };

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractTemplatesManage]}>
      <div className="space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <Button size="sm" variant="ghost" render={<Link href="/doc-extract" />}>
            <ArrowLeft className="size-4" />
            Batches
          </Button>
        </div>

        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-foreground">Templates</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Create reusable column definitions. Only <span className="font-medium text-foreground">published</span>{" "}
              templates appear when extracting documents; drafts stay here until you publish.
            </p>
          </div>
          <Button size="sm" onClick={openCreate}>
            <Plus className="size-4" />
            Create template
          </Button>
        </div>

        {templatesQuery.isLoading ? (
          <DashboardContentSkeleton />
        ) : templatesQuery.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(templatesQuery.error)}</p>
        ) : templates.length === 0 ? (
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
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {templates.map((template) => {
              const status: DocExtractTemplateStatus =
                template.status === "published" ? "published" : "draft";
              return (
              <div key={template.id} className="flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm">
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
                    <span className="text-[11px] text-muted-foreground">+{template.fields.length - 4}</span>
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
        )}

        <Dialog
          open={editor.open}
          onOpenChange={(open) => {
            if (!open) closeEditor();
          }}
        >
          <DialogContent
            className="flex max-h-[90vh] w-[min(98vw,72rem)] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none"
            showCloseButton
          >
            <DialogHeader className="border-b border-border px-6 py-4">
              <DialogTitle>
                {editor.mode === "edit" ? "Edit template" : "Create New Template"}
              </DialogTitle>
              <DialogDescription>
                Create a reusable template with custom column definitions for extracting data from documents.
              </DialogDescription>
            </DialogHeader>

            <DialogBody className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
              <div className="space-y-4">
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
                    onChange={(event) => setEditor((current) => ({ ...current, name: event.target.value }))}
                  />
                  <p className="mt-1 text-right text-[11px] text-muted-foreground">{editor.name.length}/100</p>
                </div>
                <div>
                  <Label htmlFor="create-template-description">Description</Label>
                  <Textarea
                    id="create-template-description"
                    className="mt-1.5 min-h-20"
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

              <div className="space-y-3">
                <div>
                  <p className="text-sm font-medium text-foreground">
                    Columns Definition <span className="text-destructive">*</span>
                  </p>
                  <div className="mt-1 flex flex-wrap items-center gap-2">
                    <p className="text-sm text-foreground">Define Table Columns</p>
                    <span className="rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                      {Math.max(columnCount, editor.fields.length)} columns
                    </span>
                  </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {editor.fields.map((field, index) => {
                    const isEditing = editor.editingIndex === index;
                    const active = isEditing ? editor.draft : field;

                    return (
                      <div
                        key={`col-${index}`}
                        className={cn(
                          "rounded-xl border bg-card p-4 shadow-sm",
                          isEditing ? "border-foreground/25 ring-1 ring-foreground/10" : "border-border",
                        )}
                      >
                        {isEditing ? (
                          <div className="space-y-3">
                            <div>
                              <Label>
                                Column Name <span className="text-destructive">*</span>
                              </Label>
                              <Input
                                className="mt-1"
                                value={editor.draft.label}
                                maxLength={100}
                                placeholder="e.g. Customer Name"
                                onChange={(event) => patchDraft({ label: event.target.value })}
                              />
                              <p className="mt-1 text-right text-[11px] text-muted-foreground">
                                {editor.draft.label.length}/100
                              </p>
                            </div>
                            <div>
                              <Label>Description</Label>
                              <Textarea
                                className="mt-1 min-h-16"
                                value={editor.draft.description ?? ""}
                                maxLength={200}
                                placeholder="e.g. Full name of the customer"
                                onChange={(event) => patchDraft({ description: event.target.value })}
                              />
                              <p className="mt-1 text-right text-[11px] text-muted-foreground">
                                {(editor.draft.description ?? "").length}/200
                              </p>
                            </div>
                            <div>
                              <Label>Type</Label>
                              <Select
                                className="mt-1"
                                value={editor.draft.type}
                                onChange={(event) =>
                                  patchDraft({ type: event.target.value as DocExtractFieldType })
                                }
                              >
                                {DOC_EXTRACT_FIELD_TYPES.map((entry) => (
                                  <option key={entry.value} value={entry.value}>
                                    {entry.short} — {entry.label}
                                  </option>
                                ))}
                              </Select>
                            </div>

                            {editor.draft.type === "table" ? (
                              <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
                                <div className="flex items-center justify-between">
                                  <p className="text-xs font-medium text-foreground">Table columns</p>
                                  <span className="text-[11px] text-muted-foreground">
                                    {(editor.draft.columns ?? []).length}/20
                                  </span>
                                </div>
                                {(editor.draft.columns ?? []).map((column, columnIndex) => (
                                  <div key={columnIndex} className="grid gap-2 sm:grid-cols-[1fr_7rem_auto]">
                                    <Input
                                      value={column.label}
                                      placeholder="Column name"
                                      onChange={(event) =>
                                        patchTableColumn(columnIndex, { label: event.target.value })
                                      }
                                    />
                                    <Select
                                      value={column.type}
                                      onChange={(event) =>
                                        patchTableColumn(columnIndex, {
                                          type: event.target.value as DocExtractTableColumn["type"],
                                        })
                                      }
                                    >
                                      {DOC_EXTRACT_TABLE_COLUMN_TYPES.map((entry) => (
                                        <option key={entry.value} value={entry.value}>
                                          {entry.label}
                                        </option>
                                      ))}
                                    </Select>
                                    <Button
                                      type="button"
                                      size="sm"
                                      variant="ghost"
                                      disabled={(editor.draft.columns ?? []).length <= 1}
                                      onClick={() =>
                                        setEditor((current) => ({
                                          ...current,
                                          draft: {
                                            ...current.draft,
                                            columns: (current.draft.columns ?? []).filter(
                                              (_, i) => i !== columnIndex,
                                            ),
                                          },
                                        }))
                                      }
                                    >
                                      <X className="size-4" />
                                    </Button>
                                  </div>
                                ))}
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="outline"
                                  disabled={(editor.draft.columns ?? []).length >= 20}
                                  onClick={() =>
                                    setEditor((current) => ({
                                      ...current,
                                      draft: {
                                        ...current.draft,
                                        columns: [...(current.draft.columns ?? []), emptyTableColumn()],
                                      },
                                    }))
                                  }
                                >
                                  <Plus className="size-4" />
                                  Add column
                                </Button>
                              </div>
                            ) : null}

                            <div className="flex gap-2 pt-1">
                              <Button type="button" size="sm" onClick={commitColumn}>
                                <Check className="size-4" />
                                Save
                              </Button>
                              <Button type="button" size="sm" variant="outline" onClick={cancelColumn}>
                                Cancel
                              </Button>
                            </div>
                          </div>
                        ) : (
                          <div className="flex h-full flex-col">
                            <h3 className="text-base font-medium text-foreground">
                              {active.label || "Untitled column"}
                            </h3>
                            <p className="mt-2 min-h-10 text-sm text-muted-foreground">
                              {active.description?.trim() || "No description"}
                            </p>
                            <div className="mt-3">
                              <TypeBadge type={active.type} />
                            </div>
                            <div className="mt-auto flex gap-3 border-t border-border pt-3">
                              <button
                                type="button"
                                className="inline-flex items-center gap-1 text-xs font-medium text-foreground hover:underline"
                                disabled={editor.editingIndex !== null}
                                onClick={() => startEditColumn(index)}
                              >
                                <Pencil className="size-3.5" />
                                Edit
                              </button>
                              <button
                                type="button"
                                className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-destructive"
                                disabled={editor.fields.length <= 1 || editor.editingIndex !== null}
                                onClick={() => removeColumn(index)}
                              >
                                <X className="size-3.5" />
                                Remove
                              </button>
                            </div>
                          </div>
                        )}
                      </div>
                    );
                  })}

                  <button
                    type="button"
                    onClick={addColumn}
                    disabled={editor.editingIndex !== null}
                    className="flex min-h-[14rem] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/10 p-6 text-center transition-colors hover:border-foreground/30 hover:bg-muted/20 disabled:opacity-50"
                  >
                    <span className="flex size-10 items-center justify-center rounded-full border border-border bg-card text-foreground">
                      <Plus className="size-5" />
                    </span>
                    <span className="text-sm font-medium text-foreground">Add Column</span>
                    <span className="max-w-[14rem] text-xs text-muted-foreground">
                      Define a new data column to extract
                    </span>
                  </button>
                </div>
              </div>
            </DialogBody>

            <DialogFooter className="border-t border-border px-6 py-4 sm:justify-end">
              <Button type="button" variant="outline" onClick={closeEditor}>
                Cancel
              </Button>
              <Button
                type="button"
                disabled={saveMutation.isPending || editor.editingIndex !== null}
                onClick={() => saveMutation.mutate()}
                title={editor.editingIndex !== null ? "Save or cancel the open column first" : undefined}
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
