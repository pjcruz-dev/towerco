"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Pencil, Plus, Trash2, Workflow } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteDynEntityHook,
  listDynEntityHooks,
  toggleDynEntityHook,
  type DynEntityHookRow,
  type DynEntityHookStats,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";

const EVENT_LABELS: Record<string, string> = {
  before_create: "Before create",
  after_create: "After create",
  before_update: "Before update",
  after_update: "After update",
  before_delete: "Before delete",
  after_delete: "After delete",
  before_action: "Before action",
};

function eventChip(event: string): string {
  return EVENT_LABELS[event] ?? event.replace(/_/g, " ");
}

export function DynEntityHooksManagerPageClient() {
  const router = useRouter();
  const [rows, setRows] = useState<DynEntityHookRow[]>([]);
  const [stats, setStats] = useState<DynEntityHookStats | null>(null);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listDynEntityHooks();
      setRows(data.rows);
      setStats(data.stats);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        r.name.toLowerCase().includes(q) ||
        r.entity_slug.toLowerCase().includes(q) ||
        (r.description ?? "").toLowerCase().includes(q),
    );
  }, [rows, search]);

  const grouped = useMemo(() => {
    const map = new Map<string, DynEntityHookRow[]>();
    for (const row of filtered) {
      const list = map.get(row.entity_slug) ?? [];
      list.push(row);
      map.set(row.entity_slug, list);
    }
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
  }, [filtered]);

  async function onToggle(row: DynEntityHookRow) {
    setBusy(true);
    setError(null);
    try {
      await toggleDynEntityHook(row.id);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(row: DynEntityHookRow) {
    if (!window.confirm(`Delete hook “${row.name}”?`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteDynEntityHook(row.id);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const summaryEvents = [
    "before_create",
    "after_create",
    "before_update",
    "after_update",
    "before_delete",
  ] as const;

  return (
    <PermissionGate requiredPermissions={[permissions.entityHooksManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Entity Hooks"
          description="Declarative lifecycle hooks for Dynamic Entities (safe DSL — no PHP). Use for mirroring columns, setting defaults, and reacting before or after saves."
          actions={
            <>
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search hooks…"
                className="h-9 w-48"
                autoComplete="off"
              />
              <Button
                type="button"
                size="sm"
                onClick={() => router.push("/dynamic-entities/hooks/edit")}
              >
                <Plus className="size-4" />
                Create Hook
              </Button>
            </>
          }
        />

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {stats ? (
          <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div className="rounded-xl border bg-card px-4 py-3 shadow-sm sm:col-span-1">
              <div className="text-xs font-medium text-muted-foreground">Total</div>
              <div className="mt-1 text-2xl font-semibold tabular-nums">{stats.total}</div>
              <div className="mt-1 text-xs text-muted-foreground">
                {stats.active} active · {stats.inactive} inactive
              </div>
            </div>
            {summaryEvents.map((event) => {
              const bucket = stats.by_event[event] ?? { active: 0, inactive: 0 };
              return (
                <div key={event} className="rounded-xl border bg-card px-4 py-3 shadow-sm">
                  <div className="text-xs font-medium text-muted-foreground">{eventChip(event)}</div>
                  <div className="mt-1 text-lg font-semibold tabular-nums">{bucket.active}</div>
                  <div className="mt-1 text-xs text-muted-foreground">{bucket.inactive} inactive</div>
                </div>
              );
            })}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : grouped.length === 0 ? (
          <section className="flex min-h-[420px] flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-16 text-center shadow-sm">
            <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-300">
              <Workflow className="size-7" />
            </div>
            <h2 className="text-lg font-medium">No entity hooks yet</h2>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
              Hooks run declarative rules when records are created, updated, or deleted.
            </p>
            <Button
              type="button"
              className="mt-6"
              onClick={() => router.push("/dynamic-entities/hooks/edit")}
            >
              Create First Hook
            </Button>
          </section>
        ) : (
          <div className="space-y-4">
            {grouped.map(([entitySlug, hooks]) => (
              <section
                key={entitySlug}
                className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
              >
                <div className="border-b bg-muted/40 px-4 py-3">
                  <h2 className="text-sm font-medium">{entitySlug}</h2>
                  <p className="text-xs text-muted-foreground">
                    {hooks.length} hook{hooks.length === 1 ? "" : "s"}
                  </p>
                </div>
                <ul className="divide-y">
                  {hooks.map((row) => (
                    <li
                      key={row.id}
                      className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 space-y-1">
                        <div className="font-medium">{row.name}</div>
                        {row.description ? (
                          <p className="text-xs text-muted-foreground">{row.description}</p>
                        ) : null}
                        <div className="flex flex-wrap gap-1.5">
                          {row.events.map((event) => (
                            <span
                              key={event}
                              className="rounded-md border bg-background px-2 py-0.5 text-[11px] text-muted-foreground"
                            >
                              {eventChip(event)}
                            </span>
                          ))}
                        </div>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <Switch
                          checked={row.is_active}
                          disabled={busy}
                          onCheckedChange={() => void onToggle(row)}
                          aria-label={row.is_active ? "Deactivate hook" : "Activate hook"}
                        />
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          render={<Link href={`/dynamic-entities/hooks/edit?id=${row.id}`} />}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          disabled={busy}
                          onClick={() => void onDelete(row)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            ))}
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
