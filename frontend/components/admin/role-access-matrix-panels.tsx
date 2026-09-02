"use client";

import { useMemo, useState } from "react";
import { ChevronDown, Settings2 } from "lucide-react";

import { RoleDataFiltersDialog } from "@/components/admin/role-data-filters-dialog";
import { Switch } from "@/components/ui/switch";
import type {
  FieldAccessLevel,
  RoleAccessMatrix,
  RoleDataFilterGroup,
} from "@/lib/api/modules/admin-roles-api";
import type { DynEntityDetail, DynEntitySummary, DynField } from "@/lib/api/modules/dynamic-entities-api";
import { dynWorkflowActionsFromFields } from "@/lib/dynamic-entities/dyn-workflow-actions";
import { cn } from "@/lib/utils";

const ENTITY_COLS = [
  { id: "view", label: "View" },
  { id: "view_own", label: "View Own" },
  { id: "create", label: "Create" },
  { id: "edit", label: "Edit" },
  { id: "delete", label: "Delete" },
  { id: "export", label: "Export" },
] as const;

const FIELD_LEVELS: Array<{ value: FieldAccessLevel; label: string }> = [
  { value: "full", label: "Full access" },
  { value: "view", label: "View only (read-only)" },
  { value: "table_record", label: "Table + Record view only" },
  { value: "record", label: "Record view only" },
  { value: "form", label: "Form only (fill-in)" },
  { value: "hide", label: "Hide from this role" },
];

/** List chrome — Metacoresoft Field Level Security includes these (form/table/record ACL). */
const LIST_CHROME_FIELD_NAMES = new Set(["id", "actions", "workflows", "print"]);

function isFieldLevelSecurityField(field: DynField): boolean {
  if (LIST_CHROME_FIELD_NAMES.has(field.name)) return true;
  if (field.name === "status") return true;
  if (field.is_system_field) return false;
  return true;
}

type EntityAccessKey = (typeof ENTITY_COLS)[number]["id"];

type Props = {
  tab: "data" | "fields" | "workflow";
  roleName?: string;
  entities: DynEntitySummary[];
  entityDetails: Record<string, DynEntityDetail | undefined>;
  loadEntityDetail: (slug: string) => void;
  matrix: RoleAccessMatrix;
  filter: string;
  disabled?: boolean;
  onChange: (next: RoleAccessMatrix) => void;
};

function defaultEntityAccess(hasManage: boolean): Record<EntityAccessKey, boolean> {
  return {
    view: true,
    view_own: false,
    create: hasManage,
    edit: hasManage,
    delete: hasManage,
    export: true,
  };
}

/** True when the API name is just a slugified form of the label (no need to show both). */
function isRedundantFieldSlug(label: string, name: string): boolean {
  const norm = (value: string) => value.toLowerCase().replace(/[^a-z0-9]+/g, "");
  return norm(label) === norm(name);
}

export function RoleAccessMatrixPanels({
  tab,
  roleName = "Role",
  entities,
  entityDetails,
  loadEntityDetail,
  matrix,
  filter,
  disabled,
  onChange,
}: Props) {
  const [expanded, setExpanded] = useState<string | null>(null);
  const [filtersEntity, setFiltersEntity] = useState<DynEntitySummary | null>(null);
  const needle = filter.trim().toLowerCase();

  const filteredEntities = useMemo(() => {
    if (!needle) return entities;
    return entities.filter((e) => {
      if (e.name.toLowerCase().includes(needle) || e.slug.includes(needle)) return true;
      if (tab !== "fields") return false;
      const detail = entityDetails[e.slug];
      if (!detail) return false;
      return detail.fields.some(
        (f) =>
          isFieldLevelSecurityField(f) &&
          (f.label.toLowerCase().includes(needle) || f.name.toLowerCase().includes(needle)),
      );
    });
  }, [entities, entityDetails, needle, tab]);

  function fieldsForEntity(detail: DynEntityDetail | undefined): DynField[] {
    if (!detail) return [];
    return [...detail.fields]
      .filter(isFieldLevelSecurityField)
      .filter((f) => {
        if (!needle) return true;
        // If the entity matched by name/slug, show all fields; otherwise filter to matching fields.
        const entityMatched =
          detail.name.toLowerCase().includes(needle) || detail.slug.includes(needle);
        if (entityMatched) return true;
        return f.label.toLowerCase().includes(needle) || f.name.toLowerCase().includes(needle);
      })
      .sort((a, b) => a.field_order - b.field_order);
  }

  function entityRow(slug: string): Record<EntityAccessKey, boolean> {
    const row = matrix.entities?.[slug];
    if (row) {
      return {
        view: Boolean(row.view),
        view_own: Boolean(row.view_own),
        create: Boolean(row.create),
        edit: Boolean(row.edit),
        delete: Boolean(row.delete),
        export: Boolean(row.export),
      };
    }
    return defaultEntityAccess(true);
  }

  function setEntityFlag(slug: string, key: EntityAccessKey, value: boolean) {
    const current = entityRow(slug);
    const nextRow = { ...current, [key]: value };
    if (key === "view" && value) nextRow.view_own = false;
    if (key === "view_own" && value) nextRow.view = false;
    onChange({
      ...matrix,
      entities: {
        ...(matrix.entities ?? {}),
        [slug]: nextRow,
      },
    });
  }

  function applyColumnToFiltered(key: EntityAccessKey, value: boolean) {
    if (filteredEntities.length === 0) return;
    const nextEntities = { ...(matrix.entities ?? {}) };
    for (const entity of filteredEntities) {
      const current = entityRow(entity.slug);
      const nextRow = { ...current, [key]: value };
      if (key === "view" && value) nextRow.view_own = false;
      if (key === "view_own" && value) nextRow.view = false;
      nextEntities[entity.slug] = nextRow;
    }
    onChange({ ...matrix, entities: nextEntities });
  }

  function applyAllFlagsToFiltered(value: boolean) {
    if (filteredEntities.length === 0) return;
    const nextEntities = { ...(matrix.entities ?? {}) };
    for (const entity of filteredEntities) {
      nextEntities[entity.slug] = value
        ? { view: true, view_own: false, create: true, edit: true, delete: true, export: true }
        : { view: false, view_own: false, create: false, edit: false, delete: false, export: false };
    }
    onChange({ ...matrix, entities: nextEntities });
  }

  function setFieldLevel(slug: string, field: string, level: FieldAccessLevel) {
    onChange({
      ...matrix,
      fields: {
        ...(matrix.fields ?? {}),
        [slug]: {
          ...(matrix.fields?.[slug] ?? {}),
          [field]: level,
        },
      },
    });
  }

  function setWorkflow(slug: string, action: string, value: boolean) {
    onChange({
      ...matrix,
      workflows: {
        ...(matrix.workflows ?? {}),
        [slug]: {
          ...(matrix.workflows?.[slug] ?? {}),
          [action]: value,
        },
      },
    });
  }

  function filterCount(slug: string): number {
    return matrix.filters?.[slug]?.rules?.length ?? 0;
  }

  function setEntityFilters(slug: string, next: RoleDataFilterGroup | null) {
    const filters = { ...(matrix.filters ?? {}) };
    if (!next || next.rules.length === 0) {
      delete filters[slug];
    } else {
      filters[slug] = next;
    }
    onChange({
      ...matrix,
      filters: Object.keys(filters).length > 0 ? filters : undefined,
    });
  }

  if (tab === "data") {
    return (
      <div className="space-y-3">
        <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
          <strong className="font-medium">View</strong> shows all records.{" "}
          <strong className="font-medium">View Own</strong> limits to records the user created or is
          assigned (disables View). Use <strong className="font-medium">Data Filters</strong> for
          field-level row restrictions.
        </div>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground">
            {needle
              ? `Bulk actions apply to ${filteredEntities.length} filtered entit${filteredEntities.length === 1 ? "y" : "ies"}.`
              : "Bulk actions apply to all entities in this list."}
          </p>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground hover:bg-muted disabled:opacity-50"
              disabled={disabled || filteredEntities.length === 0}
              onClick={() => applyAllFlagsToFiltered(true)}
            >
              Check all
            </button>
            <button
              type="button"
              className="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground hover:bg-muted disabled:opacity-50"
              disabled={disabled || filteredEntities.length === 0}
              onClick={() => applyAllFlagsToFiltered(false)}
            >
              Uncheck all
            </button>
          </div>
        </div>
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="min-w-full text-sm">
            <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
              <tr>
                <th className="px-3 py-2.5">Entity Name</th>
                {ENTITY_COLS.map((col) => {
                  const allOn =
                    filteredEntities.length > 0 &&
                    filteredEntities.every((entity) => entityRow(entity.slug)[col.id]);
                  return (
                    <th key={col.id} className="px-2 py-2.5 text-center">
                      <div className="flex flex-col items-center gap-1">
                        <span>{col.label}</span>
                        <input
                          type="checkbox"
                          className="size-3.5 rounded border-input"
                          checked={allOn}
                          disabled={disabled || filteredEntities.length === 0}
                          title={allOn ? `Uncheck all ${col.label}` : `Check all ${col.label}`}
                          aria-label={allOn ? `Uncheck all ${col.label}` : `Check all ${col.label}`}
                          onChange={(e) => applyColumnToFiltered(col.id, e.target.checked)}
                        />
                      </div>
                    </th>
                  );
                })}
                <th className="px-2 py-2.5 text-center">Data Filters</th>
              </tr>
            </thead>
            <tbody>
              {filteredEntities.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-8 text-center text-sm text-muted-foreground">
                    No Dynamic Entities found.
                  </td>
                </tr>
              ) : (
                filteredEntities.map((entity) => {
                  const row = entityRow(entity.slug);
                  const count = filterCount(entity.slug);
                  return (
                    <tr key={entity.id} className="border-t border-border/70">
                      <td className="px-3 py-2 font-medium text-foreground">{entity.name}</td>
                      {ENTITY_COLS.map((col) => (
                        <td key={col.id} className="px-2 py-2 text-center">
                          <input
                            type="checkbox"
                            className="size-4 rounded border-input"
                            checked={row[col.id]}
                            disabled={disabled}
                            aria-label={`${entity.name} ${col.label}`}
                            onChange={(e) => setEntityFlag(entity.slug, col.id, e.target.checked)}
                          />
                        </td>
                      ))}
                      <td className="px-2 py-2 text-center">
                        <button
                          type="button"
                          disabled={disabled}
                          className={cn(
                            "inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-2.5 text-xs font-medium hover:bg-muted disabled:opacity-50",
                            count > 0 && "border-sky-300 text-sky-800 dark:border-sky-700 dark:text-sky-200",
                          )}
                          onClick={() => {
                            loadEntityDetail(entity.slug);
                            setFiltersEntity(entity);
                          }}
                        >
                          <Settings2 className="size-3.5" />
                          Filters
                          <span className="rounded-full bg-muted px-1.5 tabular-nums text-[10px] text-muted-foreground">
                            {count}
                          </span>
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {filtersEntity ? (
          <RoleDataFiltersDialog
            open={filtersEntity !== null}
            onOpenChange={(next) => {
              if (!next) setFiltersEntity(null);
            }}
            roleName={roleName}
            entityName={filtersEntity.name}
            fields={entityDetails[filtersEntity.slug]?.fields ?? []}
            initial={matrix.filters?.[filtersEntity.slug] ?? null}
            onSave={(next) => setEntityFilters(filtersEntity.slug, next)}
          />
        ) : null}
      </div>
    );
  }

  if (tab === "fields") {
    return (
      <div className="space-y-3">
        <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
          Configure access per view — form, table and record. Default is Full access.
        </div>
        <div className="rounded-lg border border-border">
          {filteredEntities.map((entity) => {
            const open = expanded === entity.slug;
            const detail = entityDetails[entity.slug];
            const fields = fieldsForEntity(detail);
            const fieldCountLabel = detail ? String(fields.length) : "…";
            return (
              <div key={entity.id} className="border-b border-border last:border-b-0">
                <button
                  type="button"
                  className="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-muted/40"
                  onClick={() => {
                    const next = open ? null : entity.slug;
                    setExpanded(next);
                    if (next) loadEntityDetail(next);
                  }}
                >
                  <span className="font-medium">
                    {entity.name}{" "}
                    <span className="font-normal text-muted-foreground">({fieldCountLabel} fields)</span>
                  </span>
                  <ChevronDown className={cn("size-4 text-muted-foreground transition-transform", open && "rotate-180")} />
                </button>
                {open ? (
                  <div className="border-t border-border bg-muted/10 px-3 py-3">
                    {!detail ? (
                      <p className="text-xs text-muted-foreground">Loading fields…</p>
                    ) : fields.length === 0 ? (
                      <p className="text-xs text-muted-foreground">
                        {needle ? "No fields match this search." : "No custom fields."}
                      </p>
                    ) : (
                      <table className="min-w-full text-sm">
                        <thead className="text-left text-xs font-medium text-muted-foreground">
                          <tr>
                            <th className="py-1.5 pr-3">Field</th>
                            <th className="py-1.5">Access</th>
                          </tr>
                        </thead>
                        <tbody>
                          {fields.map((field) => {
                            const level =
                              (matrix.fields?.[entity.slug]?.[field.name] as FieldAccessLevel | undefined) ??
                              "full";
                            const showSlug = !isRedundantFieldSlug(field.label, field.name);
                            return (
                              <tr key={field.id} className="border-t border-border/60">
                                <td className="py-2 pr-3">
                                  <div className="font-medium text-foreground">{field.label}</div>
                                  {showSlug ? (
                                    <div className="font-mono text-[11px] text-muted-foreground">{field.name}</div>
                                  ) : null}
                                </td>
                                <td className="py-2">
                                  <select
                                    className="h-8 w-full max-w-xs rounded-md border border-input bg-background px-2 text-xs"
                                    value={level}
                                    disabled={disabled}
                                    aria-label={`Access for ${field.label}`}
                                    onChange={(e) =>
                                      setFieldLevel(entity.slug, field.name, e.target.value as FieldAccessLevel)
                                    }
                                  >
                                    {FIELD_LEVELS.map((opt) => (
                                      <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                      </option>
                                    ))}
                                  </select>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    )}
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  // workflow
  return (
    <div className="space-y-3">
      <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
        Toggle a step to Active so users in this role can trigger that workflow action on the entity.
      </div>
      <div className="rounded-lg border border-border">
        {filteredEntities.map((entity) => {
          const open = expanded === entity.slug;
          const registry = dynWorkflowActionsFromFields(
            entity.slug,
            entityDetails[entity.slug]?.fields,
          );
          const actions =
            registry.length > 0
              ? registry.map((a) => ({ id: a.id, label: a.label }))
              : [
                  { id: "approve", label: `Approve ${entity.name}` },
                  { id: "cancel", label: `Cancel ${entity.name}` },
                ];
          return (
            <div key={entity.id} className="border-b border-border last:border-b-0">
              <button
                type="button"
                className="flex w-full items-center justify-between bg-muted/20 px-3 py-2.5 text-left text-sm font-medium hover:bg-muted/40"
                onClick={() => setExpanded(open ? null : entity.slug)}
              >
                <span>
                  {entity.name}{" "}
                  <span className="font-normal text-muted-foreground">
                    ({actions.length} action{actions.length === 1 ? "" : "s"})
                  </span>
                </span>
                <ChevronDown className={cn("size-4 text-muted-foreground transition-transform", open && "rotate-180")} />
              </button>
              {open ? (
                <ul className="divide-y divide-border">
                  {actions.map((action) => {
                    const on = Boolean(matrix.workflows?.[entity.slug]?.[action.id]);
                    return (
                      <li key={action.id} className="flex items-center justify-between gap-3 px-3 py-2.5">
                        <p className="text-sm font-medium text-foreground">{action.label}</p>
                        <Switch
                          checked={on}
                          disabled={disabled}
                          onCheckedChange={(checked) => setWorkflow(entity.slug, action.id, checked)}
                        />
                      </li>
                    );
                  })}
                </ul>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}
