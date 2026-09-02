"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { LayoutGrid, Pencil, Plus } from "lucide-react";

import { DynArrangeFormDialog } from "@/components/dynamic-entities/dyn-arrange-form-dialog";
import { DynSchemaSheets, type DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import {
  fetchDynEntities,
  fetchDynEntity,
  updateDynField,
  type DynEntityDetail,
  type DynEntitySummary,
  type DynField,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type QuickFilter = "all" | "required" | "in_table" | "has_rules" | "system";

function fieldHasRules(field: DynField): boolean {
  const raw = field.conditional_rules;
  if (!raw) return false;
  if (Array.isArray(raw)) return raw.length > 0;
  if (typeof raw === "object" && Array.isArray(raw.rules)) return raw.rules.length > 0;
  return false;
}

function typeLabel(type: string): string {
  if (type === "system" || type.startsWith("system")) return "System";
  return type;
}

/** Metacoresoft-parity: these system fields open an editor (workflows has a dedicated modal). */
function isEditableSystemField(name: string): boolean {
  return name === "id" || name === "actions" || name === "print" || name === "workflows";
}

/** List chrome columns driven by show_in_table on the record list. */
function isListChromeField(name: string): boolean {
  return name === "id" || name === "actions" || name === "print";
}

function isListVisibilityManaged(field: DynField): boolean {
  const opts = field.options;
  return (
    opts !== null &&
    typeof opts === "object" &&
    !Array.isArray(opts) &&
    (opts as { list_visibility_managed?: unknown }).list_visibility_managed === true
  );
}

/** Effective In-table for list chrome (ATC import left these false while columns still show). */
function effectiveShowInTable(field: DynField): boolean {
  if (isListChromeField(field.name) && !isListVisibilityManaged(field)) return true;
  return Boolean(field.show_in_table);
}

export function DynamicFieldsAdminPageClient() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const initialSlug = searchParams.get("entity") ?? "";

  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [slug, setSlug] = useState(initialSlug);
  const [detail, setDetail] = useState<DynEntityDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState("all");
  const [quickFilter, setQuickFilter] = useState<QuickFilter>("all");
  const [schemaMode, setSchemaMode] = useState<DynSchemaMode>({ kind: "closed" });
  const [arrangeOpen, setArrangeOpen] = useState(false);
  const [togglingId, setTogglingId] = useState<string | null>(null);

  const syncEntityQuery = useCallback(
    (nextSlug: string) => {
      setSlug(nextSlug);
      const params = new URLSearchParams(searchParams.toString());
      if (nextSlug) params.set("entity", nextSlug);
      else params.delete("entity");
      const qs = params.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [pathname, router, searchParams],
  );

  useEffect(() => {
    fetchDynEntities({ active_only: false })
      .then((rows) => {
        setEntities(rows);
        if (!slug && rows[0]) syncEntityQuery(rows[0].slug);
      })
      .catch(() => setError("Unable to load entities."));
    // eslint-disable-next-line react-hooks/exhaustive-deps -- bootstrap once
  }, []);

  useEffect(() => {
    if (!slug) {
      setDetail(null);
      return;
    }
    let cancelled = false;
    fetchDynEntity(slug)
      .then((row) => {
        if (!cancelled) {
          setDetail(row);
          setError(null);
        }
      })
      .catch(() => {
        if (!cancelled) setError("Unable to load entity schema.");
      });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  const refresh = useCallback(async () => {
    if (!slug) return;
    setDetail(await fetchDynEntity(slug));
  }, [slug]);

  const typeOptions = useMemo(() => {
    const types = new Set((detail?.fields ?? []).map((f) => f.type));
    return [...types].sort((a, b) => a.localeCompare(b));
  }, [detail]);

  const filteredFields = useMemo(() => {
    const q = search.trim().toLowerCase();
    return [...(detail?.fields ?? [])]
      .sort((a, b) => a.field_order - b.field_order)
      .filter((field) => {
        if (typeFilter !== "all" && field.type !== typeFilter) return false;
        if (quickFilter === "required" && !field.is_required) return false;
        if (quickFilter === "in_table" && !field.show_in_table) return false;
        if (quickFilter === "has_rules" && !fieldHasRules(field)) return false;
        if (quickFilter === "system" && !field.is_system_field) return false;
        if (!q) return true;
        return (
          field.label.toLowerCase().includes(q) ||
          field.name.toLowerCase().includes(q)
        );
      });
  }, [detail, search, typeFilter, quickFilter]);

  async function toggleFlag(
    field: DynField,
    key: "is_required" | "show_in_table" | "is_filterable",
    value: boolean,
  ) {
    if (field.is_system_field && key === "is_required") return;
    setTogglingId(field.id);
    setError(null);
    try {
      const isChrome =
        field.name === "id" || field.name === "actions" || field.name === "print";
      const payload: Record<string, unknown> = { [key]: value };
      if (isChrome && key === "show_in_table") {
        const base =
          field.options !== null &&
          typeof field.options === "object" &&
          !Array.isArray(field.options)
            ? { ...(field.options as Record<string, unknown>) }
            : {};
        payload.options_json = { ...base, list_visibility_managed: true };
      }
      await updateDynField(field.id, payload);
      setDetail((current) => {
        if (!current) return current;
        return {
          ...current,
          fields: current.fields.map((f) => {
            if (f.id !== field.id) return f;
            const next: DynField = { ...f, [key]: value };
            if (isChrome && key === "show_in_table") {
              const base =
                f.options !== null &&
                typeof f.options === "object" &&
                !Array.isArray(f.options)
                  ? { ...(f.options as Record<string, unknown>) }
                  : {};
              next.options = { ...base, list_visibility_managed: true };
            }
            return next;
          }),
        };
      });
    } catch {
      setError("Unable to update field.");
    } finally {
      setTogglingId(null);
    }
  }

  const chips: Array<{ id: QuickFilter; label: string }> = [
    { id: "all", label: "All" },
    { id: "required", label: "Required" },
    { id: "in_table", label: "In table" },
    { id: "has_rules", label: "Has rules" },
    { id: "system", label: "System" },
  ];

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesFieldsManage]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">
              <Link href="/dynamic-entities" className="underline-offset-4 hover:underline">
                Dynamic Entities
              </Link>
              {" / Manage Fields"}
            </p>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-foreground">Fields</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Everything a record can store — what it is called, where it sits, and when it appears.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={!slug || !detail}
              onClick={() => setArrangeOpen(true)}
            >
              <LayoutGrid className="size-3.5" />
              Arrange Form
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={!slug}
              onClick={() => {
                if (!slug) return;
                router.push(`/dynamic-entities/field-groups?entity=${encodeURIComponent(slug)}`);
              }}
            >
              Field Groups
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={!slug}
              onClick={() => setSchemaMode({ kind: "add-field" })}
            >
              <Plus className="size-3.5" />
              New Field
            </Button>
          </div>
        </header>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <div className="rounded-xl border border-border bg-card">
          <div className="grid gap-3 border-b border-border p-4 sm:grid-cols-3">
            <label className="space-y-1 text-sm">
              <span className="text-xs font-medium text-muted-foreground">Entity</span>
              <select
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={slug}
                onChange={(e) => syncEntityQuery(e.target.value)}
              >
                {entities.map((e) => (
                  <option key={e.id} value={e.slug}>
                    {e.name}
                    {slug === e.slug && detail ? ` (${detail.fields.length})` : ` (${e.module_pack})`}
                  </option>
                ))}
              </select>
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-xs font-medium text-muted-foreground">Search</span>
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Label or system name"
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="text-xs font-medium text-muted-foreground">Type</span>
              <select
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={typeFilter}
                onChange={(e) => setTypeFilter(e.target.value)}
              >
                <option value="all">All types</option>
                {typeOptions.map((t) => (
                  <option key={t} value={t}>
                    {typeLabel(t)}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <div className="flex flex-wrap gap-1.5 border-b border-border px-4 py-2.5">
            {chips.map((chip) => (
              <button
                key={chip.id}
                type="button"
                onClick={() => setQuickFilter(chip.id)}
                className={cn(
                  "rounded-full px-3 py-1 text-xs font-medium transition-colors",
                  quickFilter === chip.id
                    ? "bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200"
                    : "bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground",
                )}
              >
                {chip.label}
              </button>
            ))}
            <span className="ml-auto self-center text-xs text-muted-foreground">
              {filteredFields.length} field{filteredFields.length === 1 ? "" : "s"}
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="sticky top-0 bg-muted/60 text-left text-xs font-medium text-muted-foreground">
                <tr>
                  <th className="px-4 py-2.5">Field</th>
                  <th className="px-3 py-2.5">Type</th>
                  <th className="px-3 py-2.5 text-center">Required</th>
                  <th className="px-3 py-2.5 text-center">In table</th>
                  <th className="px-3 py-2.5 text-center">Filter</th>
                  <th className="px-3 py-2.5 text-right"> </th>
                </tr>
              </thead>
              <tbody>
                {filteredFields.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-muted-foreground">
                      {detail ? "No fields match these filters." : "Select an entity to manage fields."}
                    </td>
                  </tr>
                ) : (
                  filteredFields.map((field) => {
                    const busy = togglingId === field.id;
                    const system = Boolean(field.is_system_field);
                    const editableSystem = isEditableSystemField(field.name);
                    const listChrome = isListChromeField(field.name);
                    const canToggleTable = !system || listChrome || field.name === "id";
                    const canToggleFilter = !system || field.name === "id";
                    return (
                      <tr
                        key={field.id}
                        className="border-t border-border/70 hover:bg-muted/30"
                      >
                        <td className="px-4 py-2.5">
                          <div className="font-medium text-foreground">{field.label}</div>
                          <div className="font-mono text-[11px] text-muted-foreground">{field.name}</div>
                        </td>
                        <td className="px-3 py-2.5">
                          <span
                            className={cn(
                              "inline-flex rounded-md border px-2 py-0.5 text-[11px] font-medium capitalize",
                              system
                                ? "border-slate-300 bg-slate-50 text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                : "border-border bg-background text-foreground",
                            )}
                          >
                            {system ? "System" : typeLabel(field.type)}
                          </span>
                        </td>
                        <td className="px-3 py-2.5 text-center">
                          <Switch
                            checked={field.is_required}
                            disabled={busy || system}
                            onCheckedChange={(checked) =>
                              void toggleFlag(field, "is_required", checked)
                            }
                            aria-label={`${field.label} required`}
                          />
                        </td>
                        <td className="px-3 py-2.5 text-center">
                          <Switch
                            checked={effectiveShowInTable(field)}
                            disabled={busy || !canToggleTable}
                            onCheckedChange={(checked) =>
                              void toggleFlag(field, "show_in_table", checked)
                            }
                            aria-label={`${field.label} in table`}
                          />
                        </td>
                        <td className="px-3 py-2.5 text-center">
                          <Switch
                            checked={field.is_filterable}
                            disabled={busy || !canToggleFilter}
                            onCheckedChange={(checked) =>
                              void toggleFlag(field, "is_filterable", checked)
                            }
                            aria-label={`${field.label} filterable`}
                          />
                        </td>
                        <td className="px-3 py-2.5 text-right">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            disabled={system && !editableSystem}
                            title={
                              field.name === "workflows"
                                ? "Configure workflow buttons"
                                : field.name === "actions"
                                  ? "Configure list actions column"
                                  : field.name === "id"
                                    ? "Configure ID column"
                                    : field.name === "print"
                                      ? "Configure print column"
                                      : system
                                        ? "System fields are read-only here"
                                        : "Edit field"
                            }
                            onClick={() => setSchemaMode({ kind: "edit-field", field })}
                          >
                            <Pencil className="size-3.5" />
                          </Button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {detail ? (
          <>
            <DynArrangeFormDialog
              open={arrangeOpen}
              onOpenChange={setArrangeOpen}
              entityName={detail.name}
              fields={detail.fields}
              onSaved={refresh}
            />
            <DynSchemaSheets
              entitySlug={slug}
              entityName={detail.name}
              groups={detail.field_groups}
              fields={detail.fields}
              mode={schemaMode}
              onClose={() => setSchemaMode({ kind: "closed" })}
              onSaved={async () => {
                await refresh();
                setSchemaMode({ kind: "closed" });
              }}
            />
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
