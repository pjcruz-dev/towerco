import { resolvePrintAssetUrl } from "@/modules/e-approval/print-utils";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";

export type DocumentDesignFieldRef = {
  name: string;
  label: string;
  type?: string;
};

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function fieldMap(payload: EApprovalPrintPayload): Record<string, string> {
  const map: Record<string, string> = {};
  for (const field of payload.fields ?? []) {
    map[field.key] = field.value?.trim() ?? "";
  }
  return map;
}

function companyLogoHtml(payload: EApprovalPrintPayload): string {
  const url = resolvePrintAssetUrl(payload.brand_logo_url);
  if (!url) return "";
  return `<img src="${escapeHtml(url)}" alt="" class="eapproval-print-logo" />`;
}

function subsidiaryLogoMap(payload: EApprovalPrintPayload): Record<string, string> {
  const fromPayload =
    payload.subsidiary_logos && typeof payload.subsidiary_logos === "object"
      ? payload.subsidiary_logos
      : {};
  const template = (payload.template ?? {}) as EApprovalPrintTemplate;
  const fromTemplate =
    template.subsidiary_logos && typeof template.subsidiary_logos === "object"
      ? template.subsidiary_logos
      : {};
  const merged: Record<string, string> = {};
  for (const [key, value] of Object.entries({ ...fromTemplate, ...fromPayload })) {
    if (typeof value === "string" && value.trim()) {
      merged[key.toUpperCase()] = value.trim();
    }
  }
  return merged;
}

function systemValue(payload: EApprovalPrintPayload, key: string): string {
  switch (key) {
    case "document_no":
      return payload.document_no?.trim() ?? "";
    case "status":
      return (payload.status ?? "").replace(/_/g, " ");
    case "requestor":
      return payload.requestor?.trim() ?? "";
    case "submitted_at":
      return payload.created_at?.trim() ?? "";
    case "form_name":
      return payload.form_name?.trim() ?? "";
    case "company_logo":
      return companyLogoHtml(payload);
    case "subsidiary_logo": {
      const template = (payload.template ?? {}) as EApprovalPrintTemplate;
      const fieldName = (template.subsidiary_logo_field ?? "subsidiary").trim() || "subsidiary";
      const fields = fieldMap(payload);
      const code = (fields[fieldName] ?? "").trim().toUpperCase();
      const logos = subsidiaryLogoMap(payload);
      const matched = (code && logos[code]) || undefined;
      const url = resolvePrintAssetUrl(matched);
      if (url) {
        return `<img src="${escapeHtml(url)}" alt="" class="eapproval-print-logo" />`;
      }
      // Preview / incomplete submissions: if logos exist but code missing, prefer ATC then ADIC.
      if (!code) {
        const fallbackLogo = logos.ATC || logos.ADIC;
        const fallbackUrl = resolvePrintAssetUrl(fallbackLogo);
        if (fallbackUrl) {
          return `<img src="${escapeHtml(fallbackUrl)}" alt="" class="eapproval-print-logo" />`;
        }
      }
      return companyLogoHtml(payload);
    }
    default:
      return "";
  }
}

/**
 * Merge {{field.*}} / {{system.*}} tokens into document design HTML.
 * Text values are escaped; company_logo injects a safe img tag.
 */
/** Removes legacy static sign-off placeholders (real stamps come from workflow approvals). */
export function stripRedundantDocumentDesignSignoff(html: string): string {
  if (!html.trim()) return html;
  return html
    .replace(/<footer\b[^>]*\bea-form-signoff\b[^>]*>[\s\S]*?<\/footer>/gi, "")
    .replace(/<div\b[^>]*\bea-form-signoff\b[^>]*>[\s\S]*?<\/div>/gi, "");
}

/**
 * Merge {{field.*}} / {{system.*}} tokens into document design HTML.
 * Text values are escaped; company_logo injects a safe img tag.
 * Strips legacy static Prepared/Approved boxes so they do not duplicate Approval history.
 */
export function renderEApprovalPrintTemplateHtml(
  html: string,
  payload: EApprovalPrintPayload,
): string {
  if (!html.trim()) return "";

  const fields = fieldMap(payload);
  const source = stripRedundantDocumentDesignSignoff(html);

  return source.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_match, rawToken: string) => {
    const token = String(rawToken).trim();
    if (token.startsWith("field.")) {
      const key = token.slice("field.".length);
      return escapeHtml(fields[key] ?? "");
    }
    if (token.startsWith("system.")) {
      const key = token.slice("system.".length);
      const value = systemValue(payload, key);
      if (key === "company_logo" || key === "subsidiary_logo") return value;
      return escapeHtml(value);
    }
    return "";
  });
}

export function hasCustomPrintDocumentDesign(
  template: EApprovalPrintTemplate | Record<string, unknown> | null | undefined,
): boolean {
  const html = typeof template?.template_html === "string" ? template.template_html.trim() : "";
  return html.length > 0;
}

export function shouldAppendPrintAttachments(
  template: EApprovalPrintTemplate | Record<string, unknown> | null | undefined,
): boolean {
  const footer = (template as EApprovalPrintTemplate | null | undefined)?.footer;
  if (footer && typeof footer === "object" && "appendAttachments" in footer) {
    return footer.appendAttachments !== false;
  }
  return true;
}

export const EAPPROVAL_SYSTEM_PRINT_TOKENS = [
  { token: "{{system.document_no}}", label: "Document no." },
  { token: "{{system.form_name}}", label: "Form name" },
  { token: "{{system.status}}", label: "Status" },
  { token: "{{system.requestor}}", label: "Requestor" },
  { token: "{{system.submitted_at}}", label: "Submitted at" },
  { token: "{{system.subsidiary_logo}}", label: "Subsidiary logo" },
  { token: "{{system.company_logo}}", label: "Company logo" },
] as const;

const NON_PRINT_BODY_FIELD_TYPES = new Set([
  "section",
  "page_break",
  "pagebreak",
  "divider",
  "heading",
  "html",
  "static",
  "info",
]);

/** Fields that belong in the form-style print body. */
export function printableDesignFields(fields: DocumentDesignFieldRef[]): DocumentDesignFieldRef[] {
  return fields.filter((field) => {
    const type = (field.type ?? "text").toLowerCase();
    if (NON_PRINT_BODY_FIELD_TYPES.has(type)) return false;
    if (!field.name?.trim()) return false;
    return true;
  });
}

function fieldRowHtml(field: DocumentDesignFieldRef, wide: boolean): string {
  const label = escapeHtml(field.label || field.name);
  const token = `{{field.${field.name}}}`;
  const rowClass = wide ? "ea-form-row ea-form-row--wide" : "ea-form-row";
  return `    <div class="${rowClass}">
      <div class="ea-form-label">${label}</div>
      <div class="ea-form-value">${token}</div>
    </div>`;
}

/**
 * Form-style starter layout for any E-Approval form: letterhead, meta strip,
 * and labeled field rows. Workflow approval signatures are stamped separately
 * from the submission (Approval history block) — not as static placeholders.
 */
export function defaultEApprovalDocumentDesignHtml(
  _formTitle?: string,
  fields: DocumentDesignFieldRef[] = [],
): string {
  const bodyFields = printableDesignFields(fields);
  const rows =
    bodyFields.length > 0
      ? bodyFields
          .map((field) => {
            const type = (field.type ?? "text").toLowerCase();
            const wide = type === "textarea" || type === "file" || type === "richtext" || type === "grid";
            return fieldRowHtml(field, wide);
          })
          .join("\n")
      : `    <div class="ea-form-row ea-form-row--wide">
      <div class="ea-form-label">Details</div>
      <div class="ea-form-value">Add fields on the Design tab, then re-insert this starter layout.</div>
    </div>`;

  return `<div class="eapproval-printable ea-form-doc">
  <header class="ea-form-letterhead">
    <div class="ea-form-brand">
      <div class="ea-form-logo">{{system.subsidiary_logo}}</div>
      <h1 class="ea-form-title">{{system.form_name}}</h1>
    </div>
    <div class="ea-form-docmeta">
      <div class="ea-form-docmeta-row"><span>Document</span><strong>{{system.document_no}}</strong></div>
      <div class="ea-form-docmeta-row"><span>Status</span><strong class="ea-form-status">{{system.status}}</strong></div>
      <div class="ea-form-docmeta-row"><span>Requestor</span><strong>{{system.requestor}}</strong></div>
      <div class="ea-form-docmeta-row"><span>Submitted</span><strong>{{system.submitted_at}}</strong></div>
    </div>
  </header>

  <section class="ea-form-section">
    <h2 class="ea-form-section-title">Request details</h2>
    <div class="ea-form-grid">
${rows}
    </div>
  </section>
</div>`;
}

export function defaultEApprovalDocumentDesignCss(): string {
  return `@page { size: A4; margin: 12mm; }

.eapproval-printable,
.ea-form-doc {
  box-sizing: border-box;
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  color: #0f172a;
  font-size: 12.5px;
  line-height: 1.45;
}

.ea-form-doc *,
.ea-form-doc *::before,
.ea-form-doc *::after { box-sizing: border-box; }

.ea-form-letterhead {
  display: flex;
  gap: 16px;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 14px;
  border-bottom: 2px solid #0f172a;
  margin-bottom: 16px;
}

.ea-form-brand {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  min-width: 0;
  flex: 1;
}
.ea-form-logo:empty { display: none; }
.eapproval-print-logo {
  display: block;
  max-height: 56px;
  max-width: 160px;
  object-fit: contain;
}
.ea-form-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: #0f172a;
  line-height: 1.25;
}

.ea-form-docmeta {
  flex-shrink: 0;
  min-width: 180px;
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}
.ea-form-docmeta-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 3px 0;
  font-size: 11px;
}
.ea-form-docmeta-row span { color: #64748b; }
.ea-form-docmeta-row strong { color: #0f172a; font-weight: 600; text-align: right; }
.ea-form-status { text-transform: capitalize; }

.ea-form-section { margin-top: 4px; }
.ea-form-section-title {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}

.ea-form-grid {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.ea-form-row {
  display: grid;
  grid-template-columns: 34% 1fr;
  border-bottom: 1px solid #e2e8f0;
  min-height: 36px;
}
.ea-form-row:last-child { border-bottom: none; }
.ea-form-row--wide { grid-template-columns: 34% 1fr; }
.ea-form-row--wide .ea-form-value { min-height: 56px; white-space: pre-wrap; }

.ea-form-label {
  padding: 8px 10px;
  background: #f1f5f9;
  border-right: 1px solid #e2e8f0;
  font-size: 11px;
  font-weight: 600;
  color: #475569;
}
.ea-form-value {
  padding: 8px 10px;
  color: #0f172a;
  font-size: 12.5px;
  word-break: break-word;
}

@media print {
  .ea-form-doc { color: #000; }
  .ea-form-label { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .ea-form-docmeta { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}`;
}

/** Sample print payload for live Document Design preview (editor only). */
export function buildEApprovalDocumentDesignPreviewPayload(
  fields: DocumentDesignFieldRef[],
  formTitle?: string,
  options?: {
    brand_logo_url?: string | null;
    subsidiary_logos?: Record<string, string>;
    subsidiary_logo_field?: string;
  },
): EApprovalPrintPayload {
  const logoField = (options?.subsidiary_logo_field ?? "subsidiary").trim() || "subsidiary";
  const logos = options?.subsidiary_logos ?? {};
  const preferredCode = logos.ATC ? "ATC" : logos.ADIC ? "ADIC" : "ATC";

  const previewFields = fields.map((field, index) => ({
    key: field.name,
    label: field.label || field.name,
    value:
      field.name === logoField
        ? preferredCode
        : samplePreviewValue(field.label || field.name, field.type, index),
  }));

  // Ensure live preview can resolve {{system.subsidiary_logo}} even if the form
  // field list does not currently include Subsidiary (or it was filtered out).
  if (Object.keys(logos).length > 0 && !previewFields.some((field) => field.key === logoField)) {
    previewFields.unshift({
      key: logoField,
      label: "Subsidiary",
      value: preferredCode,
    });
  }

  return {
    document_no: "EA-PREVIEW-001",
    form_name: formTitle?.trim() || "Form preview",
    status: "in_progress",
    requestor: "Sample Requestor",
    created_at: new Date().toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }),
    brand_logo_url: options?.brand_logo_url ?? null,
    subsidiary_logos: logos,
    fields: previewFields,
    approvals: [],
    attachments: [],
    template: {
      subsidiary_logo_field: logoField,
      subsidiary_logos: logos,
    },
    show_approval_trail: false,
  };
}

function samplePreviewValue(label: string, type: string | undefined, index: number): string {
  const t = (type ?? "text").toLowerCase();
  if (t === "currency" || t === "number") return String((index + 1) * 1000);
  if (t === "date") return "4 Sep 2026";
  if (t === "textarea" || t === "richtext") {
    return `Sample ${label} content for live preview. Replace with live submission values when printing.`;
  }
  if (t === "select" || t === "radio") return `Option ${index + 1}`;
  if (t === "checkbox" || t === "boolean") return "Yes";
  if (t === "file") return "sample-attachment.pdf";
  return `Sample ${label}`;
}

export function documentDesignPreviewRecommendations(
  html: string,
  css: string,
  fieldCount = 0,
): string[] {
  const tips: string[] = [];
  const trimmedHtml = html.trim();
  if (!trimmedHtml) {
    tips.push(
      fieldCount > 0
        ? "Click Insert starter layout to generate a form-style printout with all current fields."
        : "Add fields on the Design tab, then insert a starter layout.",
    );
    return tips;
  }
  if (!/\{\{\s*system\.document_no\s*\}\}/.test(trimmedHtml)) {
    tips.push("Add {{system.document_no}} so printed copies show the submission number.");
  }
  if (!/\{\{\s*field\./.test(trimmedHtml)) {
    tips.push("Insert field tokens (or re-run Insert starter layout) so submission values appear.");
  }
  if (!css.trim() || !/@page/.test(css)) {
    tips.push("Keep Styles with an @page rule so paper size and margins stay consistent.");
  }
  tips.push("Live preview uses sample values. Real submissions fill tokens when you print.");
  tips.push(
    "Approval history under the form is dynamic from this submission’s workflow (signed approve actions).",
  );
  return tips;
}
