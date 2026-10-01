"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, Copy, FileStack, Pencil, Plus, Trash2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { getErrorMessage, isCanceledRequestError } from "@/lib/api/error";
import {
  fetchDynEntities,
  updateDynEntity,
  type DynEntitySummary,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  clonePrintTemplate,
  createEmptyPrintTemplate,
  listPrintTemplates,
  packPrintSettingsJson,
  templateDisplayName,
  type DynPrintTemplate,
} from "@/lib/dynamic-entities/dyn-print-templates";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";

function formatUpdated(iso: string | undefined | null): string {
  if (!iso) return "—";
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

type EntityTemplates = {
  entity: DynEntitySummary;
  templates: DynPrintTemplate[];
  defaultId: string | null;
};

export function DynPrintablesAdminPageClient() {
  const router = useRouter();
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [search, setSearch] = useState("");
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [newOpen, setNewOpen] = useState(false);
  const [newSlug, setNewSlug] = useState("");
  const [newName, setNewName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function refresh() {
    setLoading(true);
    setError(null);
    try {
      const list = await fetchDynEntities({ active_only: true });
      const rows = Array.isArray(list) ? list : [];
      setEntities([...rows].sort((a, b) => a.name.localeCompare(b.name)));
    } catch (err) {
      if (isCanceledRequestError(err)) {
        return;
      }
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      setLoading(true);
      setError(null);
      try {
        const list = await fetchDynEntities({ active_only: true });
        if (cancelled) return;
        const rows = Array.isArray(list) ? list : [];
        setEntities([...rows].sort((a, b) => a.name.localeCompare(b.name)));
      } catch (err) {
        if (cancelled || isCanceledRequestError(err)) return;
        setError(getErrorMessage(err));
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const groups = useMemo<EntityTemplates[]>(() => {
    return entities.map((entity) => {
      const templates = listPrintTemplates(entity.print_settings, entity.name);
      return {
        entity,
        templates,
        defaultId: entity.print_settings?.default_template_id ?? templates[0]?.id ?? null,
      };
    });
  }, [entities]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return groups;
    return groups
      .map((g) => {
        const entityMatch =
          g.entity.name.toLowerCase().includes(q) || g.entity.slug.includes(q);
        const templates = g.templates.filter((t) => {
          const title = templateDisplayName(t).toLowerCase();
          return title.includes(q) || (t.layout ?? "").includes(q);
        });
        if (entityMatch) return g;
        if (templates.length === 0) return null;
        return { ...g, templates };
      })
      .filter(Boolean) as EntityTemplates[];
  }, [groups, search]);

  const allExpanded =
    filtered.length > 0 && filtered.every((g) => expanded[g.entity.slug] !== false);

  function isOpen(slug: string): boolean {
    return expanded[slug] !== false;
  }

  function toggleGroup(slug: string) {
    setExpanded((prev) => ({ ...prev, [slug]: !isOpen(slug) }));
  }

  function selectionKey(slug: string, templateId: string) {
    return `${slug}::${templateId}`;
  }

  async function saveEntityTemplates(
    entity: DynEntitySummary,
    templates: DynPrintTemplate[],
    defaultId?: string | null,
  ) {
    await updateDynEntity(entity.slug, {
      print_settings_json: packPrintSettingsJson(
        templates,
        defaultId ?? entity.print_settings?.default_template_id ?? templates[0]?.id,
      ),
    });
  }

  async function createTemplate() {
    if (!newSlug) return;
    const entity = entities.find((e) => e.slug === newSlug);
    if (!entity) return;
    setBusyKey(newSlug);
    setError(null);
    try {
      const existing = listPrintTemplates(entity.print_settings, entity.name);
      const created = createEmptyPrintTemplate(
        entity.name,
        newName.trim() || `${entity.name} Template`,
      );
      // If entity only had auto-seeded empty default with no custom name history, keep both.
      const next = [...existing, created];
      await saveEntityTemplates(entity, next, created.id);
      setNewOpen(false);
      setNewSlug("");
      setNewName("");
      router.push(
        `/dynamic-entities/printables/edit?entity=${encodeURIComponent(entity.slug)}&template=${encodeURIComponent(created.id)}`,
      );
    } catch {
      setError("Unable to create template.");
    } finally {
      setBusyKey(null);
    }
  }

  async function duplicateTemplate(entity: DynEntitySummary, template: DynPrintTemplate) {
    const key = selectionKey(entity.slug, template.id);
    setBusyKey(key);
    setError(null);
    try {
      const existing = listPrintTemplates(entity.print_settings, entity.name);
      const copy = clonePrintTemplate(template);
      await saveEntityTemplates(entity, [...existing, copy], entity.print_settings?.default_template_id);
      await refresh();
    } catch {
      setError("Unable to duplicate template.");
    } finally {
      setBusyKey(null);
    }
  }

  async function deleteTemplate(entity: DynEntitySummary, template: DynPrintTemplate) {
    if (
      !window.confirm(
        `Delete printable “${templateDisplayName(template)}”? This cannot be undone.`,
      )
    ) {
      return;
    }
    const key = selectionKey(entity.slug, template.id);
    setBusyKey(key);
    setError(null);
    try {
      const existing = listPrintTemplates(entity.print_settings, entity.name);
      const next = existing.filter((t) => t.id !== template.id);
      if (next.length === 0) {
        // Keep one blank default so print still works.
        next.push(createEmptyPrintTemplate(entity.name, "Print"));
      }
      const defaultId =
        entity.print_settings?.default_template_id === template.id
          ? next[0]!.id
          : entity.print_settings?.default_template_id;
      await saveEntityTemplates(entity, next, defaultId);
      await refresh();
      setSelected((prev) => {
        const n = new Set(prev);
        n.delete(key);
        return n;
      });
    } catch {
      setError("Unable to delete template.");
    } finally {
      setBusyKey(null);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.printablesManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Manage Printables"
          description="Multiple templates per entity (e.g. Official Receipt + Disbursement Voucher)."
          actions={
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                render={<Link href="/dynamic-entities/printables/pdf-forms" />}
              >
                <FileStack className="size-3.5" />
                PDF Forms Manager
              </Button>
              <Button type="button" size="sm" onClick={() => setNewOpen(true)}>
                <Plus className="size-3.5" />
                New Template
              </Button>
            </>
          }
        />

        {error ? (
          <div className="flex flex-wrap items-center gap-3">
            <p className="text-sm text-destructive">{error}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void refresh()}>
              Retry
            </Button>
          </div>
        ) : null}

        {newOpen ? (
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <p className="text-sm font-medium text-foreground">New printable template</p>
            <p className="mt-1 text-xs text-muted-foreground">
              Add another template under an entity — existing templates are kept.
            </p>
            <div className="mt-3 flex flex-wrap items-end gap-2">
              <label className="min-w-[14rem] flex-1 space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Entity</span>
                <Select
                  value={newSlug}
                  onChange={(e) => setNewSlug(e.target.value)}
                >
                  <option value="">Select entity…</option>
                  {entities.map((e) => (
                    <option key={e.id} value={e.slug}>
                      {e.name}
                      {listPrintTemplates(e.print_settings, e.name).length > 1
                        ? ` (${listPrintTemplates(e.print_settings, e.name).length})`
                        : ""}
                    </option>
                  ))}
                </Select>
              </label>
              <label className="min-w-[14rem] flex-1 space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Template name</span>
                <Input
                  className="h-9"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="e.g. Official Receipt Template"
                />
              </label>
              <Button
                type="button"
                disabled={!newSlug || busyKey === newSlug}
                onClick={() => void createTemplate()}
              >
                Continue
              </Button>
              <Button type="button" variant="outline" onClick={() => setNewOpen(false)}>
                Cancel
              </Button>
            </div>
          </div>
        ) : null}

        <div className="overflow-hidden rounded-xl border border-border bg-card">
          <div className="flex flex-wrap items-center gap-3 border-b border-border px-4 py-3">
            <Input
              className="h-9 max-w-sm"
              placeholder="Search templates…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="ml-auto"
              onClick={() => {
                const next: Record<string, boolean> = {};
                for (const g of filtered) next[g.entity.slug] = !allExpanded;
                setExpanded(next);
              }}
            >
              {allExpanded ? "Collapse all" : "Expand all"}
            </Button>
          </div>

          {loading ? (
            <p className="px-4 py-10 text-center text-sm text-muted-foreground">Loading…</p>
          ) : filtered.length === 0 ? (
            <p className="px-4 py-10 text-center text-sm text-muted-foreground">No templates match.</p>
          ) : (
            <ul>
              {filtered.map(({ entity, templates }) => {
                const open = isOpen(entity.slug);
                return (
                  <li key={entity.id} className="border-b border-border last:border-b-0">
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => toggleGroup(entity.slug)}
                      className={cn(
                        "h-auto w-full justify-start gap-2 rounded-none px-4 py-2.5 text-left",
                        open ? "bg-muted" : "bg-muted/40",
                      )}
                    >
                      <ChevronDown
                        className={cn(
                          "size-4 text-muted-foreground transition-transform",
                          !open && "-rotate-90",
                        )}
                      />
                      <span className="text-sm font-medium text-foreground">{entity.name}</span>
                      <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                        {templates.length}
                      </span>
                    </Button>
                    {open
                      ? templates.map((template) => {
                          const key = selectionKey(entity.slug, template.id);
                          const busy = busyKey === key;
                          const title = templateDisplayName(template);
                          return (
                            <div
                              key={template.id}
                              className="flex flex-wrap items-center gap-3 border-t border-border/60 px-4 py-3 pl-10"
                            >
                              <Checkbox
                                checked={selected.has(key)}
                                onCheckedChange={(v) => {
                                  setSelected((prev) => {
                                    const n = new Set(prev);
                                    if (v === true) n.add(key);
                                    else n.delete(key);
                                    return n;
                                  });
                                }}
                                aria-label={`Select ${title}`}
                              />
                              <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-foreground">{title}</p>
                                <p className="text-xs text-muted-foreground">
                                  Updated: {formatUpdated(template.updated_at ?? entity.updated_at)} ·
                                  layout {template.layout ?? "grouped"}
                                </p>
                              </div>
                              <div className="flex flex-wrap gap-1">
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 gap-1 text-xs"
                                  disabled={busy}
                                  onClick={() => void duplicateTemplate(entity, template)}
                                >
                                  <Copy className="size-3.5" />
                                  Duplicate
                                </Button>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 gap-1 text-xs"
                                  onClick={() =>
                                    router.push(
                                      `/dynamic-entities/printables/edit?entity=${encodeURIComponent(entity.slug)}&template=${encodeURIComponent(template.id)}`,
                                    )
                                  }
                                >
                                  <Pencil className="size-3.5" />
                                  Edit
                                </Button>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 gap-1 text-xs text-destructive hover:text-destructive"
                                  disabled={busy}
                                  onClick={() => void deleteTemplate(entity, template)}
                                >
                                  <Trash2 className="size-3.5" />
                                  Delete
                                </Button>
                              </div>
                            </div>
                          );
                        })
                      : null}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>
    </PermissionGate>
  );
}
