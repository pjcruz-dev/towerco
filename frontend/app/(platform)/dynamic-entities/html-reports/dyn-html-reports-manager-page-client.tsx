"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ClipboardPaste,
  Copy,
  Download,
  ExternalLink,
  FileCode2,
  FileJson,
  Pencil,
  Plus,
  Replace,
  Trash2,
} from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuShortcut,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { Input } from "@/components/ui/input";
import { getErrorMessage } from "@/lib/api/error";
import {
  createDynHtmlReport,
  deleteDynHtmlReport,
  duplicateDynHtmlReport,
  fetchDynHtmlReport,
  listDynHtmlReports,
  updateDynHtmlReport,
  type DynHtmlReportRow,
  type DynReportBuilderDef,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type ReportClipboardPayload = {
  name?: string;
  slug?: string;
  description?: string;
  html_source?: string;
  css_source?: string;
  js_source?: string;
  builder_json?: DynReportBuilderDef | null;
};

function toClipboardPayload(full: DynHtmlReportRow): ReportClipboardPayload {
  return {
    name: full.name,
    slug: full.slug,
    description: full.description ?? undefined,
    html_source: full.html_source,
    css_source: full.css_source,
    js_source: full.js_source,
    builder_json: full.builder_json ?? null,
  };
}

function parseReportJson(text: string): ReportClipboardPayload {
  const parsed = JSON.parse(text) as ReportClipboardPayload;
  if (!parsed?.name?.trim()) {
    throw new Error("Report JSON needs a name.");
  }
  return parsed;
}

export function DynHtmlReportsManagerPageClient() {
  const router = useRouter();
  const importInputRef = useRef<HTMLInputElement>(null);
  const [rows, setRows] = useState<DynHtmlReportRow[]>([]);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listDynHtmlReports();
      setRows(data.rows);
      setTotal(data.total);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!toast) return;
    const t = window.setTimeout(() => setToast(null), 2400);
    return () => window.clearTimeout(t);
  }, [toast]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        r.name.toLowerCase().includes(q) ||
        r.slug.toLowerCase().includes(q) ||
        (r.description ?? "").toLowerCase().includes(q),
    );
  }, [rows, search]);

  const selectedRow = useMemo(
    () => rows.find((r) => r.id === selectedId) ?? null,
    [rows, selectedId],
  );

  function editHref(row: DynHtmlReportRow) {
    return row.has_builder
      ? `/dynamic-entities/report-builder?id=${row.id}`
      : `/dynamic-entities/html-reports/edit?id=${row.id}`;
  }

  async function onNew() {
    const name = window.prompt("Report name");
    if (!name?.trim()) return;
    setBusy(true);
    setError(null);
    try {
      const created = await createDynHtmlReport({ name: name.trim() });
      router.push(`/dynamic-entities/html-reports/edit?id=${created.id}`);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function createFromPayload(parsed: ReportClipboardPayload, navigate = true) {
    const created = await createDynHtmlReport({
      name: parsed.name!.trim(),
      slug: parsed.slug,
      description: parsed.description,
      html_source: parsed.html_source,
      css_source: parsed.css_source,
      js_source: parsed.js_source,
      builder_json: parsed.builder_json ?? null,
    });
    await load();
    if (navigate) {
      router.push(editHref(created));
    }
    return created;
  }

  async function readClipboardPayload(): Promise<ReportClipboardPayload> {
    const text = await navigator.clipboard.readText();
    return parseReportJson(text);
  }

  async function onPasteAsNew() {
    setBusy(true);
    setError(null);
    try {
      const parsed = await readClipboardPayload();
      await createFromPayload(parsed);
      setToast("Pasted as new report");
    } catch (err) {
      setError(getErrorMessage(err) || "Clipboard does not contain a valid report JSON.");
    } finally {
      setBusy(false);
    }
  }

  async function onPasteAndReplace(row: DynHtmlReportRow) {
    if (
      !window.confirm(
        `Replace “${row.name}” with the report JSON from the clipboard? Name and slug stay the same; HTML/CSS/JS/builder content will be overwritten.`,
      )
    ) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const parsed = await readClipboardPayload();
      await updateDynHtmlReport(row.id, {
        description: parsed.description ?? null,
        html_source: parsed.html_source ?? "",
        css_source: parsed.css_source ?? "",
        js_source: parsed.js_source ?? "",
        builder_json: parsed.builder_json ?? null,
      });
      await load();
      setToast(`Replaced “${row.name}”`);
    } catch (err) {
      setError(getErrorMessage(err) || "Clipboard does not contain a valid report JSON.");
    } finally {
      setBusy(false);
    }
  }

  async function onCopy(row: DynHtmlReportRow) {
    setBusy(true);
    setError(null);
    try {
      const full = await fetchDynHtmlReport(row.id);
      await navigator.clipboard.writeText(JSON.stringify(toClipboardPayload(full), null, 2));
      setToast(`Copied “${row.name}”`);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onDownloadJson(row: DynHtmlReportRow) {
    setBusy(true);
    setError(null);
    try {
      const full = await fetchDynHtmlReport(row.id);
      const blob = new Blob([JSON.stringify(toClipboardPayload(full), null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${full.slug || full.name || "report"}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      setToast(`Downloaded “${row.name}.json”`);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onImportFile(file: File) {
    setBusy(true);
    setError(null);
    try {
      const text = await file.text();
      const parsed = parseReportJson(text);
      await createFromPayload(parsed);
      setToast(`Imported “${parsed.name}”`);
    } catch (err) {
      setError(getErrorMessage(err) || "Invalid report JSON file.");
    } finally {
      setBusy(false);
      if (importInputRef.current) importInputRef.current.value = "";
    }
  }

  async function onDuplicate(id: string) {
    setBusy(true);
    setError(null);
    try {
      const copy = await duplicateDynHtmlReport(id);
      await load();
      router.push(editHref(copy));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(row: DynHtmlReportRow) {
    if (!window.confirm(`Delete report “${row.name}”?`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteDynHtmlReport(row.id);
      if (selectedId === row.id) setSelectedId(null);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      const target = e.target as HTMLElement | null;
      if (
        target &&
        (target.tagName === "INPUT" ||
          target.tagName === "TEXTAREA" ||
          target.isContentEditable)
      ) {
        return;
      }
      if (!selectedRow || busy) return;

      const mod = e.ctrlKey || e.metaKey;
      if (mod && e.key.toLowerCase() === "c") {
        e.preventDefault();
        void onCopy(selectedRow);
        return;
      }
      if (mod && e.key.toLowerCase() === "v") {
        e.preventDefault();
        void onPasteAsNew();
      }
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- handlers close over latest selectedRow/busy
  }, [selectedRow, busy]);

  return (
    <PermissionGate requiredPermissions={[permissions.htmlReportsManage]}>
      <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2 text-muted-foreground">
              <FileCode2 className="size-4" />
              <span className="text-xs font-medium">System Core</span>
            </div>
            <h1 className="text-2xl font-semibold text-foreground">HTML Reports</h1>
            <p className="max-w-2xl text-sm text-muted-foreground">
              Create and manage dynamic HTML-based reports. Right-click a report to copy, duplicate
              or replace it.
            </p>
            <p className="text-xs text-muted-foreground">{total} reports</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search reports…"
              className="h-9 w-48"
              autoComplete="off"
            />
            <Button
              type="button"
              size="sm"
              variant="outline"
              render={<Link href="/dynamic-entities/report-builder" />}
            >
              Report Builder
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={busy}
              onClick={() => void onPasteAsNew()}
            >
              <ClipboardPaste className="size-4" />
              Paste Report
            </Button>
            <Button type="button" size="sm" disabled={busy} onClick={() => void onNew()}>
              <Plus className="size-4" />
              New Report
            </Button>
          </div>
        </header>

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {toast ? (
          <div className="rounded-xl border border-border bg-muted/40 px-4 py-2 text-sm text-foreground">
            {toast}
          </div>
        ) : null}

        <input
          ref={importInputRef}
          type="file"
          accept="application/json,.json"
          className="hidden"
          onChange={(e) => {
            const file = e.target.files?.[0];
            if (file) void onImportFile(file);
          }}
        />

        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[840px] text-left text-[13px]">
              <thead className="bg-muted/50 text-xs font-medium text-muted-foreground">
                <tr className="border-b border-border">
                  <th className="px-4 py-2.5">Name</th>
                  <th className="px-4 py-2.5">Slug / URL</th>
                  <th className="px-4 py-2.5">Description</th>
                  <th className="px-4 py-2.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-10 text-center text-muted-foreground">
                      Loading reports…
                    </td>
                  </tr>
                ) : filtered.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-10 text-center text-muted-foreground">
                      No reports match.
                    </td>
                  </tr>
                ) : (
                  filtered.map((row) => (
                    <ContextMenu key={row.id}>
                      <ContextMenuTrigger
                        render={
                          <tr
                            className={cn(
                              "border-b border-border last:border-0",
                              selectedId === row.id && "bg-sky-50/50 dark:bg-sky-950/20",
                            )}
                            onClick={() => setSelectedId(row.id)}
                          />
                        }
                      >
                        <td className="px-4 py-3 align-top">
                          <p className="font-medium text-foreground">{row.name}</p>
                          {row.is_system ? (
                            <span className="text-[11px] text-muted-foreground">System seed</span>
                          ) : null}
                        </td>
                        <td className="px-4 py-3 align-top">
                          <div className="flex items-center gap-2">
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]">
                              {row.path}
                            </code>
                            <Link
                              href={`/dynamic-entities/html-reports/${row.slug}`}
                              className="text-sky-700 hover:underline dark:text-sky-400"
                              title="Open report"
                              onClick={(e) => e.stopPropagation()}
                            >
                              <ExternalLink className="size-3.5" />
                            </Link>
                          </div>
                        </td>
                        <td className="px-4 py-3 align-top text-muted-foreground">
                          {row.description?.trim() || "No description"}
                        </td>
                        <td className="px-4 py-3 align-top">
                          <div className="flex justify-end gap-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              disabled={busy}
                              title="Copy JSON"
                              onClick={(e) => {
                                e.stopPropagation();
                                void onCopy(row);
                              }}
                            >
                              <Copy className="size-4" />
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              disabled={busy}
                              title="Edit"
                              onClick={(e) => {
                                e.stopPropagation();
                                router.push(editHref(row));
                              }}
                            >
                              <Pencil className="size-4" />
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              className="text-red-600"
                              disabled={busy}
                              title="Delete"
                              onClick={(e) => {
                                e.stopPropagation();
                                void onDelete(row);
                              }}
                            >
                              <Trash2 className="size-4" />
                            </Button>
                          </div>
                        </td>
                      </ContextMenuTrigger>
                      <ContextMenuContent className="min-w-[17rem]">
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => {
                            setSelectedId(row.id);
                            void onCopy(row);
                          }}
                        >
                          <Copy className="size-3.5 text-muted-foreground" />
                          Copy Report
                          <ContextMenuShortcut>Ctrl+C</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => {
                            setSelectedId(row.id);
                            void onDuplicate(row.id);
                          }}
                        >
                          <Copy className="size-3.5 text-muted-foreground" />
                          Duplicate Here
                        </ContextMenuItem>
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => {
                            setSelectedId(row.id);
                            void onDownloadJson(row);
                          }}
                        >
                          <Download className="size-3.5 text-muted-foreground" />
                          Download as .json
                        </ContextMenuItem>
                        <ContextMenuSeparator />
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => {
                            setSelectedId(row.id);
                            void onPasteAsNew();
                          }}
                        >
                          <ClipboardPaste className="size-3.5 text-muted-foreground" />
                          Paste as New Report
                          <ContextMenuShortcut>Ctrl+V</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => {
                            setSelectedId(row.id);
                            void onPasteAndReplace(row);
                          }}
                        >
                          <Replace className="size-3.5 text-muted-foreground" />
                          <span className="truncate">Paste &amp; Replace &lsquo;{row.name}&rsquo;</span>
                        </ContextMenuItem>
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => importInputRef.current?.click()}
                        >
                          <FileJson className="size-3.5 text-muted-foreground" />
                          Import from .json file…
                        </ContextMenuItem>
                        <ContextMenuSeparator />
                        <ContextMenuItem
                          disabled={busy}
                          onClick={() => router.push(editHref(row))}
                        >
                          <Pencil className="size-3.5 text-muted-foreground" />
                          Edit Report
                        </ContextMenuItem>
                        <ContextMenuItem
                          destructive
                          disabled={busy}
                          onClick={() => void onDelete(row)}
                        >
                          <Trash2 className="size-3.5" />
                          Delete Report
                        </ContextMenuItem>
                      </ContextMenuContent>
                    </ContextMenu>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </PermissionGate>
  );
}
