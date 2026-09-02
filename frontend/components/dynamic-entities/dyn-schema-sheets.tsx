"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { Check, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  createDynField,
  createDynFieldGroup,
  deleteDynFieldGroup,
  fetchDynEntities,
  updateDynField,
  updateDynFieldGroup,
  type DynConditionalRule,
  type DynEntityDetail,
  type DynEntitySummary,
  type DynField,
  type DynRelationFilter,
} from "@/lib/api/modules/dynamic-entities-api";
import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";
import { DynWorkflowButtonsEditor } from "@/components/dynamic-entities/dyn-workflow-buttons-editor";
import {
  dynWorkflowActionsForEntity,
  parseWorkflowButtons,
  workflowButtonsPayload,
  type DynWorkflowActionDef,
} from "@/lib/dynamic-entities/dyn-workflow-actions";
import {
  DYN_SELECT_BADGE_OPTIONS,
  emptyDynSelectChoice,
  parseDynSelectOptions,
  serializeDynSelectOptions,
  type DynSelectChoice,
  type DynSelectOptionsConfig,
} from "@/lib/dynamic-entities/select-choices";
import {
  parseDynNumberOptions,
  serializeDynNumberOptions,
  type DynNumberOptionsConfig,
} from "@/lib/dynamic-entities/number-options";
import {
  parseDynTextOptions,
  serializeDynTextOptions,
  type DynTextOptionsConfig,
} from "@/lib/dynamic-entities/text-options";

const FIELD_TYPE_OPTIONS = [
  { value: "text", label: "Text" },
  { value: "textarea", label: "Long text" },
  { value: "number", label: "Number" },
  { value: "decimal", label: "Decimal" },
  { value: "date", label: "Date" },
  { value: "datetime", label: "Date & time" },
  { value: "select", label: "Dropdown" },
  { value: "multiselect", label: "Multi-select" },
  { value: "boolean", label: "Yes / No" },
  { value: "email", label: "Email" },
  { value: "phone", label: "Phone" },
  { value: "relationship", label: "Link to another Record" },
  { value: "file", label: "File" },
] as const;

const WIDTH_OPTIONS = [
  { value: 3, label: "3 of 12 — Quarter" },
  { value: 4, label: "4 of 12 — Third" },
  { value: 6, label: "6 of 12 — Half" },
  { value: 8, label: "8 of 12 — Two thirds" },
  { value: 12, label: "12 of 12 — Full width" },
] as const;

/** Centered modal shell — matches Arrange Form (no CSS transform offset). */
const SCHEMA_DIALOG_SHELL =
  "flex max-h-[min(92vh,880px)] flex-col gap-0 overflow-hidden p-0 sm:max-w-none !inset-0 !m-auto !h-fit !translate-x-0 !translate-y-0";

export type DynSchemaMode =
  | { kind: "closed" }
  | { kind: "edit-field"; field: DynField }
  | { kind: "add-field"; groupId?: string | null }
  | { kind: "edit-group"; group: DynEntityDetail["field_groups"][number] }
  | { kind: "new-group" };

type Props = {
  entitySlug: string;
  entityName: string;
  groups: DynEntityDetail["field_groups"];
  fields?: DynField[];
  mode: DynSchemaMode;
  onClose: () => void;
  onSaved: () => Promise<void> | void;
};

export function DynSchemaSheets({
  entitySlug,
  entityName,
  groups,
  fields = [],
  mode,
  onClose,
  onSaved,
}: Props) {
  const isWorkflowEdit = mode.kind === "edit-field" && mode.field.name === "workflows";
  const isFieldEditor =
    (mode.kind === "edit-field" && mode.field.name !== "workflows") || mode.kind === "add-field";
  const isGroupEditor = mode.kind === "edit-group" || mode.kind === "new-group";
  const workflowField = isWorkflowEdit ? mode.field : null;

  return (
    <>
      <Dialog open={workflowField !== null} onOpenChange={(next) => (!next ? onClose() : undefined)}>
        <DialogContent
          showCloseButton
          className={`${SCHEMA_DIALOG_SHELL} w-[min(calc(100vw-2rem),1100px)] max-h-[min(92vh,920px)]`}
        >
          {workflowField ? (
            <WorkflowFieldEditorForm
              entitySlug={entitySlug}
              entityName={entityName}
              field={workflowField}
              siblingFields={fields}
              onClose={onClose}
              onSaved={onSaved}
            />
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={isFieldEditor} onOpenChange={(next) => (!next ? onClose() : undefined)}>
        <DialogContent
          showCloseButton
          className={`${SCHEMA_DIALOG_SHELL} w-[min(calc(100vw-2rem),720px)]`}
        >
          {mode.kind === "edit-field" && mode.field.name !== "workflows" ? (
            <FieldEditorForm
              mode="edit"
              entitySlug={entitySlug}
              entityName={entityName}
              field={mode.field}
              groups={groups}
              siblingFields={fields}
              onClose={onClose}
              onSaved={onSaved}
            />
          ) : null}
          {mode.kind === "add-field" ? (
            <FieldEditorForm
              mode="create"
              entitySlug={entitySlug}
              entityName={entityName}
              defaultGroupId={mode.groupId ?? null}
              groups={groups}
              siblingFields={fields}
              onClose={onClose}
              onSaved={onSaved}
            />
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={isGroupEditor} onOpenChange={(next) => (!next ? onClose() : undefined)}>
        <DialogContent
          showCloseButton
          className={`${SCHEMA_DIALOG_SHELL} w-[min(calc(100vw-2rem),560px)]`}
        >
          {mode.kind === "edit-group" ? (
            <EditGroupForm group={mode.group} onClose={onClose} onSaved={onSaved} />
          ) : null}
          {mode.kind === "new-group" ? (
            <NewGroupForm entitySlug={entitySlug} onClose={onClose} onSaved={onSaved} />
          ) : null}
        </DialogContent>
      </Dialog>
    </>
  );
}

function typeLabel(type: string): string {
  return FIELD_TYPE_OPTIONS.find((t) => t.value === type)?.label ?? type;
}

function WorkflowFieldEditorForm({
  entitySlug,
  entityName,
  field,
  siblingFields,
  onClose,
  onSaved,
}: {
  entitySlug: string;
  entityName: string;
  field: DynField;
  siblingFields: DynField[];
  onClose: () => void;
  onSaved: () => Promise<void> | void;
}) {
  const [tab, setTab] = useState("automation");
  const [buttons, setButtons] = useState<DynWorkflowActionDef[]>(() => {
    const configured = parseWorkflowButtons(field.options);
    if (configured.length > 0) return configured;
    return dynWorkflowActionsForEntity(entitySlug);
  });
  const [roleOptions, setRoleOptions] = useState<Array<{ id: string; name: string }>>([]);
  const [entityOptions, setEntityOptions] = useState<Array<{ slug: string; name: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const configured = parseWorkflowButtons(field.options);
    setButtons(configured.length > 0 ? configured : dynWorkflowActionsForEntity(entitySlug));
    setError(null);
    setTab("automation");
  }, [field, entitySlug]);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminRoleCatalog()
      .then((catalog) => {
        if (cancelled) return;
        setRoleOptions(
          (catalog.roles ?? []).map((r) => ({ id: String(r.id), name: r.name })),
        );
      })
      .catch(() => {
        if (!cancelled) setRoleOptions([]);
      });
    void fetchDynEntities({ active_only: true })
      .then((list) => {
        if (cancelled) return;
        setEntityOptions(list.map((e) => ({ slug: e.slug, name: e.name })));
      })
      .catch(() => {
        if (!cancelled) setEntityOptions([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const fieldOptions = useMemo(
    () =>
      siblingFields
        .filter((f) => !f.is_system_field || f.name === "status")
        .map((f) => ({
          name: f.name,
          label: f.label,
          type: f.type,
          options: f.options,
          target_entity_slug: f.target_entity_slug ?? null,
        })),
    [siblingFields],
  );

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const cleaned = buttons
        .map((b) => {
          const when = b.when
            .map((c) => ({ ...c, field: c.field.trim(), value: c.value.trim() }))
            .filter((c) => c.field && c.value);
          const then_updates = b.then_updates
            .map((u) => ({
              ...u,
              target: (u.target || "this").trim() || "this",
              field: u.field.trim(),
              value: u.value.trim(),
              from: u.from?.trim() || undefined,
              mode: u.mode || "set",
            }))
            .filter((u) => {
              if (!u.field) return false;
              if (u.mode === "copy") return Boolean(u.from);
              if (u.mode === "set") return Boolean(u.value);
              return false;
            });
          const loads = (b.loads ?? [])
            .map((l) => ({
              source_field: l.source_field.trim(),
              alias: l.alias.trim().toLowerCase().replace(/[^a-z0-9_]+/g, "_"),
            }))
            .filter((l) => l.source_field && l.alias);
          const creates = (b.creates ?? [])
            .map((c) => ({
              ...c,
              entity_slug: c.entity_slug.trim(),
              one_per: c.one_per?.trim() || null,
              mappings: c.mappings
                .map((m) => ({
                  ...m,
                  field: m.field.trim(),
                  value: m.value.trim(),
                  from: m.from?.trim() || undefined,
                }))
                .filter((m) => m.field && (m.mode === "copy" ? m.from : m.value)),
            }))
            .filter((c) => c.entity_slug);
          const emails = (b.emails ?? [])
            .map((e) => ({
              subject: e.subject.trim() || "Workflow notification",
              to: e.to.trim(),
              body: e.body.trim(),
            }))
            .filter((e) => e.to);
          const from_status = when.find((c) => c.field === "status")?.value ?? when[0]?.value ?? "";
          const to_status =
            then_updates.find((u) => u.field === "status" && u.mode === "set")?.value ??
            then_updates.find((u) => u.field === "status")?.value ??
            "";
          return {
            ...b,
            label: b.label.trim(),
            when,
            then_updates,
            loads,
            creates,
            emails,
            from_status,
            to_status,
          };
        })
        .filter((b) => b.label && b.when.length > 0 && b.then_updates.length > 0);
      if (cleaned.length !== buttons.length) {
        setError("Each button needs text, at least one WHEN condition, and one THEN update.");
        setSaving(false);
        return;
      }
      await updateDynField(field.id, {
        label: field.label,
        type: field.type,
        is_required: field.is_required,
        is_key: Boolean(field.is_key),
        show_in_table: field.show_in_table,
        calculate_totals: Boolean(field.calculate_totals),
        is_filterable: field.is_filterable,
        column_span: field.column_span,
        field_order: field.field_order,
        form_group_id: field.form_group_id,
        view_group_id: field.view_group_id ?? field.form_group_id,
        placeholder: field.placeholder ?? null,
        options_json: workflowButtonsPayload(cleaned),
        target_entity_id: null,
        conditional_rules_json: null,
      });
      await onSaved();
      onClose();
    } catch {
      setError("Unable to save workflow buttons.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <DialogHeader className="shrink-0 px-6 py-4 pr-12">
        <DialogTitle>Edit: Workflows</DialogTitle>
        <DialogDescription>{entityName} · Workflow Button</DialogDescription>
      </DialogHeader>

      <Tabs value={tab} onValueChange={setTab} className="flex min-h-0 flex-1 flex-col gap-0">
        <div className="shrink-0 border-b border-border px-6 pt-1">
          <TabsList variant="line" className="w-full justify-start gap-0">
            <TabsTrigger value="basics" className="px-3">
              Basics
            </TabsTrigger>
            <TabsTrigger value="form-list" className="px-3">
              Form & List
            </TabsTrigger>
            <TabsTrigger value="automation" className="px-3">
              Automation{buttons.length > 0 ? ` (${buttons.length})` : ""}
            </TabsTrigger>
          </TabsList>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
          <TabsContent value="basics" className="mt-0 space-y-5">
            <Section title="What is this field?">
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Belongs to</span>
                <Input value={entityName} disabled />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Field type</span>
                <Input value="Workflow Button" disabled />
              </label>
            </Section>
            <Section title="Naming">
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Field label</span>
                <Input value={field.label} disabled />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">System name</span>
                <Input value={field.name} disabled />
              </label>
            </Section>
          </TabsContent>

          <TabsContent value="form-list" className="mt-0 space-y-5">
            <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center">
              <p className="text-sm text-muted-foreground">
                Workflow buttons are shown on the record toolbar. Form & List layout is not used for
                this system field.
              </p>
            </div>
          </TabsContent>

          <TabsContent value="automation" className="mt-0 space-y-5">
            <DynWorkflowButtonsEditor
              buttons={buttons}
              onChange={setButtons}
              fieldOptions={fieldOptions}
              roleOptions={roleOptions}
              entityOptions={entityOptions}
            />
          </TabsContent>
        </div>
      </Tabs>

      {error ? <p className="shrink-0 px-6 pb-2 text-sm text-destructive">{error}</p> : null}

      <DialogFooter className="shrink-0 px-6 py-3 sm:justify-end">
        <Button type="button" variant="outline" onClick={onClose} disabled={saving}>
          Cancel
        </Button>
        <Button type="button" onClick={() => void save()} disabled={saving}>
          <Check className="size-3.5" />
          {saving ? "Saving…" : "Save Field"}
        </Button>
      </DialogFooter>
    </>
  );
}

function parseRelationFilters(options: unknown): DynRelationFilter[] {
  if (!options || typeof options !== "object" || Array.isArray(options)) return [];
  const filters = (options as { filters?: unknown }).filters;
  if (!Array.isArray(filters)) return [];
  return filters
    .map((row): DynRelationFilter | null => {
      if (!row || typeof row !== "object") return null;
      const r = row as Record<string, unknown>;
      const field = String(r.field ?? "").trim();
      if (!field) return null;
      const op = (["eq", "neq", "contains"].includes(String(r.op)) ? String(r.op) : "eq") as DynRelationFilter["op"];
      return { field, op, value: String(r.value ?? "") };
    })
    .filter((r): r is DynRelationFilter => r !== null);
}

function normalizeRules(raw: DynField["conditional_rules"]): DynConditionalRule[] {
  if (!raw) return [];
  if (Array.isArray(raw)) return raw;
  if (typeof raw === "object" && Array.isArray(raw.rules)) return raw.rules;
  return [];
}

function FieldEditorForm({
  mode,
  entitySlug,
  entityName,
  field,
  defaultGroupId,
  groups,
  siblingFields,
  onClose,
  onSaved,
}: {
  mode: "edit" | "create";
  entitySlug: string;
  entityName: string;
  field?: DynField;
  defaultGroupId?: string | null;
  groups: DynEntityDetail["field_groups"];
  siblingFields: DynField[];
  onClose: () => void;
  onSaved: () => Promise<void> | void;
}) {
  const [tab, setTab] = useState("basics");
  const [label, setLabel] = useState(field?.label ?? "");
  const [type, setType] = useState(field?.type ?? "text");
  const [placeholder, setPlaceholder] = useState(field?.placeholder ?? "");
  const [required, setRequired] = useState(field?.is_required ?? false);
  const [isKey, setIsKey] = useState(Boolean(field?.is_key));
  const [showInTable, setShowInTable] = useState(field?.show_in_table ?? mode === "create");
  const [calculateTotals, setCalculateTotals] = useState(Boolean(field?.calculate_totals));
  const [filterable, setFilterable] = useState(field?.is_filterable ?? true);
  const [columnSpan, setColumnSpan] = useState(String(field?.column_span || 6));
  const [groupId, setGroupId] = useState(field?.form_group_id ?? defaultGroupId ?? "");
  const [fieldOrder, setFieldOrder] = useState(String(field?.field_order ?? 10));
  const [selectOptions, setSelectOptions] = useState<DynSelectOptionsConfig>(() =>
    parseDynSelectOptions(field?.options),
  );
  const [numberOptions, setNumberOptions] = useState<DynNumberOptionsConfig>(() =>
    parseDynNumberOptions(field?.options),
  );
  const [textOptions, setTextOptions] = useState<DynTextOptionsConfig>(() =>
    parseDynTextOptions(field?.options),
  );
  const [targetEntityId, setTargetEntityId] = useState(field?.target_entity_id ?? "");
  const [relationFilters, setRelationFilters] = useState<DynRelationFilter[]>(() =>
    parseRelationFilters(field?.options),
  );
  const [rules, setRules] = useState<DynConditionalRule[]>(() => normalizeRules(field?.conditional_rules));
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const isSystemField = Boolean(field?.is_system_field);
  const isListChrome =
    field?.name === "id" || field?.name === "actions" || field?.name === "print";
  const isDataSystemField = isSystemField && field?.name === "id";

  useEffect(() => {
    if (mode !== "edit" || !field) return;
    setLabel(field.label);
    setType(field.type);
    setPlaceholder(field.placeholder ?? "");
    setRequired(field.is_required);
    setIsKey(Boolean(field.is_key));
    setShowInTable(
      field.name === "id" || field.name === "actions" || field.name === "print"
        ? (() => {
            const opts = field.options;
            const managed =
              opts !== null &&
              typeof opts === "object" &&
              !Array.isArray(opts) &&
              (opts as { list_visibility_managed?: unknown }).list_visibility_managed === true;
            return managed ? field.show_in_table : true;
          })()
        : field.show_in_table,
    );
    setCalculateTotals(Boolean(field.calculate_totals));
    setFilterable(field.is_filterable);
    setColumnSpan(String(field.column_span || 6));
    setGroupId(field.form_group_id ?? "");
    setFieldOrder(String(field.field_order ?? 10));
    setSelectOptions(parseDynSelectOptions(field.options));
    setNumberOptions(parseDynNumberOptions(field.options));
    setTextOptions(parseDynTextOptions(field.options));
    setTargetEntityId(field.target_entity_id ?? "");
    setRelationFilters(parseRelationFilters(field.options));
    setRules(normalizeRules(field.conditional_rules));
    setError(null);
    setTab("basics");
  }, [field, mode]);

  useEffect(() => {
    if (mode === "create") {
      setGroupId(defaultGroupId ?? "");
    }
  }, [defaultGroupId, mode]);

  useEffect(() => {
    let cancelled = false;
    void fetchDynEntities({ active_only: true })
      .then((list) => {
        if (!cancelled) setEntities(list);
      })
      .catch(() => {
        if (!cancelled) setEntities([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const otherFields = useMemo(
    () => siblingFields.filter((f) => f.id !== field?.id),
    [siblingFields, field?.id],
  );

  function buildOptionsJson(): unknown {
    if (type === "select" || type === "multiselect") {
      return serializeDynSelectOptions(selectOptions);
    }
    if (type === "number" || type === "decimal") {
      return serializeDynNumberOptions(numberOptions);
    }
    if (type === "text" || type === "textarea" || type === "email") {
      return serializeDynTextOptions(textOptions);
    }
    if (type === "relationship") {
      const filters = relationFilters.filter((f) => f.field.trim());
      return filters.length > 0 ? { filters } : null;
    }
    return field?.options ?? null;
  }

  function updateChoice(index: number, patch: Partial<DynSelectChoice>) {
    setSelectOptions((prev) => {
      const choices = prev.choices.map((row, i) => (i === index ? { ...row, ...patch } : row));
      const defaultStillValid =
        prev.default && choices.some((c) => (c.value.trim() || c.label.trim()) === prev.default)
          ? prev.default
          : null;
      return { choices, default: defaultStillValid };
    });
  }

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const payload = {
        label: label.trim(),
        type: isSystemField && field ? field.type : type,
        is_required: isListChrome ? false : required,
        is_key: isListChrome ? false : isKey,
        show_in_table: showInTable,
        calculate_totals: isListChrome ? false : calculateTotals,
        is_filterable: isListChrome && field?.name !== "id" ? false : filterable,
        column_span: Math.min(12, Math.max(1, Number(columnSpan) || 6)),
        field_order: Math.max(0, Number(fieldOrder) || 10),
        form_group_id: isListChrome ? null : groupId || null,
        view_group_id: isListChrome ? null : groupId || null,
        placeholder: placeholder.trim() || null,
        options_json: (() => {
          if (isListChrome) {
            const existing =
              field?.options !== null &&
              typeof field?.options === "object" &&
              !Array.isArray(field.options)
                ? (field.options as Record<string, unknown>)
                : {};
            const numberPayload =
              type === "number" || type === "decimal"
                ? serializeDynNumberOptions(numberOptions)
                : null;
            return {
              ...existing,
              ...(numberPayload ?? {}),
              list_visibility_managed: true,
            };
          }
          if (
            isSystemField &&
            type !== "number" &&
            type !== "decimal" &&
            type !== "select" &&
            type !== "multiselect" &&
            type !== "text" &&
            type !== "textarea" &&
            type !== "email"
          ) {
            return field?.options ?? null;
          }
          return buildOptionsJson();
        })(),        target_entity_id:
          isSystemField || type !== "relationship" ? null : targetEntityId || null,
        conditional_rules_json: isListChrome ? null : rules.length > 0 ? rules : null,
      };

      if (mode === "edit" && field) {
        await updateDynField(field.id, payload);
      } else {
        await createDynField(entitySlug, payload);
      }
      await onSaved();
      onClose();
    } catch {
      setError(mode === "edit" ? "Unable to save field." : "Unable to create field.");
    } finally {
      setSaving(false);
    }
  }

  const title = mode === "edit" ? `Edit: ${field?.label ?? label}` : "Add field";
  const subtitle =
    mode === "edit"
      ? `${entityName} · ${isSystemField ? "System" : typeLabel(type)}`
      : `New field on ${entityName}`;

  return (
    <>
      <DialogHeader className="shrink-0 border-b border-border px-6 py-4 pr-12">
        <DialogTitle>{title}</DialogTitle>
        <DialogDescription>{subtitle}</DialogDescription>
      </DialogHeader>

      <Tabs value={tab} onValueChange={setTab} className="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden">
        <div className="shrink-0 border-b border-border px-6 pt-2">
          <TabsList variant="line" className="w-full justify-start gap-0">
            <TabsTrigger value="basics" className="px-3">
              Basics
            </TabsTrigger>
            <TabsTrigger value="form-list" className="px-3">
              Form & List
            </TabsTrigger>
            <TabsTrigger value="options" className="px-3" disabled={isListChrome}>
              Options
            </TabsTrigger>
            <TabsTrigger value="rules" className="px-3" disabled={isListChrome}>
              Rules
            </TabsTrigger>
          </TabsList>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
          <TabsContent value="basics" className="mt-0 space-y-5">
            {isSystemField ? (
              <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-200">
                {field?.name === "id"
                  ? "System ID — type and system name stay fixed. You can rename the label and control list visibility."
                  : field?.name === "actions"
                    ? "System Actions column — drives View / Edit / Clone / Delete on the record list. Not a data field."
                    : field?.name === "print"
                      ? "System Print column — drives the print button on the record list. Not a data field."
                      : "System field — type and system name stay fixed."}
              </div>
            ) : null}
            <Section title="What is this field?">
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Belongs to</span>
                <Input value={entityName} disabled />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Field type</span>
                <select
                  className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-70"
                  value={type}
                  disabled={isSystemField}
                  onChange={(e) => setType(e.target.value)}
                >
                  {FIELD_TYPE_OPTIONS.map((t) => (
                    <option key={t.value} value={t.value}>
                      {t.label}
                    </option>
                  ))}
                  {isSystemField && !FIELD_TYPE_OPTIONS.some((t) => t.value === type) ? (
                    <option value={type}>{typeLabel(type)}</option>
                  ) : null}
                </select>
              </label>
            </Section>

            <Section title="Naming">
              <label className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Field label</span>
                <Input value={label} onChange={(e) => setLabel(e.target.value)} />
              </label>
              <label className="space-y-1.5">
                <span className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                  System name
                  {field?.name ? <Check className="size-3.5 text-sky-600" aria-hidden /> : null}
                </span>
                {field?.name ? (
                  <Input value={field.name} disabled />
                ) : (
                  <p className="text-xs text-muted-foreground">Generated from the label when you save.</p>
                )}
              </label>
              {!isListChrome || isDataSystemField ? (
                <label className="space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Placeholder / hint text</span>
                  <Input
                    value={placeholder}
                    onChange={(e) => setPlaceholder(e.target.value)}
                    placeholder="e.g., Enter the compressor serial number"
                  />
                </label>
              ) : null}
            </Section>

            {!isListChrome ? (
              <Section title="Data entry">
                <label className="flex items-start gap-2 text-sm">
                  <Checkbox
                    className="mt-0.5"
                    checked={required}
                    onCheckedChange={(v) => setRequired(v === true)}
                  />
                  <span>Always required</span>
                </label>
                <label className="flex items-start gap-2 text-sm">
                  <Checkbox
                    className="mt-0.5"
                    checked={isKey}
                    onCheckedChange={(v) => setIsKey(v === true)}
                  />
                  <span>
                    Key field
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                      Used for list summaries and record titles.
                    </span>
                  </span>
                </label>
              </Section>
            ) : null}
          </TabsContent>

          <TabsContent value="form-list" className="mt-0 space-y-5">
            {!isListChrome ? (
              <Section title="Position on the form">
                <label className="space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Field group</span>
                  <select
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={groupId}
                    onChange={(e) => setGroupId(e.target.value)}
                  >
                    <option value="">Ungrouped</option>
                    {groups.map((g) => (
                      <option key={g.id} value={g.id}>
                        {g.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Field width</span>
                  <select
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={columnSpan}
                    onChange={(e) => setColumnSpan(e.target.value)}
                  >
                    {WIDTH_OPTIONS.map((w) => (
                      <option key={w.value} value={w.value}>
                        {w.label}
                      </option>
                    ))}
                    {!WIDTH_OPTIONS.some((w) => String(w.value) === columnSpan) ? (
                      <option value={columnSpan}>{columnSpan} of 12</option>
                    ) : null}
                  </select>
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Sort order</span>
                  <Input
                    type="number"
                    min={0}
                    value={fieldOrder}
                    onChange={(e) => setFieldOrder(e.target.value)}
                  />
                </label>
                <p className="text-xs text-muted-foreground">
                  Prefer dragging? Close this editor and use{" "}
                  <span className="font-medium text-foreground">Arrange Form</span> on the fields list.
                </p>
              </Section>
            ) : (
              <div className="rounded-lg border border-dashed border-border px-3 py-3 text-xs text-muted-foreground">
                {field?.name === "id"
                  ? "ID is list chrome — it is not placed on New / Edit forms via Arrange Form."
                  : "This column is list chrome only — it does not appear on New / Edit forms."}
              </div>
            )}

            <Section title="Behaviour in lists & tables">
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={showInTable}
                  onCheckedChange={(v) => setShowInTable(v === true)}
                />
                Show as a table column
              </label>
              {!isListChrome ? (
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={calculateTotals}
                    onCheckedChange={(v) => setCalculateTotals(v === true)}
                  />
                  Show a column total
                </label>
              ) : null}
              {!isListChrome || field?.name === "id" ? (
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={filterable}
                    onCheckedChange={(v) => setFilterable(v === true)}
                  />
                  Allow filtering
                </label>
              ) : null}
            </Section>
          </TabsContent>

          <TabsContent value="options" className="mt-0 space-y-5">
            {type === "relationship" ? (
              <Section title="Link configuration">
                <label className="space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">
                    Which entity does this field link to?
                  </span>
                  <select
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={targetEntityId}
                    onChange={(e) => setTargetEntityId(e.target.value)}
                  >
                    <option value="">Select entity…</option>
                    {entities.map((e) => (
                      <option key={e.id} value={e.id}>
                        {e.name}
                      </option>
                    ))}
                  </select>
                </label>

                <div className="space-y-2">
                  <div>
                    <p className="text-xs font-medium text-muted-foreground">Limit the choices (optional)</p>
                    <p className="text-xs text-muted-foreground">
                      Only records matching every condition below appear in the picker.
                    </p>
                  </div>
                  {relationFilters.map((filter, index) => (
                    <div key={index} className="flex flex-wrap items-center gap-2 rounded-lg border border-border p-2">
                      <Input
                        className="min-w-[7rem] flex-1"
                        placeholder="Field name"
                        value={filter.field}
                        onChange={(e) => {
                          const next = [...relationFilters];
                          next[index] = { ...filter, field: e.target.value };
                          setRelationFilters(next);
                        }}
                      />
                      <select
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        value={filter.op}
                        onChange={(e) => {
                          const next = [...relationFilters];
                          next[index] = {
                            ...filter,
                            op: e.target.value as DynRelationFilter["op"],
                          };
                          setRelationFilters(next);
                        }}
                      >
                        <option value="eq">equals</option>
                        <option value="neq">not equals</option>
                        <option value="contains">contains</option>
                      </select>
                      <Input
                        className="min-w-[7rem] flex-1"
                        placeholder="Value"
                        value={filter.value}
                        onChange={(e) => {
                          const next = [...relationFilters];
                          next[index] = { ...filter, value: e.target.value };
                          setRelationFilters(next);
                        }}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        aria-label="Remove condition"
                        onClick={() => setRelationFilters(relationFilters.filter((_, i) => i !== index))}
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    </div>
                  ))}
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      setRelationFilters([...relationFilters, { field: "", op: "eq", value: "" }])
                    }
                  >
                    <Plus className="size-3.5" />
                    Add condition
                  </Button>
                </div>
              </Section>
            ) : type === "text" || type === "textarea" || type === "email" ? (
              <Section title="Text behaviour & default value">
                <div className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_auto]">
                  <label className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Default Value</span>
                    {type === "textarea" ? (
                      <Textarea
                        value={textOptions.default_value}
                        placeholder="e.g., Default text or N/A"
                        rows={3}
                        onChange={(e) =>
                          setTextOptions((prev) => ({ ...prev, default_value: e.target.value }))
                        }
                      />
                    ) : (
                      <Input
                        value={textOptions.default_value}
                        placeholder="e.g., Default text or N/A"
                        onChange={(e) =>
                          setTextOptions((prev) => ({ ...prev, default_value: e.target.value }))
                        }
                      />
                    )}
                    <p className="text-xs text-muted-foreground">
                      Pre-filled value when creating a new record.
                    </p>
                  </label>
                  <label className="flex items-start gap-2 rounded-lg border border-border px-3 py-2.5 text-sm sm:min-w-[14rem]">
                    <Checkbox
                      className="mt-0.5"
                      checked={textOptions.prevent_duplicates}
                      onCheckedChange={(v) =>
                        setTextOptions((prev) => ({
                          ...prev,
                          prevent_duplicates: v === true,
                        }))
                      }
                    />
                    <span>
                      Prevent duplicates
                      <span className="mt-0.5 block text-xs text-muted-foreground">
                        Refuse to save duplicated values.
                      </span>
                    </span>
                  </label>
                </div>
              </Section>
            ) : type === "number" || type === "decimal" ? (
              <Section title="Number formatting & default">
                <div className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                  <label className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Currency Symbol</span>
                    <Input
                      value={numberOptions.currency_symbol}
                      placeholder="e.g., ₱"
                      onChange={(e) =>
                        setNumberOptions((prev) => ({ ...prev, currency_symbol: e.target.value }))
                      }
                    />
                  </label>
                  <label className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Decimal Places</span>
                    <Input
                      type="number"
                      min={0}
                      max={12}
                      value={numberOptions.decimal_places}
                      placeholder="Auto"
                      onChange={(e) =>
                        setNumberOptions((prev) => ({ ...prev, decimal_places: e.target.value }))
                      }
                    />
                  </label>
                  <label className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Default Value</span>
                    <Input
                      value={numberOptions.default_value}
                      placeholder="e.g., 0"
                      onChange={(e) =>
                        setNumberOptions((prev) => ({ ...prev, default_value: e.target.value }))
                      }
                    />
                  </label>
                  <label className="flex items-start gap-2 rounded-lg border border-border px-3 py-2.5 text-sm sm:min-w-[11rem]">
                    <Checkbox
                      className="mt-0.5"
                      checked={numberOptions.thousand_separators}
                      onCheckedChange={(v) =>
                        setNumberOptions((prev) => ({
                          ...prev,
                          thousand_separators: v === true,
                        }))
                      }
                    />
                    <span>
                      Thousand separators
                      <span className="mt-0.5 block text-xs text-muted-foreground">1,234.567</span>
                    </span>
                  </label>
                </div>
              </Section>
            ) : type === "select" || type === "multiselect" ? (
              <Section title="Choices">
                <div className="overflow-hidden rounded-lg border border-border">
                  <div className="grid grid-cols-[1fr_1fr_10rem_2.25rem] gap-2 border-b border-border bg-muted/40 px-3 py-2 text-[11px] font-medium text-muted-foreground">
                    <span>Stored value</span>
                    <span>Display label</span>
                    <span>Badge style</span>
                    <span className="sr-only">Remove</span>
                  </div>
                  <div className="divide-y divide-border">
                    {selectOptions.choices.length === 0 ? (
                      <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        No choices yet. Add the options users can pick.
                      </p>
                    ) : (
                      selectOptions.choices.map((choice, index) => (
                        <div
                          key={`choice-${index}`}
                          className="grid grid-cols-[1fr_1fr_10rem_2.25rem] items-center gap-2 px-3 py-2"
                        >
                          <Input
                            value={choice.value}
                            placeholder="Stored value"
                            onChange={(e) => updateChoice(index, { value: e.target.value })}
                          />
                          <Input
                            value={choice.label}
                            placeholder="Display label"
                            onChange={(e) => updateChoice(index, { label: e.target.value })}
                          />
                          <Select
                            value={choice.badge}
                            onChange={(e) =>
                              updateChoice(index, {
                                badge: e.target.value as DynSelectChoice["badge"],
                              })
                            }
                          >
                            {DYN_SELECT_BADGE_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>
                                {opt.label}
                              </option>
                            ))}
                          </Select>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            className="text-destructive"
                            aria-label="Remove choice"
                            onClick={() =>
                              setSelectOptions((prev) => ({
                                ...prev,
                                choices: prev.choices.filter((_, i) => i !== index),
                                default:
                                  prev.default === (choice.value.trim() || choice.label.trim())
                                    ? null
                                    : prev.default,
                              }))
                            }
                          >
                            <Trash2 className="size-3.5" />
                          </Button>
                        </div>
                      ))
                    )}
                  </div>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="mt-2"
                  onClick={() =>
                    setSelectOptions((prev) => ({
                      ...prev,
                      choices: [...prev.choices, emptyDynSelectChoice()],
                    }))
                  }
                >
                  <Plus className="size-3.5" />
                  Add Choice
                </Button>
                <p className="text-xs text-muted-foreground">
                  Leave the stored value blank to reuse the label. Badge styles colour the value
                  wherever it is shown.
                </p>
                <label className="mt-3 block space-y-1.5">
                  <span className="text-xs font-medium text-muted-foreground">
                    Default selected choice
                  </span>
                  <Select
                    value={selectOptions.default ?? ""}
                    onChange={(e) =>
                      setSelectOptions((prev) => ({
                        ...prev,
                        default: e.target.value.trim() || null,
                      }))
                    }
                  >
                    <option value="">None</option>
                    {selectOptions.choices
                      .map((c) => ({
                        value: c.value.trim() || c.label.trim(),
                        label: c.label.trim() || c.value.trim(),
                      }))
                      .filter((c) => c.value)
                      .map((c) => (
                        <option key={c.value} value={c.value}>
                          {c.label}
                        </option>
                      ))}
                  </Select>
                  <p className="text-xs text-muted-foreground">
                    Pre-selected option when creating a new record.
                  </p>
                </label>
              </Section>
            ) : (
              <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center">
                <p className="text-sm text-muted-foreground">
                  No type-specific options for {typeLabel(type).toLowerCase()} fields.
                </p>
              </div>
            )}
          </TabsContent>

          <TabsContent value="rules" className="mt-0 space-y-5">
            <Section title="Conditional behaviour">
              <p className="text-xs text-muted-foreground">
                Make this field appear, disappear or become required depending on what has been entered
                elsewhere on the record.
              </p>

              {rules.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center">
                  <p className="text-sm text-muted-foreground">
                    This field is always shown. Add a rule to control when it appears or becomes required.
                  </p>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-3"
                    onClick={() =>
                      setRules([
                        {
                          action: "show",
                          field: otherFields[0]?.name ?? "",
                          op: "eq",
                          value: "",
                        },
                      ])
                    }
                  >
                    <Plus className="size-3.5" />
                    Add a rule
                  </Button>
                </div>
              ) : (
                <div className="space-y-3">
                  {rules.map((rule, index) => (
                    <div key={index} className="space-y-2 rounded-lg border border-border p-3">
                      <div className="flex flex-wrap gap-2">
                        <select
                          className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                          value={rule.action}
                          onChange={(e) => {
                            const next = [...rules];
                            next[index] = {
                              ...rule,
                              action: e.target.value as DynConditionalRule["action"],
                            };
                            setRules(next);
                          }}
                        >
                          <option value="show">Show when</option>
                          <option value="hide">Hide when</option>
                          <option value="require">Require when</option>
                        </select>
                        <select
                          className="h-9 min-w-[8rem] flex-1 rounded-md border border-input bg-background px-2 text-sm"
                          value={rule.field}
                          onChange={(e) => {
                            const next = [...rules];
                            next[index] = { ...rule, field: e.target.value };
                            setRules(next);
                          }}
                        >
                          <option value="">Select field…</option>
                          {otherFields.map((f) => (
                            <option key={f.id} value={f.name}>
                              {f.label}
                            </option>
                          ))}
                        </select>
                        <select
                          className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                          value={rule.op}
                          onChange={(e) => {
                            const next = [...rules];
                            next[index] = {
                              ...rule,
                              op: e.target.value as DynConditionalRule["op"],
                            };
                            setRules(next);
                          }}
                        >
                          <option value="eq">equals</option>
                          <option value="neq">not equals</option>
                          <option value="empty">is empty</option>
                          <option value="not_empty">is not empty</option>
                        </select>
                        {rule.op === "eq" || rule.op === "neq" ? (
                          <Input
                            className="min-w-[7rem] flex-1"
                            placeholder="Value"
                            value={rule.value ?? ""}
                            onChange={(e) => {
                              const next = [...rules];
                              next[index] = { ...rule, value: e.target.value };
                              setRules(next);
                            }}
                          />
                        ) : null}
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label="Remove rule"
                          onClick={() => setRules(rules.filter((_, i) => i !== index))}
                        >
                          <Trash2 className="size-3.5" />
                        </Button>
                      </div>
                    </div>
                  ))}
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      setRules([
                        ...rules,
                        {
                          action: "show",
                          field: otherFields[0]?.name ?? "",
                          op: "eq",
                          value: "",
                        },
                      ])
                    }
                  >
                    <Plus className="size-3.5" />
                    Add a rule
                  </Button>
                </div>
              )}
            </Section>
          </TabsContent>

          {error ? <p className="mt-4 text-sm text-destructive">{error}</p> : null}
        </div>
      </Tabs>

      <DialogFooter className="shrink-0 border-t border-border px-6 py-3 sm:justify-end">
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="button" disabled={saving || !label.trim()} onClick={() => void save()}>
          <Check className="size-3.5" />
          {saving ? "Saving…" : mode === "edit" ? "Save field" : "Add field"}
        </Button>
      </DialogFooter>
    </>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <h4 className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{title}</h4>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

function EditGroupForm({
  group,
  onClose,
  onSaved,
}: {
  group: DynEntityDetail["field_groups"][number];
  onClose: () => void;
  onSaved: () => Promise<void> | void;
}) {
  const [name, setName] = useState(group.name);
  const [description, setDescription] = useState(group.description ?? "");
  const [appliesForm, setAppliesForm] = useState(group.applies_to_form);
  const [appliesView, setAppliesView] = useState(group.applies_to_view);
  const [startCollapsed, setStartCollapsed] = useState(Boolean(group.start_collapsed));
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    setName(group.name);
    setDescription(group.description ?? "");
    setAppliesForm(group.applies_to_form);
    setAppliesView(group.applies_to_view);
    setStartCollapsed(Boolean(group.start_collapsed));
  }, [group]);

  async function save() {
    setSaving(true);
    setError(null);
    try {
      await updateDynFieldGroup(group.id, {
        name: name.trim(),
        description: description.trim() || null,
        applies_to_form: appliesForm,
        applies_to_view: appliesView,
        start_collapsed: startCollapsed,
      });
      await onSaved();
      onClose();
    } catch {
      setError("Unable to save group.");
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (
      !window.confirm(
        `Delete group “${group.name}”? Fields in this group stay on the record but move to Other fields.`,
      )
    ) {
      return;
    }
    setDeleting(true);
    setError(null);
    try {
      await deleteDynFieldGroup(group.id);
      await onSaved();
      onClose();
    } catch {
      setError("Unable to delete group.");
    } finally {
      setDeleting(false);
    }
  }

  return (
    <>
      <DialogHeader className="shrink-0 border-b border-border px-6 py-4 pr-12">
        <DialogTitle>Edit field group</DialogTitle>
        <DialogDescription>Rename, control where this group appears, or delete it.</DialogDescription>
      </DialogHeader>
      <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-6 py-4">
        <label className="space-y-1.5">
          <span className="text-xs font-medium text-muted-foreground">Group name</span>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </label>
        <label className="space-y-1.5">
          <span className="text-xs font-medium text-muted-foreground">Description (optional)</span>
          <Input value={description} onChange={(e) => setDescription(e.target.value)} />
        </label>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox checked={appliesForm} onCheckedChange={(v) => setAppliesForm(v === true)} />
          Show in New / Edit form
        </label>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox checked={appliesView} onCheckedChange={(v) => setAppliesView(v === true)} />
          Show in record view
        </label>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={startCollapsed}
            onCheckedChange={(v) => setStartCollapsed(v === true)}
          />
          Start collapsed
        </label>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </div>
      <DialogFooter className="shrink-0 border-t border-border px-6 py-3 sm:justify-between">
        <Button
          type="button"
          variant="destructive"
          className="gap-1.5"
          disabled={saving || deleting}
          onClick={() => void remove()}
        >
          <Trash2 className="size-3.5" />
          {deleting ? "Deleting…" : "Delete group"}
        </Button>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="button" disabled={saving || deleting || !name.trim()} onClick={() => void save()}>
            {saving ? "Saving…" : "Save group"}
          </Button>
        </div>
      </DialogFooter>
    </>
  );
}

function NewGroupForm({
  entitySlug,
  onClose,
  onSaved,
}: {
  entitySlug: string;
  onClose: () => void;
  onSaved: () => Promise<void> | void;
}) {
  const [name, setName] = useState("");
  const [startCollapsed, setStartCollapsed] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function save() {
    setSaving(true);
    setError(null);
    try {
      await createDynFieldGroup(entitySlug, {
        name: name.trim(),
        applies_to_form: true,
        applies_to_view: true,
        start_collapsed: startCollapsed,
      });
      await onSaved();
      onClose();
    } catch {
      setError("Unable to create group.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <DialogHeader className="shrink-0 border-b border-border px-6 py-4 pr-12">
        <DialogTitle>New field group</DialogTitle>
        <DialogDescription>Groups organize fields on New and View forms.</DialogDescription>
      </DialogHeader>
      <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-6 py-4">
        <label className="space-y-1.5">
          <span className="text-xs font-medium text-muted-foreground">Group name</span>
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Basic Information" />
        </label>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={startCollapsed}
            onCheckedChange={(v) => setStartCollapsed(v === true)}
          />
          Start collapsed
        </label>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </div>
      <DialogFooter className="shrink-0 border-t border-border px-6 py-3 sm:justify-end">
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="button" disabled={saving || !name.trim()} onClick={() => void save()}>
          {saving ? "Saving…" : "Create group"}
        </Button>
      </DialogFooter>
    </>
  );
}
