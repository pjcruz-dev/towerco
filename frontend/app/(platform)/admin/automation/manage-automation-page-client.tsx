"use client";

import { Fragment, useCallback, useEffect, useMemo, useState } from "react";
import {
  History,
  Pause,
  Pencil,
  Play,
  Plus,
  RefreshCw,
  Trash2,
  X,
} from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage, isApiTimeoutError } from "@/lib/api/error";
import {
  createScheduledTask,
  deleteScheduledTask,
  listScheduledTasks,
  runScheduledTask,
  syncScheduledTasks,
  toggleScheduledTask,
  updateScheduledTask,
  type ScheduledTaskMeta,
  type ScheduledTaskRow,
} from "@/lib/api/modules/automation-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { statusToneClassName, type StatusTone } from "@/lib/ui/status-tone";

type FormState = {
  name: string;
  description: string;
  command_key: string;
  schedule: string;
  cron_expression: string;
};

function emptyForm(meta: ScheduledTaskMeta | null): FormState {
  const first = meta?.commands[0];
  return {
    name: first?.name ?? "",
    description: first?.description ?? "",
    command_key: first?.key ?? "",
    schedule: first?.default_schedule ?? "daily",
    cron_expression: "",
  };
}

function taskTone(status: ScheduledTaskRow["status"] | undefined): StatusTone {
  switch (status) {
    case "failed":
      return "danger";
    case "overdue":
      return "warning";
    case "paused":
      return "neutral";
    default:
      return "success";
  }
}

function taskLabel(row: ScheduledTaskRow): string {
  switch (row.status) {
    case "failed":
      return "Failed";
    case "overdue":
      return "Overdue";
    case "paused":
      return "Paused";
    case "active":
      return "Active";
    default:
      return row.is_active ? "Active" : "Paused";
  }
}

function formatTs(iso: string | null): string {
  if (!iso) return "N/A";
  try {
    return new Date(iso).toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return iso;
  }
}

export function ManageAutomationPageClient() {
  const [rows, setRows] = useState<ScheduledTaskRow[]>([]);
  const [meta, setMeta] = useState<ScheduledTaskMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [editingId, setEditingId] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [historyId, setHistoryId] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm(null));

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listScheduledTasks();
      setRows(data.rows);
      setMeta(data.meta);
    } catch (err) {
      setError(
        isApiTimeoutError(err)
          ? "The cron list took too long because the API is busy. Nothing was saved. Retry."
          : getErrorMessage(err),
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const allSelected = rows.length > 0 && selected.size === rows.length;

  function toggleAll() {
    if (allSelected) {
      setSelected(new Set());
      return;
    }
    setSelected(new Set(rows.map((r) => r.id)));
  }

  function toggleOne(id: string) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm(meta));
    setShowForm(true);
    setNotice(null);
  }

  function openEdit(row: ScheduledTaskRow) {
    setEditingId(row.id);
    setForm({
      name: row.name,
      description: row.description ?? "",
      command_key: row.command_key,
      schedule: row.schedule,
      cron_expression: row.cron_expression,
    });
    setShowForm(true);
    setNotice(null);
  }

  async function onSync() {
    setBusy(true);
    setError(null);
    try {
      const result = await syncScheduledTasks();
      setNotice(`Cron sync complete — ${result.synced} task(s) updated.`);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onRun(row: ScheduledTaskRow) {
    setBusy(true);
    setError(null);
    try {
      await runScheduledTask(row.id);
      setNotice(`Ran “${row.name}”.`);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onToggle(row: ScheduledTaskRow) {
    setBusy(true);
    setError(null);
    try {
      const updated = await toggleScheduledTask(row.id);
      setNotice(updated.is_active ? `Resumed “${row.name}”.` : `Paused “${row.name}”.`);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(row: ScheduledTaskRow) {
    if (!window.confirm(`Delete scheduled task “${row.name}”?`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteScheduledTask(row.id);
      if (editingId === row.id) {
        setShowForm(false);
        setEditingId(null);
      }
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onSubmit() {
    if (!form.name.trim() || !form.command_key) {
      setError("Name and command are required.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      if (editingId) {
        await updateScheduledTask(editingId, {
          name: form.name.trim(),
          description: form.description.trim() || null,
          command_key: form.command_key,
          schedule: form.schedule,
          cron_expression: form.cron_expression.trim() || null,
        });
        setNotice("Task updated.");
      } else {
        await createScheduledTask({
          name: form.name.trim(),
          description: form.description.trim() || null,
          command_key: form.command_key,
          schedule: form.schedule,
          cron_expression: form.cron_expression.trim() || null,
        });
        setNotice("Task created.");
      }
      setShowForm(false);
      setEditingId(null);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const commandOptions = useMemo(() => meta?.commands ?? [], [meta]);

  return (
    <PermissionGate requiredPermissions={[permissions.automationManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          title="Cron jobs"
          description="Jobs for the modules this environment has turned on. Pause and resume apply only in this workspace."
          actions={
            <>
              <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => void onSync()}>
                <RefreshCw className="size-4" />
                Cron Sync
              </Button>
              <Button type="button" size="sm" disabled={busy} onClick={openCreate}>
                <Plus className="size-4" />
                New Task
              </Button>
            </>
          }
        />

        {error ? (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-destructive/40 bg-card px-4 py-3 text-sm text-destructive">
            <p>{error}</p>
            <Button type="button" size="sm" variant="outline" disabled={loading} onClick={() => void load()}>
              Retry
            </Button>
          </div>
        ) : null}
        {notice ? (
          <div className="rounded-xl border border-border bg-card px-4 py-3 text-sm text-foreground">{notice}</div>
        ) : null}

        {showForm ? (
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-base font-medium">{editingId ? "Edit Task" : "New Task"}</h2>
              <Button type="button" size="sm" variant="ghost" onClick={() => setShowForm(false)}>
                <X className="size-3.5" />
                Close
              </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Name</Label>
                <Input
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Description</Label>
                <Textarea
                  rows={2}
                  value={form.description}
                  onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                />
              </div>
              <div className="space-y-1.5">
                <Label>Command</Label>
                <Select
                  className="h-9 w-full"
                  value={form.command_key}
                  onChange={(e) => {
                    const key = e.target.value;
                    const cmd = commandOptions.find((c) => c.key === key);
                    setForm((f) => ({
                      ...f,
                      command_key: key,
                      name: f.name || cmd?.name || "",
                      description: f.description || cmd?.description || "",
                      schedule: f.schedule || cmd?.default_schedule || "daily",
                    }));
                  }}
                >
                  {commandOptions.map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.name} ({c.execution_label})
                    </option>
                  ))}
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Schedule</Label>
                <Select
                  className="h-9 w-full"
                  value={form.schedule}
                  onChange={(e) => setForm((f) => ({ ...f, schedule: e.target.value }))}
                >
                  {(meta?.schedule_presets ?? []).map((p) => (
                    <option key={p.value} value={p.value}>
                      {p.label}
                    </option>
                  ))}
                </Select>
              </div>
              {form.schedule === "custom" ? (
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Cron expression</Label>
                  <Input
                    value={form.cron_expression}
                    onChange={(e) => setForm((f) => ({ ...f, cron_expression: e.target.value }))}
                    placeholder="*/5 * * * *"
                    className="font-mono text-sm"
                  />
                </div>
              ) : null}
            </div>
            <div className="mt-3 flex justify-end">
              <Button type="button" disabled={busy} onClick={() => void onSubmit()}>
                {editingId ? "Save Task" : "Create Task"}
              </Button>
            </div>
          </section>
        ) : null}

        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <Table className="min-w-[900px] text-[13px]">
            <TableHeader>
              <TableRow>
                <TableHead>
                  <Checkbox
                    checked={allSelected}
                    onCheckedChange={() => toggleAll()}
                    aria-label="Select all"
                  />
                </TableHead>
                <TableHead>ID</TableHead>
                <TableHead>Job details</TableHead>
                <TableHead>Schedule</TableHead>
                <TableHead>Execution</TableHead>
                <TableHead>Next run</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-6">
                    <div className="flex flex-col gap-2">
                      <Skeleton className="h-8 w-full" />
                      <Skeleton className="h-8 w-full" />
                      <Skeleton className="h-8 w-2/3" />
                    </div>
                  </TableCell>
                </TableRow>
              ) : rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                    No scheduled tasks yet.
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row) => (
                  <Fragment key={row.id}>
                  <TableRow>
                    <TableCell>
                      <Checkbox
                        checked={selected.has(row.id)}
                        onCheckedChange={() => toggleOne(row.id)}
                        aria-label={`Select ${row.name}`}
                      />
                    </TableCell>
                    <TableCell className="font-medium text-muted-foreground">#{row.number}</TableCell>
                    <TableCell className="whitespace-normal">
                      <div className="font-medium text-foreground">{row.name}</div>
                      <div className="text-[12px] text-muted-foreground">{row.description}</div>
                      {row.last_error ? (
                        <div className="mt-1 text-[12px] text-destructive">{row.last_error}</div>
                      ) : null}
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      <div className="font-mono text-[12px]">{row.cron_expression || row.schedule_display}</div>
                      {meta?.timezone ? (
                        <div className="text-[11px] text-muted-foreground">{meta.timezone}</div>
                      ) : null}
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      <div className="font-mono text-[12px] text-foreground">{row.execution_label}</div>
                      <div className="text-[11px] text-muted-foreground">
                        Last Run: {formatTs(row.last_run_at)}
                        {row.last_status ? ` · ${row.last_status}` : ""}
                      </div>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{formatTs(row.next_run_at)}</TableCell>
                    <TableCell>
                      <span className={statusToneClassName(taskTone(row.status))}>{taskLabel(row)}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="outline"
                          disabled={busy}
                          title="Run history"
                          aria-expanded={historyId === row.id}
                          onClick={() => setHistoryId((current) => (current === row.id ? null : row.id))}
                        >
                          <History className="size-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="outline"
                          disabled={busy}
                          title="Run now"
                          onClick={() => void onRun(row)}
                        >
                          <Play className="size-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="outline"
                          disabled={busy}
                          title="Edit"
                          onClick={() => openEdit(row)}
                        >
                          <Pencil className="size-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="outline"
                          disabled={busy}
                          title={row.is_active ? "Pause" : "Resume"}
                          onClick={() => void onToggle(row)}
                        >
                          <Pause className="size-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="outline"
                          disabled={busy || row.is_system}
                          title="Delete"
                          onClick={() => void onDelete(row)}
                        >
                          <Trash2 className="size-3.5 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                  {historyId === row.id ? (
                    <TableRow>
                      <TableCell colSpan={8} className="bg-muted/30">
                        <p className="text-xs font-medium text-foreground">Last 20 runs</p>
                        {(row.recent_runs ?? []).length === 0 ? (
                          <p className="mt-1 text-xs text-muted-foreground">
                            No runs recorded yet. They appear after the scheduler or Run now.
                          </p>
                        ) : (
                          <ul className="mt-2 flex flex-col gap-1">
                            {(row.recent_runs ?? []).map((run) => (
                              <li key={run.id} className="text-xs text-muted-foreground">
                                <span className="tabular-nums text-foreground">{formatTs(run.ran_at)}</span>
                                {" · "}
                                <span className={run.status === "failed" ? "text-destructive" : undefined}>
                                  {run.status}
                                </span>
                                {run.error ? <span className="text-destructive"> · {run.error}</span> : null}
                              </li>
                            ))}
                          </ul>
                        )}
                      </TableCell>
                    </TableRow>
                  ) : null}
                  </Fragment>
                ))
              )}
            </TableBody>
          </Table>
        </section>
      </div>
    </PermissionGate>
  );
}
