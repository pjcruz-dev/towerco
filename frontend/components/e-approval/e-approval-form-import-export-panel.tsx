"use client";

import { useCallback, useId, useState } from "react";
import { Download, FileJson, Upload } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { downloadEApprovalFormExport, importEApprovalForm } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  documentNumberSettingsFromImportForm,
  documentNumberSettingsToImportForm,
  type EApprovalFormDocumentNumberSettings,
} from "@/modules/e-approval/form-document-number";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";
export type EApprovalFormImportPayload = {
  name: string;
  description: string;
  status: "draft" | "published";
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  metadataJson: string;
  brandLogoUrl: string | null;
  documentNumber: EApprovalFormDocumentNumberSettings;
};

const IMPORT_DRAFT_STORAGE_KEY = "e-approval-form-import-draft";

export function storeImportDraft(payload: EApprovalFormImportPayload): void {
  if (typeof window === "undefined") return;
  sessionStorage.setItem(IMPORT_DRAFT_STORAGE_KEY, JSON.stringify(payload));
}

export function readStoredImportDraft(): EApprovalFormImportPayload | null {
  if (typeof window === "undefined") return null;
  const raw = sessionStorage.getItem(IMPORT_DRAFT_STORAGE_KEY);
  if (!raw) return null;
  sessionStorage.removeItem(IMPORT_DRAFT_STORAGE_KEY);
  try {
    return JSON.parse(raw) as EApprovalFormImportPayload;
  } catch {
    return null;
  }
}

type Props = {
  formId?: string;
  formName: string;
  /** Current editor state — used for draft export and load-into-editor */
  getDraftPayload: () => EApprovalFormImportPayload;
  onLoadIntoEditor: (payload: EApprovalFormImportPayload) => void;
  onImported?: (formId: string) => void;
  /** List page: import-as-new + open in editor (no export) */
  importOnly?: boolean;
  onOpenInEditor?: () => void;
  className?: string;
  /** When true, fill the textarea from the current editor on first paint. */
  seedFromDraft?: boolean;
};

export function parseImportEnvelope(raw: Record<string, unknown>): EApprovalFormImportPayload {
  const inner = (raw.form ?? raw) as Record<string, unknown>;
  const form = (inner.form ?? inner) as Record<string, unknown>;
  const fields = Array.isArray(form.fields) ? (form.fields as EApprovalFormFieldInput[]) : [];
  const steps = Array.isArray(form.steps) ? (form.steps as EApprovalWorkflowStepInput[]) : [];
  const metadata = form.metadata_json;
  const metadataJson =
    metadata && typeof metadata === "object" && Object.keys(metadata as object).length > 0
      ? JSON.stringify(metadata, null, 2)
      : "{}";

  return {
    name: String(form.name ?? ""),
    description: String(form.description ?? ""),
    status: form.status === "published" ? "published" : "draft",
    fields: fields.length > 0 ? fields : [{ type: "text", name: "summary", label: "Summary" }],
    steps,
    metadataJson,
    brandLogoUrl:
      typeof form.brand_logo_url === "string" &&
      !form.brand_logo_url.startsWith("/uploads/") &&
      !form.brand_logo_url.startsWith("uploads/")
        ? form.brand_logo_url
        : null,
    documentNumber: documentNumberSettingsFromImportForm(form),
  };
}

export function buildExportEnvelope(draft: EApprovalFormImportPayload): Record<string, unknown> {
  let metadata_json: Record<string, unknown> | null = null;
  const trimmed = draft.metadataJson.trim();
  if (trimmed && trimmed !== "{}") {
    metadata_json = JSON.parse(trimmed) as Record<string, unknown>;
  }

  return {
    format: "atc-form-export",
    formatVersion: 1,
    exportedAt: new Date().toISOString(),
    form: {
      name: draft.name,
      description: draft.description || null,
      status: draft.status,
      metadata_json,
      brand_logo_url: draft.brandLogoUrl,
      ...documentNumberSettingsToImportForm(draft.documentNumber),
      fields: draft.fields,
      steps: draft.steps,
    },
  };
}

function downloadJson(filename: string, data: unknown) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function validateEnvelope(raw: Record<string, unknown>): string | null {
  if (raw.format !== undefined && raw.format !== "atc-form-export") {
    return 'Unknown format. Expected "atc-form-export".';
  }
  try {
    const payload = parseImportEnvelope(raw);
    if (!payload.name.trim()) {
      return "Form name is required in the JSON.";
    }
    if (!Array.isArray(payload.fields) || payload.fields.length === 0) {
      return "fields must be a non-empty array.";
    }
  } catch (e) {
    return getErrorMessage(e);
  }
  return null;
}

export function EApprovalFormImportExportPanel({
  formId,
  formName,
  getDraftPayload,
  onLoadIntoEditor,
  onImported,
  importOnly = false,
  onOpenInEditor,
  className,
  seedFromDraft = false,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const fileInputId = useId();
  const [importJson, setImportJson] = useState(() => {
    if (!seedFromDraft) {
      return "";
    }
    try {
      return JSON.stringify(buildExportEnvelope(getDraftPayload()), null, 2);
    } catch {
      return "";
    }
  });
  const [busy, setBusy] = useState(false);
  const [parseError, setParseError] = useState<string | null>(null);

  const refreshFromDraft = useCallback(() => {
    try {
      setImportJson(JSON.stringify(buildExportEnvelope(getDraftPayload()), null, 2));
      setParseError(null);
      push({
        level: "success",
        title: "Loaded current form",
        message: "Edit the JSON below, then Apply to canvas.",
      });
    } catch (e) {
      push({ level: "error", title: "Could not serialize form", message: getErrorMessage(e) });
    }
  }, [getDraftPayload, push]);

  const handleExportSaved = async () => {
    if (!formId) return;
    setBusy(true);
    try {
      const blob = await downloadEApprovalFormExport(formId);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${formName || "form"}.export.json`;
      a.click();
      URL.revokeObjectURL(url);
      push({ level: "success", title: "Form exported" });
    } catch (e) {
      push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
    } finally {
      setBusy(false);
    }
  };

  const handleExportDraft = () => {
    try {
      const envelope = buildExportEnvelope(getDraftPayload());
      downloadJson(`${formName || "form-draft"}.export.json`, envelope);
      push({ level: "success", title: "Draft exported" });
    } catch (e) {
      push({ level: "error", title: "Export failed", message: getErrorMessage(e) });
    }
  };

  const handleFilePicked = async (file: File | null) => {
    if (!file) return;
    setBusy(true);
    try {
      const text = await file.text();
      JSON.parse(text);
      setImportJson(text);
      setParseError(null);
      push({ level: "success", title: "JSON file loaded", message: file.name });
    } catch (e) {
      setParseError(getErrorMessage(e));
      push({ level: "error", title: "Invalid JSON file", message: getErrorMessage(e) });
    } finally {
      setBusy(false);
    }
  };

  const handleApplyToEditor = () => {
    try {
      const raw = JSON.parse(importJson) as Record<string, unknown>;
      const error = validateEnvelope(raw);
      if (error) {
        setParseError(error);
        push({ level: "error", title: "Invalid form JSON", message: error });
        return;
      }
      setParseError(null);
      const payload = parseImportEnvelope(raw);
      if (importOnly) {
        storeImportDraft(payload);
        onOpenInEditor?.();
      } else {
        onLoadIntoEditor(payload);
      }
      push({
        level: "success",
        title: "JSON applied to editor",
        message: "Review Design / Workflow, then Save to persist.",
      });
    } catch (e) {
      const message = getErrorMessage(e);
      setParseError(message);
      push({ level: "error", title: "Invalid JSON", message });
    }
  };

  const handleImportAsNew = async () => {
    setBusy(true);
    try {
      const raw = JSON.parse(importJson) as Record<string, unknown>;
      const error = validateEnvelope(raw);
      if (error) {
        setParseError(error);
        push({ level: "error", title: "Invalid form JSON", message: error });
        return;
      }
      const result = await importEApprovalForm(raw);
      const warnings = result.warnings?.filter(Boolean) ?? [];
      push({
        level: "success",
        title: "Form imported as new",
        message: warnings.length > 0 ? warnings.slice(0, 3).join(" · ") : undefined,
      });
      onImported?.(result.id);
    } catch (e) {
      push({ level: "error", title: "Import failed", message: getErrorMessage(e) });
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className={cn("space-y-4", className)}>
      <div>
        <h2 className="flex items-center gap-2 text-base font-medium">
          <FileJson className="h-4 w-4 text-muted-foreground" aria-hidden />
          Form definition JSON
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Safe power-user path: export or paste{" "}
          <code className="rounded bg-muted px-1 text-xs">atc-form-export</code> JSON, edit offline, then use
          Apply to canvas. The visual builder stays the source of truth until you Save. Re-upload logos from
          legacy <code className="rounded bg-muted px-1 text-xs">/uploads/</code> paths after import.
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {!importOnly && formId ? (
          <Button type="button" size="sm" variant="outline" disabled={busy} onClick={handleExportSaved}>
            <Download className="mr-1.5 h-3.5 w-3.5" />
            Download saved form
          </Button>
        ) : null}
        {!importOnly ? (
          <Button type="button" size="sm" variant="outline" disabled={busy} onClick={handleExportDraft}>
            <Download className="mr-1.5 h-3.5 w-3.5" />
            Download editor draft
          </Button>
        ) : null}
        {!importOnly ? (
          <Button type="button" size="sm" variant="outline" disabled={busy} onClick={refreshFromDraft}>
            Load current form into editor
          </Button>
        ) : null}
        <label
          htmlFor={fileInputId}
          className={cn(
            "inline-flex h-8 cursor-pointer items-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-muted",
            busy && "pointer-events-none opacity-50",
          )}
        >
          <Upload className="mr-1.5 h-3.5 w-3.5" />
          Upload .json file
          <input
            id={fileInputId}
            type="file"
            accept="application/json,.json"
            className="sr-only"
            disabled={busy}
            onChange={(e) => {
              void handleFilePicked(e.target.files?.[0] ?? null);
              e.target.value = "";
            }}
          />
        </label>
      </div>

      <div className="space-y-2">
        <Label htmlFor={`${fileInputId}-json`} className="text-xs font-medium text-muted-foreground">
          JSON
        </Label>
        <Textarea
          id={`${fileInputId}-json`}
          className="min-h-[280px] font-mono text-xs"
          placeholder='Paste {"format":"atc-form-export","form":{...}} or click “Load current form into editor”'
          value={importJson}
          onChange={(e) => {
            setImportJson(e.target.value);
            setParseError(null);
          }}
          spellCheck={false}
        />
        {parseError ? <p className="text-xs text-destructive">{parseError}</p> : null}
      </div>

      <div className="flex flex-wrap gap-2 border-t border-border pt-3">
        <Button type="button" size="sm" disabled={busy || !importJson.trim()} onClick={handleApplyToEditor}>
          Apply JSON to canvas
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={busy || !importJson.trim()}
          onClick={handleImportAsNew}
        >
          Import as new form
        </Button>
      </div>
    </section>
  );
}
