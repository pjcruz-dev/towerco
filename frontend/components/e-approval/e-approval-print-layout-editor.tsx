"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { EApprovalDocumentDesignEditor } from "@/components/e-approval/e-approval-document-design-editor";
import { EApprovalAuthenticatedImage } from "@/components/e-approval/e-approval-authenticated-image";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  deleteEApprovalFormSubsidiaryLogo,
  fetchEApprovalPdfLayout,
  registerEApprovalFormSubsidiaryCode,
  removeEApprovalFormSubsidiaryCode,
  updateEApprovalPdfLayout,
  uploadEApprovalFormSubsidiaryLogo,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { printableDesignFields } from "@/lib/e-approval/e-approval-print-template-render";
import { isPurchaseOrderPrintTemplate } from "@/modules/e-approval/purchase-order-template";
import { isPurchaseRequisitionPrintTemplate } from "@/modules/e-approval/purchase-requisition-template";
import {
  EAPPROVAL_DEFAULT_SUBSIDIARY_CODES,
  normalizeSubsidiaryCode,
  type EApprovalPrintTemplate,
} from "@/modules/e-approval/print-template-types";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  formId: string;
  fields: EApprovalFormFieldInput[];
  formTitle?: string;
};

function asTemplate(raw: Record<string, unknown> | undefined | null): EApprovalPrintTemplate {
  return (raw ?? {}) as EApprovalPrintTemplate;
}

function resolveSubsidiaryCodes(
  template: EApprovalPrintTemplate,
  layoutPersisted: boolean,
): string[] {
  const fromLogos = Object.keys(template.subsidiary_logos ?? {})
    .map((c) => normalizeSubsidiaryCode(c))
    .filter((c): c is string => c !== null);

  if (Array.isArray(template.subsidiary_codes)) {
    const fromTemplate = template.subsidiary_codes
      .map((c) => normalizeSubsidiaryCode(String(c)))
      .filter((c): c is string => c !== null);
    const merged = [...new Set([...fromTemplate, ...fromLogos])];
    if (merged.length > 0 || layoutPersisted) {
      return merged;
    }
  } else if (fromLogos.length > 0) {
    return fromLogos;
  }

  return [...EAPPROVAL_DEFAULT_SUBSIDIARY_CODES];
}

export function EApprovalPrintLayoutEditor({ formId, fields, formTitle }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [template, setTemplate] = useState<EApprovalPrintTemplate>({});
  const [newCode, setNewCode] = useState("");
  const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const layoutQuery = useQuery({
    queryKey: ["e-approval", "pdf-layout", formId],
    queryFn: () => fetchEApprovalPdfLayout(formId),
  });

  useEffect(() => {
    if (layoutQuery.data?.template) {
      setTemplate(asTemplate(layoutQuery.data.template));
    }
  }, [layoutQuery.data]);

  const designFields = useMemo(
    () => fields.map((f) => ({ name: f.name, label: f.label, type: f.type })),
    [fields],
  );

  const fieldTokens = useMemo(
    () =>
      printableDesignFields(designFields).map((field) => ({
        token: `{{field.${field.name}}}`,
        label: field.label || field.name,
      })),
    [designFields],
  );

  const allVisibleLayout = useMemo(
    () =>
      fields.map((f) => ({
        key: f.name,
        label: f.label,
        visible: true,
        fieldType: f.type,
      })),
    [fields],
  );

  const subsidiaryCodes = useMemo(
    () => resolveSubsidiaryCodes(template, Boolean(layoutQuery.data?.layout_persisted)),
    [template, layoutQuery.data?.layout_persisted],
  );

  const subsidiaryLogos = useMemo(() => {
    const map: Record<string, string> = {};
    for (const [code, url] of Object.entries(template.subsidiary_logos ?? {})) {
      const normalized = normalizeSubsidiaryCode(code);
      if (normalized && url) map[normalized] = url;
    }
    return map;
  }, [template.subsidiary_logos]);

  const applySubsidiaryResult = (result: {
    subsidiary_codes?: string[];
    subsidiary_logos?: Record<string, string>;
  }) => {
    setTemplate((prev) => ({
      ...prev,
      subsidiary_logo_field: prev.subsidiary_logo_field ?? "subsidiary",
      subsidiary_codes: result.subsidiary_codes ?? prev.subsidiary_codes,
      subsidiary_logos: result.subsidiary_logos ?? prev.subsidiary_logos,
    }));
  };

  const saveMutation = useMutation({
    mutationFn: () => {
      if (allVisibleLayout.length === 0) {
        throw new Error("Add at least one form field before saving print design.");
      }
      return updateEApprovalPdfLayout(formId, {
        layout: allVisibleLayout,
        template: {
          ...template,
          subsidiary_logo_field: template.subsidiary_logo_field ?? "subsidiary",
          subsidiary_codes: subsidiaryCodes,
          subsidiary_logos: subsidiaryLogos,
        },
        active_preset_id: layoutQuery.data?.active_preset_id ?? "default",
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: "Print design saved" });
    },
    onError: (e) => push({ level: "error", title: "Save failed", message: getErrorMessage(e) }),
  });

  const uploadLogoMutation = useMutation({
    mutationFn: ({ code, file }: { code: string; file: File }) =>
      uploadEApprovalFormSubsidiaryLogo(formId, code, file),
    onSuccess: (result) => {
      applySubsidiaryResult({
        subsidiary_codes: result.subsidiary_codes,
        subsidiary_logos: {
          ...(result.subsidiary_logos ?? {}),
          [result.code]: result.logo_url,
        },
      });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: `${result.code} logo uploaded` });
    },
    onError: (e) => push({ level: "error", title: "Logo upload failed", message: getErrorMessage(e) }),
  });

  const clearLogoMutation = useMutation({
    mutationFn: (code: string) => deleteEApprovalFormSubsidiaryLogo(formId, code),
    onSuccess: (result) => {
      applySubsidiaryResult(result);
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: `${result.code} logo cleared` });
    },
    onError: (e) => push({ level: "error", title: "Could not clear logo", message: getErrorMessage(e) }),
  });

  const addCodeMutation = useMutation({
    mutationFn: (code: string) => registerEApprovalFormSubsidiaryCode(formId, code),
    onSuccess: (result) => {
      applySubsidiaryResult(result);
      setNewCode("");
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: `${result.code} added` });
    },
    onError: (e) => push({ level: "error", title: "Could not add subsidiary", message: getErrorMessage(e) }),
  });

  const removeCodeMutation = useMutation({
    mutationFn: (code: string) => removeEApprovalFormSubsidiaryCode(formId, code),
    onSuccess: (result) => {
      applySubsidiaryResult(result);
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: `${result.code} removed` });
    },
    onError: (e) => push({ level: "error", title: "Could not remove subsidiary", message: getErrorMessage(e) }),
  });

  const isProcurementTemplate =
    isPurchaseOrderPrintTemplate(template as Record<string, unknown>) ||
    isPurchaseRequisitionPrintTemplate(template as Record<string, unknown>) ||
    fields.some((field) => field.name === "grand_total");

  const canSave = allVisibleLayout.length > 0;
  const logosBusy =
    uploadLogoMutation.isPending ||
    clearLogoMutation.isPending ||
    addCodeMutation.isPending ||
    removeCodeMutation.isPending;

  const patchFooter = (patch: Partial<NonNullable<EApprovalPrintTemplate["footer"]>>) => {
    setTemplate((prev) => ({
      ...prev,
      footer: {
        showPageNumbers: true,
        showApprovalHistory: true,
        showRequestorSignature: false,
        appendAttachments: true,
        ...prev.footer,
        ...patch,
      },
    }));
  };

  const onPickLogo = (code: string, file: File | undefined) => {
    if (!file) return;
    uploadLogoMutation.mutate({ code, file });
  };

  const onAddCode = () => {
    const code = normalizeSubsidiaryCode(newCode);
    if (!code) {
      push({
        level: "error",
        title: "Invalid code",
        message: "Use 1–24 characters: letters, numbers, _ or -.",
      });
      return;
    }
    if (subsidiaryCodes.includes(code)) {
      push({ level: "error", title: "Already added", message: `${code} is already in the list.` });
      return;
    }
    addCodeMutation.mutate(code);
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-base font-medium text-foreground">Print design</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Form-style printable for every form. Document design is the body only. Approval signatures come from
            this submission’s workflow (Approval history on print). Attachment merge stays available by default.
          </p>
          {isProcurementTemplate ? (
            <p className="mt-2 text-xs text-muted-foreground">
              This form also has a structured PO/PR layout. If you save a Document design, that custom HTML is used
              when printing instead of the default procurement template.
            </p>
          ) : null}
        </div>
        <Button
          type="button"
          size="sm"
          onClick={() => saveMutation.mutate()}
          disabled={saveMutation.isPending || !canSave}
        >
          {saveMutation.isPending ? "Saving…" : "Save print design"}
        </Button>
      </div>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h3 className="text-sm font-medium text-foreground">Print options</h3>
          <p className="mt-1 text-xs text-muted-foreground">
            Applies to browser print and merged PDF export for this form.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="ea-print-page-size">Page size</Label>
            <Select
              id="ea-print-page-size"
              className="h-9"
              value={template.page?.size ?? "A4"}
              onChange={(e) =>
                setTemplate((prev) => ({
                  ...prev,
                  page: { ...prev.page, size: e.target.value, marginMm: prev.page?.marginMm ?? 12 },
                }))
              }
            >
              <option value="A4">A4</option>
              <option value="Letter">Letter</option>
              <option value="Legal">Legal</option>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="ea-print-margin">Margin (mm)</Label>
            <Input
              id="ea-print-margin"
              type="number"
              min={0}
              max={40}
              value={String(template.page?.marginMm ?? 12)}
              onChange={(e) =>
                setTemplate((prev) => ({
                  ...prev,
                  page: {
                    size: prev.page?.size ?? "A4",
                    marginMm: Number(e.target.value) || 0,
                  },
                }))
              }
            />
          </div>
        </div>

        <div className="space-y-3">
          <label className="flex items-center gap-3 text-sm text-foreground">
            <Checkbox
              checked={template.footer?.showApprovalHistory !== false}
              onCheckedChange={(v) => patchFooter({ showApprovalHistory: v === true })}
            />
            Show approval history signatures on print
          </label>
          <label className="flex items-center gap-3 text-sm text-foreground">
            <Checkbox
              checked={template.footer?.showRequestorSignature === true}
              onCheckedChange={(v) => patchFooter({ showRequestorSignature: v === true })}
            />
            Show requestor signature on print
          </label>
          <label className="flex items-center gap-3 text-sm text-foreground">
            <Checkbox
              checked={template.footer?.appendAttachments !== false}
              onCheckedChange={(v) => patchFooter({ appendAttachments: v === true })}
            />
            Append PDF/image attachments to merged PDF (with signature stamps)
          </label>
        </div>
      </section>

      <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h3 className="text-sm font-medium text-foreground">Subsidiary logos</h3>
          <p className="mt-1 text-xs text-muted-foreground">
            Add any subsidiary codes and upload logos. Print uses{" "}
            <code className="rounded bg-muted px-1">{"{{system.subsidiary_logo}}"}</code> from the form’s{" "}
            Subsidiary field. Choices on that field stay in sync with this list.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-2">
          <div className="min-w-[10rem] flex-1 space-y-1.5 sm:max-w-xs">
            <Label htmlFor="ea-subsidiary-code">Add subsidiary code</Label>
            <Input
              id="ea-subsidiary-code"
              className="h-9 uppercase"
              placeholder="e.g. ATC, ADIC, NEWCO"
              value={newCode}
              disabled={logosBusy}
              onChange={(e) => setNewCode(e.target.value.toUpperCase())}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  onAddCode();
                }
              }}
            />
          </div>
          <Button type="button" size="sm" disabled={logosBusy || !newCode.trim()} onClick={onAddCode}>
            {addCodeMutation.isPending ? "Adding…" : "Add"}
          </Button>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          {subsidiaryCodes.map((code) => {
            const logoPath = subsidiaryLogos[code] ?? null;
            return (
              <div key={code} className="rounded-lg border border-border bg-background p-3">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-sm font-medium text-foreground">{code}</p>
                    <p className="text-[11px] text-muted-foreground">Shown when Subsidiary = {code}</p>
                  </div>
                  <div className="flex flex-wrap justify-end gap-1">
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={logosBusy}
                      onClick={() => fileInputRefs.current[code]?.click()}
                    >
                      {logoPath ? "Replace" : "Upload"}
                    </Button>
                    {logoPath ? (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={logosBusy}
                        onClick={() => clearLogoMutation.mutate(code)}
                      >
                        Clear
                      </Button>
                    ) : null}
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={logosBusy}
                      onClick={() => removeCodeMutation.mutate(code)}
                    >
                      Remove
                    </Button>
                  </div>
                </div>
                <input
                  ref={(el) => {
                    fileInputRefs.current[code] = el;
                  }}
                  type="file"
                  accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                  className="hidden"
                  onChange={(e) => {
                    onPickLogo(code, e.target.files?.[0]);
                    e.target.value = "";
                  }}
                />
                <div className="mt-3 flex min-h-[56px] items-center justify-center rounded-md border border-dashed border-border bg-muted/30 px-3 py-2">
                  <EApprovalAuthenticatedImage
                    pathOrUrl={logoPath}
                    alt={`${code} logo`}
                    refreshKey={logoPath ?? code}
                  />
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <EApprovalDocumentDesignEditor
          formTitle={formTitle}
          fields={designFields}
          fieldTokens={fieldTokens}
          html={template.template_html ?? ""}
          css={template.template_css ?? ""}
          pageSize={template.page?.size ?? "A4"}
          subsidiaryLogos={subsidiaryLogos}
          subsidiaryLogoField={template.subsidiary_logo_field ?? "subsidiary"}
          onHtmlChange={(html) => setTemplate((prev) => ({ ...prev, template_html: html }))}
          onCssChange={(css) => setTemplate((prev) => ({ ...prev, template_css: css }))}
        />
      </section>

      {layoutQuery.data?.updated_at ? (
        <p className="text-xs text-muted-foreground">
          Last saved {layoutQuery.data.updated_by_name ? `by ${layoutQuery.data.updated_by_name}` : ""}
          {layoutQuery.data.layout_persisted ? " · custom design active" : " · using defaults"}
        </p>
      ) : null}
    </div>
  );
}
