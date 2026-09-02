"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft, Save } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchDynHtmlReport,
  updateDynHtmlReport,
  type DynHtmlReportRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type EditorTab = "html" | "css" | "js";

const SNIPPETS: Array<{ label: string; html: string }> = [
  {
    label: "Form Modal (Create / Edit)",
    html: `<div class="card border-0 shadow-sm mb-4 p-3">
  <h3>Record form</h3>
  <p class="muted">Wire inputs to POST/PATCH /api/v1/integration/dynamic-entities/entities/{slug}/records</p>
</div>`,
  },
  {
    label: "Delete Action Snippet",
    html: `<button type="button" id="btn-delete" class="danger">Delete record</button>`,
  },
  {
    label: "Data Table with Edit/Delete",
    html: `<table class="table">
  <thead><tr><th>Title</th><th>Status</th><th></th></tr></thead>
  <tbody id="rows"></tbody>
</table>`,
  },
];

export function DynHtmlReportEditorPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const id = searchParams.get("id") ?? "";

  const [report, setReport] = useState<DynHtmlReportRow | null>(null);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [description, setDescription] = useState("");
  const [html, setHtml] = useState("");
  const [css, setCss] = useState("");
  const [js, setJs] = useState("");
  const [tab, setTab] = useState<EditorTab>("html");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) {
      setError("Missing report id.");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const row = await fetchDynHtmlReport(id);
      setReport(row);
      setName(row.name);
      setSlug(row.slug);
      setDescription(row.description ?? "");
      setHtml(row.html_source ?? "");
      setCss(row.css_source ?? "");
      setJs(row.js_source ?? "");
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const source = useMemo(() => {
    if (tab === "css") return css;
    if (tab === "js") return js;
    return html;
  }, [tab, html, css, js]);

  function setSource(value: string) {
    if (tab === "css") setCss(value);
    else if (tab === "js") setJs(value);
    else setHtml(value);
  }

  function insertSnippet(snippetHtml: string) {
    setTab("html");
    setHtml((prev) => `${prev.trimEnd()}\n${snippetHtml}\n`);
  }

  async function onSave() {
    if (!id) return;
    setSaving(true);
    setError(null);
    try {
      const saved = await updateDynHtmlReport(id, {
        name: name.trim(),
        slug: slug.trim(),
        description: description.trim() || null,
        html_source: html,
        css_source: css,
        js_source: js,
      });
      setReport(saved);
      setSlug(saved.slug);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.htmlReportsManage]}>
      <div className="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-[1600px] flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <Link
              href="/dynamic-entities/html-reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-4" />
              HTML Reports
            </Link>
            <span className="text-muted-foreground">/</span>
            <h1 className="text-base font-medium text-foreground">
              {report?.name || "Edit report"}
            </h1>
          </div>
          <div className="flex gap-2">
            {report ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => router.push(`/dynamic-entities/html-reports/${report.slug}`)}
              >
                Preview
              </Button>
            ) : null}
            <Button type="button" size="sm" disabled={saving || loading} onClick={() => void onSave()}>
              <Save className="size-4" />
              Save Changes
            </Button>
          </div>
        </div>

        {error ? (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {loading ? (
          <p className="py-12 text-center text-sm text-muted-foreground">Loading editor…</p>
        ) : (
          <div className="grid min-h-0 flex-1 gap-3 lg:grid-cols-[1.6fr_0.9fr]">
            <div className="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border bg-card">
              <div className="flex gap-1 border-b border-border px-2 pt-2">
                {(["html", "css", "js"] as EditorTab[]).map((key) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setTab(key)}
                    className={cn(
                      "rounded-t-md px-3 py-1.5 text-xs font-medium uppercase",
                      tab === key
                        ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                        : "text-muted-foreground hover:bg-muted",
                    )}
                  >
                    {key}
                  </button>
                ))}
              </div>
              <Textarea
                value={source}
                onChange={(e) => setSource(e.target.value)}
                spellCheck={false}
                className="min-h-0 flex-1 resize-none rounded-none border-0 bg-slate-950 font-mono text-[12px] leading-relaxed text-slate-100 focus-visible:ring-0"
              />
            </div>

            <aside className="space-y-4 overflow-y-auto rounded-xl border border-border bg-card p-4">
              <div className="space-y-1.5">
                <Label className="text-xs">Report Name</Label>
                <Input value={name} onChange={(e) => setName(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">Slug (URL Path)</Label>
                <Input value={slug} onChange={(e) => setSlug(e.target.value)} />
                <p className="text-[11px] text-muted-foreground">
                  Opens at <code>/dynamic-entities/html-reports/{slug || "…"}</code>
                </p>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">Description</Label>
                <Textarea
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Internal notes…"
                />
              </div>

              <div className="space-y-2">
                <p className="text-xs font-medium text-foreground">Form &amp; Data Action Snippets</p>
                <ul className="space-y-1">
                  {SNIPPETS.map((s) => (
                    <li key={s.label}>
                      <button
                        type="button"
                        className="w-full rounded-md border border-border px-2 py-1.5 text-left text-xs hover:bg-muted"
                        onClick={() => insertSnippet(s.html)}
                      >
                        {s.label}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-[11px] leading-relaxed text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                <p className="font-medium">Developer notes</p>
                <p className="mt-1">
                  Tokens: <code>{"{{system.company_name}}"}</code>, <code>{"{{current_date}}"}</code>,{" "}
                  <code>{"{{report.name}}"}</code>.
                </p>
                <p className="mt-1">
                  From JS, call TowerOS session APIs or integration keys under{" "}
                  <code>/api/v1/integration/dynamic-entities/…</code>.
                </p>
              </div>

              <Button
                type="button"
                variant="outline"
                className="w-full"
                onClick={() => router.push("/dynamic-entities/html-reports")}
              >
                Close
              </Button>
            </aside>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
