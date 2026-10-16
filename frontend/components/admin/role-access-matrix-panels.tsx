"use client";

import { Fragment, useMemo, useState } from "react";
import { ChevronDown, Settings2 } from "lucide-react";

import { RoleDataFiltersDialog } from "@/components/admin/role-data-filters-dialog";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Switch } from "@/components/ui/switch";
import type {
  FieldAccessLevel,
  RoleAccessMatrix,
  RoleDataFilterGroup,
} from "@/lib/api/modules/admin-roles-api";
import { FIELD_ACCESS_LEVELS } from "@/lib/rbac/field-surface-access";
import { dynSelectChoiceLabel, dynSelectChoiceValues } from "@/lib/dynamic-entities/select-choices";
import type { DynEntityDetail, DynEntitySummary, DynField } from "@/lib/api/modules/dynamic-entities-api";
import { dynWorkflowActionsFromFields } from "@/lib/dynamic-entities/dyn-workflow-actions";
import { cn } from "@/lib/utils";

const ENTITY_COLS = [
  { id: "view", label: "View", tone: "" },
  { id: "view_own", label: "View Own", tone: "" },
  { id: "create", label: "Create", tone: "" },
  { id: "edit", label: "Edit", tone: "" },
  { id: "delete", label: "Delete", tone: "" },
  { id: "export", label: "Export", tone: "" },
  { id: "import", label: "Import", tone: "text-info" },
  { id: "smart_scan", label: "Smart Scan", tone: "text-info" },
  { id: "undo", label: "Undo", tone: "text-warning" },
  { id: "truncate", label: "Truncate", tone: "text-destructive" },
  { id: "duplicate", label: "Duplicate", tone: "text-success" },
] as const;

/** Every stored field, including IT Ticket system fields and list chrome. */
function isFieldLevelSecurityField(field: DynField): boolean {
  return field.name.trim() !== "";
}

type EntityAccessKey = (typeof ENTITY_COLS)[number]["id"];

type Props = {
  tab: "data" | "fields" | "workflow" | "status";
  roleName?: string;
  entities: DynEntitySummary[];
  entityDetails: Record<string, DynEntityDetail | undefined>;
  loadEntityDetail: (slug: string) => void;
  matrix: RoleAccessMatrix;
  filter: string;
  entityGroup?: string;
  groupByPack?: boolean;
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
    import: hasManage,
    smart_scan: hasManage,
    undo: hasManage,
    truncate: hasManage,
    duplicate: hasManage,
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
  entityGroup = "all",
  groupByPack = false,
  disabled,
  onChange,
}: Props) {
  const [expanded, setExpanded] = useState<string | null>(null);
  const [filtersEntity, setFiltersEntity] = useState<DynEntitySummary | null>(null);
  const needle = filter.trim().toLowerCase();

  const filteredEntities = useMemo(() => {
    const grouped = entityGroup === "all" ? entities : entities.filter((e) => e.module_pack === entityGroup);
    const matched = !needle
      ? grouped
      : grouped.filter((e) => {
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
    if (!groupByPack) return matched;
    return [...matched].sort(
      (a, b) => a.module_pack.localeCompare(b.module_pack) || a.name.localeCompare(b.name),
    );
  }, [entities, entityDetails, entityGroup, groupByPack, needle, tab]);

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
      const create = Boolean(row.create);
      const edit = Boolean(row.edit);
      const del = Boolean(row.delete);
      return {
        view: Boolean(row.view),
        view_own: Boolean(row.view_own),
        create,
        edit,
        delete: del,
        export: Boolean(row.export),
        import: row.import ?? create,
        smart_scan: row.smart_scan ?? create,
        undo: row.undo ?? edit,
        truncate: row.truncate ?? del,
        duplicate: row.duplicate ?? create,
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
        ? {
            view: true,
            view_own: false,
            create: true,
            edit: true,
            delete: true,
            export: true,
            import: true,
            smart_scan: true,
            undo: true,
            truncate: true,
            duplicate: true,
          }
        : {
            view: false,
            view_own: false,
            create: false,
            edit: false,
            delete: false,
            export: false,
            import: false,
            smart_scan: false,
            undo: false,
            truncate: false,
            duplicate: false,
          };
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
      <div className="flex min-h-0 flex-1 flex-col gap-3">
        <div className="shrink-0 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
          <strong className="font-medium text-foreground">View</strong> shows all records.{" "}
          <strong className="font-medium text-foreground">View Own</strong> limits to records the user
          created or is assigned (disables View). Use{" "}
          <strong className="font-medium text-foreground">Data Filters</strong> for field-level row
          restrictions. Import, undo and duplicate are enforced on the record list. Smart Scan and
          Truncate are saved on the role for those tools.
        </div>
        <div className="flex shrink-0 flex-wrap items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground">
            {needle
              ? `Bulk actions apply to ${filteredEntities.length} filtered entit${filteredEntities.length === 1 ? "y" : "ies"}.`
              : "Bulk actions apply to all entities in this list."}
          </p>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || filteredEntities.length === 0}
              onClick={() => applyAllFlagsToFiltered(true)}
            >
              Check all
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || filteredEntities.length === 0}
              onClick={() => applyAllFlagsToFiltered(false)}
            >
              Uncheck all
            </Button>
          </div>
        </div>
        <div className="min-h-0 flex-1 overflow-auto rounded-lg border border-border">
          <table className="w-max min-w-full border-separate border-spacing-0 text-sm">
            <thead className="text-left text-xs font-medium text-muted-foreground">
              <tr>
                <th className="sticky top-0 left-0 z-20 w-44 min-w-44 border-b border-r border-border bg-muted px-3 py-2 text-left">
                  Entity Name
                </th>
                {ENTITY_COLS.map((col) => {
                  const allOn =
                    filteredEntities.length > 0 &&
                    filteredEntities.every((entity) => entityRow(entity.slug)[col.id]);
                  return (
                    <th
                      key={col.id}
                      className="sticky top-0 z-10 w-16 min-w-16 border-b border-border bg-muted px-1 py-2 text-center align-bottom"
                    >
                      <span className={cn("mb-1 block whitespace-normal text-[11px] leading-tight", col.tone)}>
                        {col.label}
                      </span>
                      <div className="flex justify-center">
                        <Checkbox
                          checked={allOn}
                          disabled={disabled || filteredEntities.length === 0}
                          title={allOn ? `Uncheck all ${col.label}` : `Check all ${col.label}`}
                          aria-label={allOn ? `Uncheck all ${col.label}` : `Check all ${col.label}`}
                          onCheckedChange={(checked) =>
                            applyColumnToFiltered(col.id, checked === true)
                          }
                        />
                      </div>
                    </th>
                  );
                })}
                <th className="sticky top-0 z-10 min-w-32 border-b border-border bg-muted px-2 py-2 text-center align-bottom">
                  Data Filters
                </th>
              </tr>
            </thead>
            <tbody>
              {filteredEntities.length === 0 ? (
                <tr>
                  <td colSpan={ENTITY_COLS.length + 2} className="px-3 py-8 text-center text-sm text-muted-foreground">
                    {needle
                      ? "No entities match this filter."
                      : "No Dynamic Entities found. Confirm the Dynamic Entities module is on and packs are imported."}
                  </td>
                </tr>
              ) : (
                filteredEntities.map((entity, index) => {
                  const row = entityRow(entity.slug);
                  const count = filterCount(entity.slug);
                  const showPack =
                    groupByPack &&
                    (index === 0 || filteredEntities[index - 1]?.module_pack !== entity.module_pack);
                  return (
                    <Fragment key={entity.id}>
                    {showPack ? (
                      <tr>
                        <td
                          colSpan={ENTITY_COLS.length + 2}
                          className="border-b border-border bg-muted px-3 py-1.5 text-xs font-medium capitalize text-muted-foreground"
                        >
                          {entity.module_pack.replace(/_/g, " ")}
                        </td>
                      </tr>
                    ) : null}
                    <tr className="group">
                      <td className="sticky left-0 z-10 w-44 min-w-44 border-r border-b border-border/70 bg-card px-3 py-2 font-medium text-foreground group-hover:bg-muted">
                        {entity.name}
                      </td>
                      {ENTITY_COLS.map((col) => (
                        <td key={col.id} className="w-16 min-w-16 border-b border-border/70 px-1 py-2 align-middle">
                          <div className="flex justify-center">
                            <Checkbox
                              checked={row[col.id]}
                              disabled={disabled}
                              aria-label={`${entity.name} ${col.label}`}
                              onCheckedChange={(checked) =>
                                setEntityFlag(entity.slug, col.id, checked === true)
                              }
                            />
                          </div>
                        </td>
                      ))}
                      <td className="border-b border-border/70 px-2 py-2 text-center align-middle">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          disabled={disabled}
                          className={cn(count > 0 && "border-foreground/30 bg-muted/50")}
                          onClick={() => {
                            loadEntityDetail(entity.slug);
                            setFiltersEntity(entity);
                          }}
                        >
                          <Settings2 className="size-3.5" />
                          Filters
                          <span className="rounded-md bg-muted px-1.5 tabular-nums text-[10px] text-muted-foreground">
                            {count}
                          </span>
                        </Button>
                      </td>
                    </tr>
                    </Fragment>
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
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto">
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
          One access level per field, the same list as the field menu. Default is Full access.
        </div>
        <div className="rounded-lg border border-border">
          {filteredEntities.length === 0 ? (
            <p className="px-3 py-8 text-center text-sm text-muted-foreground">
              {needle
                ? "No entities match this filter."
                : "No Dynamic Entities found. Confirm the Dynamic Entities module is on and packs are imported."}
            </p>
          ) : null}
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
                        {needle ? "No fields match this search." : "No fields."}
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
                                    className="h-8 w-full max-w-[16rem] rounded-md border border-input bg-background px-2 text-xs"
                                    value={level}
                                    disabled={disabled}
                                    aria-label={`Access for ${field.label}`}
                                    onChange={(e) =>
                                      setFieldLevel(entity.slug, field.name, e.target.value as FieldAccessLevel)
                                    }
                                  >
                                    {FIELD_ACCESS_LEVELS.map((opt) => (
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

  if (tab === "status") {
    return (
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto">
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
          Choose which status values this role can see and set. Leaving every value on means the role
          is not limited.
        </div>
        <div className="rounded-lg border border-border">
          {filteredEntities.length === 0 ? (
            <p className="px-3 py-8 text-center text-sm text-muted-foreground">
              {needle ? "No entities match this filter." : "No Dynamic Entities found."}
            </p>
          ) : null}
          {filteredEntities.map((entity) => {
            const open = expanded === entity.slug;
            const detail = entityDetails[entity.slug];
            const statusField =
              detail?.fields.find((field) => field.name === "status") ??
              detail?.fields.find((field) => field.type === "status") ??
              detail?.fields.find((field) => /status/i.test(field.name));
            const choices = statusField ? dynSelectChoiceValues(statusField.options) : [];
            const limited = matrix.statuses?.[entity.slug];
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
                    <span className="font-normal text-muted-foreground">
                      ({limited ? `${limited.length} of ${choices.length || "…"}` : "all statuses"})
                    </span>
                  </span>
                  <ChevronDown className={cn("size-4 text-muted-foreground transition-transform", open && "rotate-180")} />
                </button>
                {open ? (
                  <div className="border-t border-border px-3 py-3">
                    {!detail ? (
                      <p className="text-xs text-muted-foreground">Loading fields…</p>
                    ) : !statusField || choices.length === 0 ? (
                      <p className="text-xs text-muted-foreground">This entity has no status choices to limit.</p>
                    ) : (
                      <ul className="grid gap-2 sm:grid-cols-2">
                        {choices.map((choice) => {
                          const checked = !limited || limited.includes(choice);
                          return (
                            <label key={choice} className="flex items-center gap-2 text-sm">
                              <Checkbox
                                checked={checked}
                                disabled={disabled}
                                onCheckedChange={(next) => {
                                  const current = limited ?? choices;
                                  const selected = next === true
                                    ? [...new Set([...current, choice])]
                                    : current.filter((value) => value !== choice);
                                  const statuses = { ...(matrix.statuses ?? {}) };
                                  if (selected.length === choices.length) {
                                    delete statuses[entity.slug];
                                  } else {
                                    statuses[entity.slug] = selected;
                                  }
                                  onChange({
                                    ...matrix,
                                    statuses: Object.keys(statuses).length > 0 ? statuses : undefined,
                                  });
                                }}
                              />
                              <span>{dynSelectChoiceLabel(statusField.options, choice)}</span>
                            </label>
                          );
                        })}
                      </ul>
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
    <div className="min-h-0 flex-1 space-y-3 overflow-y-auto">
      <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
        Toggle a step to Active so users in this role can trigger that workflow action on the entity.
      </div>
      <div className="rounded-lg border border-border">
        {filteredEntities.length === 0 ? (
          <p className="px-3 py-8 text-center text-sm text-muted-foreground">
            {needle
              ? "No entities match this filter."
              : "No Dynamic Entities found. Confirm the Dynamic Entities module is on and packs are imported."}
          </p>
        ) : null}
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
