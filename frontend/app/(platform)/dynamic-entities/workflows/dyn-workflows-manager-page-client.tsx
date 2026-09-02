"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { GitBranch, Pencil, Plus, Trash2, Zap } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteDynWorkflow,
  listDynWorkflows,
  type DynWorkflowRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";

import { NewWorkflowDialog } from "./new-workflow-dialog";

function triggerLabel(mode: string): string {
  if (mode === "on_create") return "Auto on create";
  if (mode === "on_update") return "Auto on update";
  return "Manual button";
}

export function DynWorkflowsManagerPageClient() {
  const router = useRouter();
  const [rows, setRows] = useState<DynWorkflowRow[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [openNew, setOpenNew] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listDynWorkflows();
      setRows(data.rows);
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

  async function onDelete(row: DynWorkflowRow) {
    if (!window.confirm(`Delete workflow “${row.name}”?`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteDynWorkflow(row.id);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.workflowsManage]}>
      <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2 text-muted-foreground">
              <GitBranch className="size-4" />
              <span className="text-xs font-medium">Operations</span>
            </div>
            <h1 className="text-2xl font-semibold text-foreground">Workflow Automation</h1>
            <p className="max-w-2xl text-sm text-muted-foreground">
              Create and manage database-driven transactional steps and status transitions.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search workflows…"
              className="h-9 w-48"
              autoComplete="off"
            />
            <Button type="button" size="sm" onClick={() => setOpenNew(true)}>
              <Plus className="size-4" />
              New Workflow
            </Button>
          </div>
        </header>

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : filtered.length === 0 ? (
          <section className="flex min-h-[420px] flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-16 text-center shadow-sm">
            <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-300">
              <Zap className="size-7" />
            </div>
            <h2 className="text-lg font-medium">No workflows created yet</h2>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
              Workflows allow you to chain actions like field updates, record creations and emails.
            </p>
            <Button type="button" className="mt-6" onClick={() => setOpenNew(true)}>
              Create First Workflow
            </Button>
          </section>
        ) : (
          <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">Name</th>
                    <th className="px-4 py-3">Entity</th>
                    <th className="px-4 py-3">Trigger</th>
                    <th className="px-4 py-3">Status match</th>
                    <th className="px-4 py-3">Active</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((row) => (
                    <tr key={row.id} className="border-t">
                      <td className="px-4 py-3">
                        <div className="font-medium">{row.name}</div>
                        {row.description ? (
                          <div className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                            {row.description}
                          </div>
                        ) : null}
                      </td>
                      <td className="px-4 py-3">
                        <code className="text-xs">{row.entity_slug}</code>
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {triggerLabel(row.trigger_mode)}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {row.status_matches.length
                          ? row.status_matches.join(", ")
                          : "Any"}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={
                            row.is_active
                              ? "text-emerald-600 dark:text-emerald-400"
                              : "text-muted-foreground"
                          }
                        >
                          {row.is_active ? "Yes" : "No"}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-end gap-1">
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={busy}
                            onClick={() =>
                              router.push(`/dynamic-entities/workflows/edit?id=${row.id}`)
                            }
                          >
                            <Pencil className="size-4" />
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="text-red-600"
                            disabled={busy}
                            onClick={() => void onDelete(row)}
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="border-t px-4 py-2 text-xs text-muted-foreground">
              {filtered.length} workflow{filtered.length === 1 ? "" : "s"}
              {" · "}
              <Link href="/dynamic-entities/fields" className="underline-offset-2 hover:underline">
                Per-entity buttons also live under Manage Fields → workflows
              </Link>
            </div>
          </section>
        )}
      </div>

      <NewWorkflowDialog
        open={openNew}
        onOpenChange={setOpenNew}
        onCreated={(id) => {
          setOpenNew(false);
          router.push(`/dynamic-entities/workflows/edit?id=${id}`);
        }}
      />
    </PermissionGate>
  );
}
