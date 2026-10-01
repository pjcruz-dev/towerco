"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, Eye, FileText, Pencil, Trash2, Upload } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteDynPdfForm,
  downloadDynPdfFormBlob,
  listDynPdfForms,
  renameDynPdfForm,
  uploadDynPdfForm,
  type DynPdfFormRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";

export function DynPdfFormsManagerPageClient() {
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const [search, setSearch] = useState("");
  const [forms, setForms] = useState<DynPdfFormRow[]>([]);
  const [total, setTotal] = useState(0);
  const [available, setAvailable] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [uploadCode, setUploadCode] = useState("");
  const [renamingId, setRenamingId] = useState<string | null>(null);
  const [renameValue, setRenameValue] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await listDynPdfForms();
      setForms(result.forms);
      setTotal(result.total);
      setAvailable(result.available);
    } catch (e) {
      setError(getErrorMessage(e) || "Unable to load PDF forms.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return forms;
    return forms.filter(
      (f) =>
        f.code.toLowerCase().includes(q) ||
        f.name.toLowerCase().includes(q) ||
        f.path.toLowerCase().includes(q) ||
        f.file_name.toLowerCase().includes(q),
    );
  }, [forms, search]);

  async function onUploadSelected(fileList: FileList | null) {
    const file = fileList?.[0];
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      await uploadDynPdfForm({
        file,
        code: uploadCode.trim() || undefined,
        name: undefined,
      });
      setUploadCode("");
      if (fileInputRef.current) fileInputRef.current.value = "";
      await load();
    } catch (e) {
      setError(getErrorMessage(e) || "Upload failed.");
    } finally {
      setBusy(false);
    }
  }

  async function onPreview(form: DynPdfFormRow) {
    if (form.source === "bundled" && form.preview_url) {
      window.open(form.preview_url, "_blank", "noopener,noreferrer");
      return;
    }
    if (!form.id) return;
    setBusy(true);
    setError(null);
    try {
      const blob = await downloadDynPdfFormBlob(form.id);
      const url = URL.createObjectURL(blob);
      window.open(url, "_blank", "noopener,noreferrer");
      window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
      setError(getErrorMessage(e) || "Unable to preview PDF.");
    } finally {
      setBusy(false);
    }
  }

  async function onRename(form: DynPdfFormRow) {
    if (!form.id || !renameValue.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await renameDynPdfForm(form.id, { name: renameValue.trim() });
      setRenamingId(null);
      setRenameValue("");
      await load();
    } catch (e) {
      setError(getErrorMessage(e) || "Rename failed.");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(form: DynPdfFormRow) {
    if (!form.id || !form.can_delete) return;
    if (!window.confirm(`Delete uploaded PDF “${form.code}.pdf”? This cannot be undone.`)) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await deleteDynPdfForm(form.id);
      await load();
    } catch (e) {
      setError(getErrorMessage(e) || "Delete failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.printablesManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="PDF Forms Manager"
          description="Upload, rename, or delete PDF form templates used for overlays."
          actions={
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                render={<Link href="/dynamic-entities/printables" />}
              >
                <ArrowLeft className="size-3.5" />
                Printables
              </Button>
              <Input
                className="h-9 w-36"
                placeholder="Catalog code"
                value={uploadCode}
                disabled={busy}
                onChange={(e) => setUploadCode(e.target.value)}
              />
              <input
                ref={fileInputRef}
                type="file"
                accept="application/pdf,.pdf"
                className="hidden"
                onChange={(e) => void onUploadSelected(e.target.files)}
              />
              <Button
                type="button"
                size="sm"
                disabled={busy}
                onClick={() => fileInputRef.current?.click()}
              >
                <Upload className="size-3.5" />
                Upload PDF
              </Button>
            </>
          }
        />

        <div className="flex flex-wrap items-center gap-3">
          <Input
            className="h-9 max-w-sm"
            placeholder="Search filenames…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <div className="rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-muted-foreground">
            TOTAL FORMS: {total}
            <span className="mx-2 text-border">·</span>
            Available: {available}
          </div>
        </div>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {loading ? <p className="text-sm text-muted-foreground">Loading PDF forms…</p> : null}

        {!loading ? (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <ul>
              {filtered.map((form) => (
                <li
                  key={form.id ?? form.code}
                  className="flex flex-wrap items-center gap-3 border-b border-border px-4 py-3 last:border-b-0"
                >
                  <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-red-600 text-[10px] font-bold text-white">
                    PDF
                  </span>
                  <div className="min-w-0 flex-1">
                    {renamingId != null && renamingId === form.id ? (
                      <div className="flex flex-wrap items-center gap-2">
                        <Input
                          className="h-8 max-w-sm"
                          value={renameValue}
                          autoFocus
                          autoComplete="off"
                          name="pdf-form-rename"
                          onChange={(e) => setRenameValue(e.target.value)}
                          onKeyDown={(e) => {
                            if (e.key === "Enter") void onRename(form);
                            if (e.key === "Escape") {
                              setRenamingId(null);
                              setRenameValue("");
                            }
                          }}
                        />
                        <Button
                          type="button"
                          size="sm"
                          className="h-8"
                          disabled={busy || !renameValue.trim()}
                          onClick={() => void onRename(form)}
                        >
                          Save
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          className="h-8"
                          onClick={() => {
                            setRenamingId(null);
                            setRenameValue("");
                          }}
                        >
                          Cancel
                        </Button>
                      </div>
                    ) : (
                      <>
                        <p className="text-sm font-medium text-foreground">
                          {form.code}.pdf
                          {form.source === "missing" ? (
                            <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                              Not uploaded
                            </span>
                          ) : form.source === "bundled" ? (
                            <span className="ml-2 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                              Bundled
                            </span>
                          ) : (
                            <span className="ml-2 rounded-full bg-sky-500/15 px-2 py-0.5 text-[10px] font-medium text-sky-700 dark:text-sky-400">
                              Uploaded
                            </span>
                          )}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                          {form.name} · Path: {form.path}
                        </p>
                      </>
                    )}
                  </div>
                  <span className="text-xs text-muted-foreground">{form.size_label}</span>
                  <div className="flex gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-8 text-xs"
                      disabled={busy || !form.available}
                      onClick={() => void onPreview(form)}
                    >
                      <Eye className="size-3.5" />
                      Preview
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-8 text-xs"
                      disabled={busy || !form.can_rename}
                      onClick={() => {
                        if (!form.id) return;
                        setRenamingId(form.id);
                        setRenameValue(form.name);
                      }}
                    >
                      <Pencil className="size-3.5" />
                      Rename
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-8 text-xs"
                      disabled={busy || !form.can_delete}
                      onClick={() => void onDelete(form)}
                    >
                      <Trash2 className="size-3.5" />
                      Delete
                    </Button>
                  </div>
                </li>
              ))}
              {filtered.length === 0 ? (
                <li className="px-4 py-8 text-center text-sm text-muted-foreground">
                  No PDF forms match your search.
                </li>
              ) : null}
            </ul>
          </div>
        ) : null}

        <p className="flex items-start gap-2 text-xs text-muted-foreground">
          <FileText className="mt-0.5 size-3.5 shrink-0" />
          BIR 2307 ships bundled with TowerOS. Upload other BIR PDFs (optionally set catalog code like{" "}
          <span className="font-medium">0217</span>) to attach them for overlays. Re-uploading the same
          code replaces the previous file.
        </p>
      </div>
    </PermissionGate>
  );
}
