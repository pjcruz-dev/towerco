"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { EApprovalDocumentDesignEditor } from "@/components/e-approval/e-approval-document-design-editor";
import { EApprovalAuthenticatedImage } from "@/components/e-approval/e-approval-authenticated-image";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalPdfLayout, updateEApprovalPdfLayout } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { printableGridDesignFields, printableScalarDesignFields } from "@/lib/e-approval/e-approval-print-template-render";
import { parseGridColumns } from "@/modules/e-approval/field-options";
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
  /** When set, unsaved print defaults can prefer landscape for wide expense grids. */
  formFamily?: string | null;
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

export function EApprovalPrintLayoutEditor({ formId, fields, formTitle, formFamily }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [template, setTemplate] = useState<EApprovalPrintTemplate>({});

  const layoutQuery = useQuery({
    queryKey: ["e-approval", "pdf-layout", formId],
    queryFn: () => fetchEApprovalPdfLayout(formId),
  });

  const prefersExpenseLandscape = useMemo(
    () =>
      formFamily === "liquidation" ||
      formFamily === "reimbursement" ||
      fields.some((field) => field.type === "grid" && field.name.toLowerCase().includes("expense")),
    [formFamily, fields],
  );

  useEffect(() => {
    if (!layoutQuery.data?.template) {
      return;
    }
    const next = asTemplate(layoutQuery.data.template);

    // Unsaved defaults: ensure expense forms open as landscape even if global default is portrait.
    if (!layoutQuery.data.layout_persisted && prefersExpenseLandscape && !next.orientation) {
      next.orientation = "landscape";
      next.page = { size: next.page?.size ?? "A4", marginMm: next.page?.marginMm ?? 8 };
    }
    setTemplate(next);
    // Only re-hydrate from the server when layout payload changes — not when `fields` identity
    // changes (that was wiping subsidiary logos right after upload).
  }, [layoutQuery.data, prefersExpenseLandscape]);

  const designFields = useMemo(
    () =>
      fields.map((f) => ({
        name: f.name,
        label: f.label,
        type: f.type,
        grid_columns: f.type === "grid" ? parseGridColumns(f) : undefined,
      })),
    [fields],
  );

  const fieldTokens = useMemo(() => {
    const scalars = printableScalarDesignFields(designFields).map((field) => ({
      token: `{{field.${field.name}}}`,
      label: field.label || field.name,
    }));
    const grids = printableGridDesignFields(designFields).map((field) => ({
      token: `{{grid.${field.name}}}`,
      label: `${field.label || field.name} (table)`,
    }));
    return [...scalars, ...grids];
  }, [designFields]);

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

  const isProcurementTemplate =
    isPurchaseOrderPrintTemplate(template as Record<string, unknown>) ||
    isPurchaseRequisitionPrintTemplate(template as Record<string, unknown>) ||
    fields.some((field) => field.name === "grand_total");

  const canSave = allVisibleLayout.length > 0;

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
              for print; otherwise the structured layout is used.
            </p>
          ) : null}
        </div>
        <Button
          type="button"
          size="sm"
          disabled={!canSave || saveMutation.isPending}
          onClick={() => saveMutation.mutate()}
        >
          {saveMutation.isPending ? "Saving…" : "Save print design"}
        </Button>
      </div>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="grid gap-3 sm:grid-cols-3">
          <div className="space-y-2">
            <Label htmlFor="ea-print-page-size">Page size</Label>
            <Select
              id="ea-print-page-size"
              className="h-9"
              value={template.page?.size ?? "A4"}
              onChange={(e) =>
                setTemplate((prev) => ({
                  ...prev,
                  page: { size: e.target.value, marginMm: prev.page?.marginMm ?? 12 },
                }))
              }
            >
              <option value="A4">A4</option>
              <option value="Letter">Letter</option>
              <option value="Legal">Legal</option>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="ea-print-orientation">Orientation</Label>
            <Select
              id="ea-print-orientation"
              className="h-9"
              value={template.orientation ?? "portrait"}
              onChange={(e) =>
                setTemplate((prev) => ({
                  ...prev,
                  orientation: e.target.value === "landscape" ? "landscape" : "portrait",
                }))
              }
            >
              <option value="portrait">Portrait</option>
              <option value="landscape">Landscape</option>
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
        <p className="text-[11px] text-muted-foreground">
          Landscape is recommended for liquidation and reimbursement expense grids. Use{" "}
          <code className="rounded bg-muted px-1">{"{{system.form_body}}"}</code> (Insert starter layout) so expense
          columns print dynamically. Preview and browser print use these settings after you save.
        </p>

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
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 className="text-sm font-medium text-foreground">Subsidiary logos</h3>
            <p className="mt-1 text-xs text-muted-foreground">
              Inherited from tenant settings. Print uses{" "}
              <code className="rounded bg-muted px-1">{"{{system.subsidiary_logo}}"}</code> from the form’s Subsidiary
              field.
            </p>
          </div>
          <Link
            href="/e-approval/settings"
            className="inline-flex h-8 items-center justify-center rounded-md border border-border bg-background px-3 text-xs font-medium text-foreground shadow-sm hover:bg-muted"
          >
            Manage in Settings
          </Link>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          {subsidiaryCodes.map((code) => {
            const logoPath = subsidiaryLogos[code] ?? null;
            return (
              <div key={code} className="rounded-lg border border-border bg-background p-3">
                <div>
                  <p className="text-sm font-medium text-foreground">{code}</p>
                  <p className="text-[11px] text-muted-foreground">
                    {logoPath ? `Shown when Subsidiary = ${code}` : "No tenant logo uploaded yet"}
                  </p>
                </div>
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
          formFamily={formFamily}
          fields={designFields}
          fieldTokens={fieldTokens}
          html={template.template_html ?? ""}
          css={template.template_css ?? ""}
          pageSize={template.page?.size ?? "A4"}
          orientation={template.orientation ?? "portrait"}
          marginMm={template.page?.marginMm ?? 12}
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
