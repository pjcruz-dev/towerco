"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Mail, Pencil, Plus, Trash2, X } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  createDynEmailTemplate,
  deleteDynEmailTemplate,
  fetchDynEmailTemplate,
  listDynEmailTemplates,
  updateDynEmailTemplate,
  type DynEmailTemplateRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type FormState = {
  name: string;
  subject: string;
  body_html: string;
  default_to: string;
};

const emptyForm = (): FormState => ({
  name: "",
  subject: "",
  body_html: "",
  default_to: "{self.email}",
});

function formatUpdated(iso: string | null): string {
  if (!iso) return "—";
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

export function DynEmailTemplatesPageClient() {
  const [rows, setRows] = useState<DynEmailTemplateRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listDynEmailTemplates();
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
        r.subject.toLowerCase().includes(q) ||
        r.slug.toLowerCase().includes(q),
    );
  }, [rows, search]);

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm());
    setNotice(null);
  }

  async function onEdit(row: DynEmailTemplateRow) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const full = await fetchDynEmailTemplate(row.id);
      setEditingId(full.id);
      setForm({
        name: full.name,
        subject: full.subject,
        body_html: full.body_html ?? "",
        default_to: full.default_to ?? "",
      });
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onSubmit() {
    const name = form.name.trim();
    const subject = form.subject.trim();
    if (!name || !subject) {
      setError("Template name and email subject are required.");
      return;
    }
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      if (editingId) {
        await updateDynEmailTemplate(editingId, {
          name,
          subject,
          body_html: form.body_html,
          default_to: form.default_to.trim() || null,
        });
        setNotice("Template updated.");
      } else {
        await createDynEmailTemplate({
          name,
          subject,
          body_html: form.body_html,
          default_to: form.default_to.trim() || null,
        });
        setNotice("Template created.");
      }
      resetForm();
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(row: DynEmailTemplateRow) {
    if (!window.confirm(`Delete email template “${row.name}”?`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteDynEmailTemplate(row.id);
      if (editingId === row.id) resetForm();
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.emailTemplatesManage]}>
      <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
        <header className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground">
            <Mail className="size-4" />
            <span className="text-xs font-medium">System Core</span>
          </div>
          <h1 className="text-2xl font-semibold text-foreground">Manage Email Templates</h1>
          <p className="max-w-2xl text-sm text-muted-foreground">
            Reusable subject and body templates for workflow notifications. Reference a template from a
            workflow email step with <code className="text-xs">template_slug</code>.
          </p>
        </header>

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}
        {notice ? (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {notice}
          </div>
        ) : null}

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.9fr)]">
          <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
              <h2 className="text-base font-medium text-foreground">Existing Templates</h2>
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search…"
                className="h-8 w-40"
                autoComplete="off"
              />
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[520px] text-left text-[13px]">
                <thead className="sticky top-0 bg-muted/40 text-xs font-medium text-muted-foreground">
                  <tr className="border-b border-border">
                    <th className="px-4 py-2.5">Name</th>
                    <th className="px-4 py-2.5">Subject</th>
                    <th className="px-4 py-2.5 whitespace-nowrap">Last updated</th>
                    <th className="px-4 py-2.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan={4} className="px-4 py-10 text-center text-muted-foreground">
                        Loading…
                      </td>
                    </tr>
                  ) : filtered.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="px-4 py-10 text-center text-muted-foreground">
                        No templates found.
                      </td>
                    </tr>
                  ) : (
                    filtered.map((row) => (
                      <tr
                        key={row.id}
                        className={cn(
                          "border-b border-border/80 transition-colors",
                          editingId === row.id ? "bg-muted/50" : "hover:bg-muted/30",
                        )}
                      >
                        <td className="px-4 py-3 font-medium text-foreground">
                          <div>{row.name}</div>
                          <div className="text-[11px] font-normal text-muted-foreground">{row.slug}</div>
                        </td>
                        <td className="max-w-[220px] truncate px-4 py-3 text-muted-foreground">
                          {row.subject}
                        </td>
                        <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">
                          {formatUpdated(row.updated_at)}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex justify-end gap-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy}
                              onClick={() => void onEdit(row)}
                            >
                              <Pencil className="size-3.5" />
                              Edit
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy || row.is_system}
                              onClick={() => void onDelete(row)}
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </section>

          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-base font-medium text-foreground">
                {editingId ? "Edit Template" : "Create New Template"}
              </h2>
              {editingId ? (
                <Button type="button" size="sm" variant="ghost" disabled={busy} onClick={resetForm}>
                  <X className="size-3.5" />
                  Cancel
                </Button>
              ) : null}
            </div>

            <div className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="et-name">Template Name</Label>
                <Input
                  id="et-name"
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  placeholder="e.g., Applicant Accepted Email"
                  autoComplete="off"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="et-subject">Email Subject</Label>
                <Input
                  id="et-subject"
                  value={form.subject}
                  onChange={(e) => setForm((f) => ({ ...f, subject: e.target.value }))}
                  placeholder="e.g., Congratulations! You have been accepted"
                  autoComplete="off"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="et-to">Default To (optional)</Label>
                <Input
                  id="et-to"
                  value={form.default_to}
                  onChange={(e) => setForm((f) => ({ ...f, default_to: e.target.value }))}
                  placeholder="{self.email} or {this.email}"
                  autoComplete="off"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="et-body">Email Body (HTML supported)</Label>
                <Textarea
                  id="et-body"
                  value={form.body_html}
                  onChange={(e) => setForm((f) => ({ ...f, body_html: e.target.value }))}
                  rows={10}
                  placeholder="Use placeholders like {this.first_name}, {vendor.email}, etc."
                  className="min-h-[180px] font-mono text-[13px]"
                />
              </div>
              <div className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                <p className="mb-1 font-medium text-foreground">Placeholders:</p>
                <ul className="list-inside list-disc space-y-0.5">
                  <li>
                    <code>{"{this.field_name}"}</code> — current record (also{" "}
                    <code>{"{self.field}"}</code> except <code>{"{self.email}"}</code> = acting user)
                  </li>
                  <li>
                    <code>{"{alias.field_name}"}</code> — related record from workflow GET loads
                  </li>
                  <li>
                    <code>{"{actor.email}"}</code> / <code>{"{{system.today}}"}</code>
                  </li>
                </ul>
              </div>
              <Button type="button" className="w-full" disabled={busy} onClick={() => void onSubmit()}>
                {editingId ? (
                  <>
                    <Pencil className="size-4" />
                    {busy ? "Saving…" : "Save Template"}
                  </>
                ) : (
                  <>
                    <Plus className="size-4" />
                    {busy ? "Creating…" : "Create Template"}
                  </>
                )}
              </Button>
            </div>
          </section>
        </div>
      </div>
    </PermissionGate>
  );
}
