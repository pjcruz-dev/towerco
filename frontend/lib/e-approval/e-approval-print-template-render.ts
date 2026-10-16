import { placeReviewTotals } from "@/modules/e-approval/form-compose-review";
import { resolvePrintAssetUrl } from "@/modules/e-approval/print-utils";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";
import {
  defaultCashAdvanceDocumentDesignCss,
  defaultCashAdvanceDocumentDesignHtml,
  defaultLiquidationDocumentDesignHtml,
  defaultReimbursementDocumentDesignHtml,
} from "@/lib/e-approval/e-approval-cash-advance-print-template";
import {
  defaultRequestForPaymentDocumentDesignCss,
  defaultRequestForPaymentDocumentDesignHtml,
  renderRfpCostApplicationPrintHtml,
} from "@/lib/e-approval/e-approval-rfp-print-template";

export type DocumentDesignFieldRef = {
  name: string;
  label: string;
  type?: string;
  /** Grid column labels for live preview sample tables. */
  grid_columns?: string[];
};

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
    case "form_body":
      return renderDynamicFormBodyHtml(payload);
    case "form_fields":
      return renderDynamicScalarFieldsHtml(partitionPrintScalarFields(payload).detailFields);
    case "form_grids":
      return renderDynamicGridsHtml(payload);
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

const HTML_SYSTEM_TOKENS = new Set([
  "company_logo",
  "subsidiary_logo",
  "form_body",
  "form_fields",
  "form_grids",
]);

const EXCLUDED_DYNAMIC_BODY_TYPES = new Set([
  ...NON_PRINT_BODY_FIELD_TYPES,
  "grid",
  "approver",
  "approver_list",
  "signature",
]);

function isWidePrintFieldType(type: string | null | undefined): boolean {
  const t = (type ?? "text").toLowerCase();
  return t === "textarea" || t === "file" || t === "richtext" || t === "camera" || t === "date_range";
}

/** Scalar "Total personal / Total expenses" style fields — print below grids, not in Request details. */
export function isPrintTotalScalarField(field: {
  key: string;
  label?: string | null;
  field_type?: string | null;
}): boolean {
  const key = field.key.trim().toLowerCase();
  const label = (field.label ?? "").trim().toLowerCase();
  const type = (field.field_type ?? "").toLowerCase();
  if (type === "grid") return false;
  if (/^total([_\s-]|$)/.test(key) || /^totals?([_\s-]|$)/.test(key)) return true;
  if (/^totals?\b/.test(label)) return true;
  return false;
}

export function parsePrintNumericCell(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed || /^[—\-–]$/.test(trimmed) || /^sample\b/i.test(trimmed)) {
    return null;
  }
  const parenNegative = /^\(.*\)$/.test(trimmed);
  const cleaned = trimmed
    .replace(/[^\d.,\-]/g, "")
    .replace(/,/g, "");
  if (cleaned === "" || cleaned === "-" || cleaned === ".") {
    return null;
  }
  const parsed = Number(cleaned);
  if (!Number.isFinite(parsed)) {
    return null;
  }
  return parenNegative ? -Math.abs(parsed) : parsed;
}

export function formatPrintNumericTotal(value: number): string {
  return `₱${value.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

/** Currency fields print with the same peso format as an Amount cell. */
export function formatPrintCurrencyField(value: string): string {
  const parsed = parsePrintNumericCell(value);
  if (parsed === null) {
    return value.trim();
  }
  return formatPrintNumericTotal(parsed);
}

const SUMMABLE_COLUMN_LABEL =
  /personal|official|total|amount|price|cost|qty|quantity|sum|subtotal|debit|credit|balance|hours|days|transport|gasoline|lodging|diem|vat|land|sea|air/i;

export function shouldSumPrintGridColumn(columnLabel: string, cells: string[]): boolean {
  const numericCount = cells.filter((cell) => parsePrintNumericCell(cell) !== null).length;
  if (numericCount === 0) return false;
  if (SUMMABLE_COLUMN_LABEL.test(columnLabel)) return true;
  return numericCount >= Math.ceil(cells.length / 2);
}

function renderScalarFieldRowHtml(label: string, value: string, wide: boolean): string {
  const rowClass = wide ? "ea-form-row ea-form-row--wide" : "ea-form-row";
  const display = value.trim() !== "" ? escapeHtml(value) : "—";
  return `    <div class="${rowClass}">
      <div class="ea-form-label">${escapeHtml(label)}</div>
      <div class="ea-form-value">${display}</div>
    </div>`;
}

function partitionPrintScalarFields(payload: EApprovalPrintPayload): {
  detailFields: EApprovalPrintPayload["fields"];
} {
  const gridKeys = new Set((payload.grids ?? []).map((grid) => grid.key));
  const detailFields: EApprovalPrintPayload["fields"] = [];
  const hasGrids = (payload.grids?.length ?? 0) > 0;

  for (const field of payload.fields ?? []) {
    const type = (field.field_type ?? "").toLowerCase();
    if (gridKeys.has(field.key)) continue;
    if (EXCLUDED_DYNAMIC_BODY_TYPES.has(type)) continue;
    // When line-item grids print with column footers, omit redundant Total* scalars.
    if (hasGrids && isPrintTotalScalarField(field)) continue;
    detailFields.push(field);
  }

  return { detailFields };
}

function renderDynamicScalarFieldsHtml(
  fields: EApprovalPrintPayload["fields"] | unknown,
  emptyMessage = "No printable fields on this form.",
): string {
  const list = Array.isArray(fields) ? fields : [];
  if (list.length === 0) {
    return `<div class="ea-form-grid">
${renderScalarFieldRowHtml("Details", emptyMessage, true)}
</div>`;
  }

  const scalarRows: string[] = [];
  const checklistBlocks: string[] = [];
  for (const field of list) {
    const type = (field.field_type ?? "").toLowerCase();
    if (type === "checklist_matrix") {
      checklistBlocks.push(renderRfpCostApplicationPrintHtml(field.value ?? ""));
      continue;
    }
    scalarRows.push(
      renderScalarFieldRowHtml(
        field.label || field.key,
        field.value ?? "",
        isWidePrintFieldType(field.field_type),
      ),
    );
  }

  const parts: string[] = [];
  if (scalarRows.length > 0) {
    parts.push(`<div class="ea-form-grid">
${scalarRows.join("\n")}
</div>`);
  }
  parts.push(...checklistBlocks);
  return parts.join("\n");
}

function printGridTotalLabel(label: string): string {
  return /forecast/i.test(label) ? "TOTAL FORECAST" : "TOTAL EXPENSES";
}

function renderPrintGridTotalsFooterHtml(grid: {
  label?: string;
  columns: string[];
  rows: string[][];
}): string {
  const columns = grid.columns.length > 0 ? grid.columns : ["Value"];
  if (grid.rows.length === 0) return "";

  const summable = columns.map((column, index) => {
    const cells = grid.rows.map((row) => row[index] ?? "");
    return shouldSumPrintGridColumn(column, cells);
  });
  if (!summable.some(Boolean)) return "";

  let labelSpan = 0;
  for (const flag of summable) {
    if (flag) break;
    labelSpan += 1;
  }
  labelSpan = Math.max(labelSpan, 1);

  const cells: string[] = [
    `<td class="ea-print-table-total-label" colspan="${labelSpan}"><strong>${printGridTotalLabel(grid.label ?? "")}</strong></td>`,
  ];

  for (let index = labelSpan; index < columns.length; index += 1) {
    if (!summable[index]) {
      cells.push(`<td></td>`);
      continue;
    }
    const total = grid.rows.reduce((sum, row) => {
      const parsed = parsePrintNumericCell(row[index] ?? "");
      return parsed === null ? sum : sum + parsed;
    }, 0);
    const isTotalCol = /^total$/i.test(columns[index] ?? "");
    cells.push(
      `<td class="ea-print-table-total-value${isTotalCol ? " ea-print-table-total-col" : ""}"><strong>${escapeHtml(formatPrintNumericTotal(total))}</strong></td>`,
    );
  }

  return `<tfoot><tr class="ea-print-table-totals">${cells.join("")}</tr></tfoot>`;
}

function isCashAdvanceForecastGrid(
  grid: { key: string; columns: string[] },
  formName: string,
): boolean {
  const form = formName.trim().toLowerCase();
  if (form === "reimbursement" || form === "liquidation") {
    return false;
  }
  if (form === "cash advance" && grid.key === "expense_lines") {
    return true;
  }
  const columns = grid.columns.map((column) => column.trim().toLowerCase());
  const forecast =
    columns.some((column) => column.startsWith("from date")) && columns.includes("amount");
  const actuals = columns.some(
    (column) =>
      column === "quantity" ||
      column === "rate" ||
      column === "forecast amount" ||
      column === "or no",
  );
  return forecast && !actuals;
}

function appendCashAdvanceSplitColumns(
  columns: string[],
  rows: string[][],
): { columns: string[]; rows: string[][] } {
  const nextColumns = [...columns];
  const needNon = !nextColumns.some((column) => /^non-?vatable$/i.test(column.trim()));
  const needNet = !nextColumns.some((column) => /net of vat/i.test(column.trim()));
  if (!needNon && !needNet) {
    return { columns, rows };
  }
  if (needNon) nextColumns.push("Non-VATable");
  if (needNet) nextColumns.push("VATable, net of VAT");

  const amountIndex = columns.findIndex((column) => column.trim().toLowerCase() === "amount");
  const treatmentIndex = columns.findIndex((column) => column.trim().toLowerCase() === "vat treatment");
  const vatIndex = columns.findIndex((column) => column.trim().toLowerCase() === "vat");
  const nextRows = rows.map((row) => {
    const amount = amountIndex >= 0 ? (parsePrintNumericCell(row[amountIndex] ?? "") ?? 0) : 0;
    const treatment = treatmentIndex >= 0 ? (row[treatmentIndex] ?? "").toLowerCase() : "";
    const nonVatable = /non/.test(treatment);
    const vat = vatIndex >= 0 ? (parsePrintNumericCell(row[vatIndex] ?? "") ?? 0) : 0;
    const net = nonVatable ? 0 : Math.max(0, Math.round((amount - vat) * 100) / 100);
    const extra: string[] = [];
    if (needNon) extra.push(formatPrintNumericTotal(nonVatable ? amount : 0));
    if (needNet) extra.push(formatPrintNumericTotal(net));
    return [...row, ...extra];
  });

  return { columns: nextColumns, rows: nextRows };
}

function expenseGridWantsVatSplit(columns: string[]): boolean {
  const labels = columns.map((column) => column.trim().toLowerCase());
  const hasSplit = labels.some((label) => label === "expense type" || label === "vat treatment");
  const hasMoney = labels.some((label) => label === "amount" || label === "rate");
  return hasSplit && hasMoney;
}

/** Totals sit under Amount, VAT, Non-VATable, and VATable net, same as the request table. */
function renderCashAdvanceReviewFooter(
  columns: string[],
  fields: EApprovalPrintPayload["fields"],
): string {
  const fieldByKey = (key: string) => (fields ?? []).find((field) => field.key === key);
  const money = (value: string) => formatPrintCurrencyField(value) || "₱0.00";
  const amountField = fieldByKey("total_reimbursement") ?? fieldByKey("requested_amount");
  const placed = placeReviewTotals(columns, [
    {
      label: amountField?.label?.trim() || (fieldByKey("total_reimbursement") ? "Total expenses" : "Requested amount"),
      value: money(amountField?.value ?? ""),
    },
    { label: "VAT", value: money(fieldByKey("vat_total")?.value ?? "") },
    { label: "Non-VATable", value: money(fieldByKey("non_vatable_total")?.value ?? "") },
    { label: "VATable, net of VAT", value: money(fieldByKey("vatable_net")?.value ?? "") },
  ]);
  if (placed.length === 0) {
    return "";
  }

  const rows = placed.map((totalRow, rowIndex) => {
    const cells = totalRow.map((total, cellIndex) => {
      if (total) {
        const classes = [printGridColumnClass(columns[cellIndex] ?? ""), "ea-print-table-total-value"]
          .filter(Boolean)
          .join(" ");
        return `<td class="${classes}" style="text-align:right;vertical-align:bottom;"><strong style="display:block;white-space:nowrap;font-variant-numeric:tabular-nums;">${escapeHtml(total.value)}</strong></td>`;
      }
      if (rowIndex === 0 && cellIndex === 0) {
        return `<td class="ea-print-table-total-label" style="text-align:left;vertical-align:bottom;"><strong>Totals</strong></td>`;
      }
      return `<td></td>`;
    });
    return `<tr class="ea-print-table-totals">${cells.join("")}</tr>`;
  });

  return `<tfoot>${rows.join("")}</tfoot>`;
}

/** Width hints so a landscape cash-advance forecast table can keep every value visible. */
export function printGridColumnClass(column: string): string {
  const label = column.trim().toLowerCase();
  if (label === "date" || label.startsWith("from date") || label === "date range") {
    return "ea-print-col-date";
  }
  if (label.startsWith("from site") || label.startsWith("to site")) {
    return "ea-print-col-site";
  }
  if (label === "expense type") {
    return "ea-print-col-type";
  }
  if (label === "amount" || label === "rate" || label === "forecast amount") {
    return "ea-print-col-money";
  }
  if (label === "vat" || label === "non-vatable" || /net of vat/.test(label)) {
    return "ea-print-col-vat";
  }
  if (label === "vat treatment") {
    return "ea-print-col-treatment";
  }
  if (label === "quantity" || label === "qty") {
    return "ea-print-col-qty";
  }
  if (label === "or no" || label === "or number") {
    return "ea-print-col-ref";
  }
  if (label === "supplier/payee" || label === "supplier" || label === "payee") {
    return "ea-print-col-party";
  }
  if (label === "description" || label === "variance reason") {
    return "ea-print-col-note";
  }

  return "";
}

export function renderPrintGridTableHtml(
  grid: {
    key: string;
    label: string;
    columns: string[];
    rows: string[][];
  },
  options?: {
    formName?: string;
    fields?: EApprovalPrintPayload["fields"];
  },
): string {
  const visible = hideLiquidationForecastCompanionColumns(grid.columns, grid.rows);
  const cashAdvanceGrid = isCashAdvanceForecastGrid(
    { ...grid, columns: visible.columns, rows: visible.rows },
    options?.formName ?? "",
  );
  const wantsVatSplit = cashAdvanceGrid || expenseGridWantsVatSplit(visible.columns);
  const expanded = wantsVatSplit
    ? appendCashAdvanceSplitColumns(visible.columns.length > 0 ? visible.columns : ["Value"], visible.rows)
    : null;
  const columns = expanded?.columns ?? (visible.columns.length > 0 ? visible.columns : ["Value"]);
  const sourceRows = expanded?.rows ?? visible.rows;
  const head = columns
    .map((column) => {
      const isTotalCol = /^total$/i.test(column);
      const classes = [printGridColumnClass(column), isTotalCol ? "ea-print-table-total-col" : ""]
        .filter(Boolean)
        .join(" ");
      return `<th class="${classes}">${escapeHtml(column)}</th>`;
    })
    .join("");
  const body =
    sourceRows.length > 0
      ? sourceRows
          .map((row) => {
            const cells = columns.map((column, index) => {
              const cell = row[index] ?? "";
              const isTotalCol = /^total$/i.test(column);
              const classes = [printGridColumnClass(column), isTotalCol ? "ea-print-table-total-col" : ""]
                .filter(Boolean)
                .join(" ");
              const isMoney = shouldSumPrintGridColumn(
                column,
                sourceRows.map((r) => r[index] ?? ""),
              );
              let display = cell.trim() !== "" ? cell : isMoney ? "₱0.00" : "—";
              if (isMoney && cell.trim() !== "") {
                const parsed = parsePrintNumericCell(cell);
                if (parsed !== null) {
                  display = formatPrintNumericTotal(parsed);
                }
              }
              return `<td class="${classes}">${escapeHtml(display)}</td>`;
            });
            return `<tr>${cells.join("")}</tr>`;
          })
          .join("")
      : `<tr><td colspan="${columns.length}">No line items</td></tr>`;
  const foot = wantsVatSplit
    ? renderCashAdvanceReviewFooter(columns, options?.fields)
    : renderPrintGridTotalsFooterHtml({ label: grid.label, columns, rows: sourceRows });

  return `<section class="ea-form-section ea-form-grid-section" data-grid-key="${escapeHtml(grid.key)}">
  <h2 class="ea-form-section-title">${escapeHtml(grid.label || grid.key)}</h2>
  <div class="ea-print-table-wrap">
    <table class="ea-print-table">
      <thead><tr>${head}</tr></thead>
      <tbody>${body}</tbody>
      ${foot}
    </table>
  </div>
</section>`;
}

/** Quantity and Rate do not price a liquidation line once Forecast amount is on the table. */
function hideLiquidationForecastCompanionColumns(
  columns: string[],
  rows: string[][],
): { columns: string[]; rows: string[][] } {
  const labels = columns.map((column) => column.trim().toLowerCase());
  const has = (name: string) => labels.includes(name);
  const dropQtyRate =
    has("forecast amount") ||
    (has("or no") && has("expense type") && has("amount") && has("quantity") && has("rate"));
  if (!dropQtyRate) {
    return { columns, rows };
  }
  const kept = columns
    .map((column, index) => ({ column, index }))
    .filter(({ column }) => !/^(quantity|rate)$/i.test(column.trim()));
  return {
    columns: kept.map(({ column }) => column),
    rows: rows.map((row) => kept.map(({ index }) => row[index] ?? "")),
  };
}

function renderDynamicGridsHtml(payload: EApprovalPrintPayload, onlyKey?: string): string {
  const grids = (payload.grids ?? []).filter((grid) =>
    onlyKey ? grid.key === onlyKey : true,
  );
  if (grids.length === 0) {
    return onlyKey
      ? `<p class="ea-form-hint">No rows for ${escapeHtml(onlyKey)}.</p>`
      : "";
  }
  return grids
    .map((grid) =>
      renderPrintGridTableHtml(grid, {
        formName: payload.form_name,
        fields: payload.fields,
      }),
    )
    .join("\n");
}

function renderDynamicFormBodyHtml(payload: EApprovalPrintPayload): string {
  const { detailFields } = partitionPrintScalarFields(payload);
  const gridsHtml = renderDynamicGridsHtml(payload);
  const parts: string[] = [];

  if (detailFields.length > 0 || (payload.grids?.length ?? 0) === 0) {
    parts.push(`<section class="ea-form-section">
  <h2 class="ea-form-section-title">Request details</h2>
  ${renderDynamicScalarFieldsHtml(detailFields)}
</section>`);
  }

  if (gridsHtml) {
    parts.push(gridsHtml);
  }

  return parts.join("\n");
}

/** True when custom HTML already embeds grids via dynamic tokens (avoid duplicate React tables). */
export function documentDesignEmbedsGrids(html: string | null | undefined): boolean {
  const source = html ?? "";
  return (
    /\{\{\s*system\.form_body\s*\}\}/i.test(source) ||
    /\{\{\s*system\.form_grids\s*\}\}/i.test(source) ||
    /\{\{\s*grid\./i.test(source)
  );
}

/** Removes legacy static sign-off placeholders (real stamps come from workflow approvals). */
export function stripRedundantDocumentDesignSignoff(html: string): string {
  if (!html.trim()) return html;
  return html
    .replace(/<footer\b[^>]*\bea-form-signoff\b[^>]*>[\s\S]*?<\/footer>/gi, "")
    .replace(/<div\b[^>]*\bea-form-signoff\b[^>]*>[\s\S]*?<\/div>/gi, "");
}

/**
 * Merge {{field.*}} / {{system.*}} / {{grid.*}} tokens into document design HTML.
 * Text values are escaped; logo / form_body / grid tokens inject trusted HTML.
 * Strips legacy static Prepared/Approved boxes so they do not duplicate Approval history.
 */
export function renderEApprovalPrintTemplateHtml(
  html: string,
  payload: EApprovalPrintPayload,
): string {
  if (!html.trim()) return "";

  const fields = fieldMap(payload);
  const fieldsByKey = new Map((payload.fields ?? []).map((field) => [field.key, field]));
  const source = stripRedundantDocumentDesignSignoff(html);

  return source.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_match, rawToken: string) => {
    const token = String(rawToken).trim();
    if (token.startsWith("field.")) {
      const key = token.slice("field.".length);
      const meta = fieldsByKey.get(key);
      const raw = fields[key] ?? "";
      const type = (meta?.field_type ?? "").toLowerCase();
      if (type === "checklist_matrix") {
        return renderRfpCostApplicationPrintHtml(raw);
      }
      if (type === "currency") {
        return escapeHtml(formatPrintCurrencyField(raw));
      }
      return escapeHtml(raw);
    }
    if (token.startsWith("grid.")) {
      const key = token.slice("grid.".length);
      return renderDynamicGridsHtml(payload, key);
    }
    if (token.startsWith("system.")) {
      const key = token.slice("system.".length);
      const value = systemValue(payload, key);
      if (HTML_SYSTEM_TOKENS.has(key)) return value;
      return escapeHtml(value);
    }
    return "";
  });
}

export type FinancePrintFamily = "cash_advance" | "liquidation" | "reimbursement";

export function financePrintFamily(data: {
  form_family?: string | null;
  form_name?: string | null;
}): FinancePrintFamily | null {
  const family = String(data.form_family ?? "").trim();
  if (family === "cash_advance" || family === "liquidation" || family === "reimbursement") {
    return family;
  }
  const name = String(data.form_name ?? "").trim();
  if (/^cash advance$/i.test(name)) return "cash_advance";
  if (/^liquidation$/i.test(name)) return "liquidation";
  if (/^reimbursement$/i.test(name)) return "reimbursement";
  return null;
}

/** Empty or generic letterhead designs pick up the finance page. A saved family design is left as written. */
export function shouldUseFinancePrintStarter(
  savedHtml: string,
  family: FinancePrintFamily | null,
): boolean {
  if (!family) return false;
  const html = savedHtml.trim();
  const generic = !html || html === defaultEApprovalDocumentDesignHtml().trim();
  if (family === "cash_advance") return generic;
  if (family === "liquidation") return generic || !html.includes("ea-lq-doc");
  return generic || !html.includes("ea-re-doc");
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
  { token: "{{system.form_body}}", label: "Dynamic form body (fields + grids)" },
  { token: "{{system.form_fields}}", label: "Dynamic fields only" },
  { token: "{{system.form_grids}}", label: "Dynamic grids only" },
  { token: "{{system.document_no}}", label: "Document no." },
  { token: "{{system.form_name}}", label: "Form name" },
  { token: "{{system.status}}", label: "Status" },
  { token: "{{system.requestor}}", label: "Requestor" },
  { token: "{{system.submitted_at}}", label: "Submitted at" },
  { token: "{{system.subsidiary_logo}}", label: "Subsidiary logo" },
  { token: "{{system.company_logo}}", label: "Company logo" },
] as const;

/** Fields that belong in the form-style print body (includes grids for token lists). */
export function printableDesignFields(fields: DocumentDesignFieldRef[]): DocumentDesignFieldRef[] {
  return fields.filter((field) => {
    const type = (field.type ?? "text").toLowerCase();
    if (NON_PRINT_BODY_FIELD_TYPES.has(type)) return false;
    if (!field.name?.trim()) return false;
    return true;
  });
}

/** Scalar fields only (grids use {{grid.*}} / form_body). */
export function printableScalarDesignFields(
  fields: DocumentDesignFieldRef[],
): DocumentDesignFieldRef[] {
  return printableDesignFields(fields).filter(
    (field) => (field.type ?? "text").toLowerCase() !== "grid",
  );
}

export function printableGridDesignFields(
  fields: DocumentDesignFieldRef[],
): DocumentDesignFieldRef[] {
  return printableDesignFields(fields).filter(
    (field) => (field.type ?? "").toLowerCase() === "grid",
  );
}

/**
 * Form-style starter layout: letterhead + {{system.form_body}}.
 * Fields and line-item grids resolve from the live print payload — no per-field HTML edits.
 * Workflow approval signatures are stamped separately under the form.
 * Request for payment uses the paper-style ATC/ADIC RFP layout.
 */
export function defaultEApprovalDocumentDesignHtml(
  _formTitle?: string,
  _fields: DocumentDesignFieldRef[] = [],
  formFamily?: string | null,
): string {
  if (formFamily === "request_for_payment") {
    return defaultRequestForPaymentDocumentDesignHtml();
  }

  if (formFamily === "cash_advance") {
    return defaultCashAdvanceDocumentDesignHtml();
  }

  if (formFamily === "liquidation") {
    return defaultLiquidationDocumentDesignHtml();
  }

  if (formFamily === "reimbursement") {
    return defaultReimbursementDocumentDesignHtml();
  }

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

  {{system.form_body}}
</div>`;
}

export type EApprovalPrintPageOptions = {
  size?: string;
  marginMm?: number;
  orientation?: "portrait" | "landscape";
};

export function normalizePrintPageSize(size?: string | null): string {
  const raw = String(size ?? "A4").trim();
  if (/^letter$/i.test(raw)) return "letter";
  if (/^legal$/i.test(raw)) return "legal";
  return "A4";
}

export function normalizePrintOrientation(
  orientation?: string | null,
): "portrait" | "landscape" {
  return String(orientation ?? "").trim().toLowerCase() === "landscape" ? "landscape" : "portrait";
}

/** `@page` rule from saved Print options (size, orientation, margin). */
export function buildEApprovalPrintPageCss(options?: EApprovalPrintPageOptions): string {
  const size = normalizePrintPageSize(options?.size);
  const margin = Math.min(40, Math.max(0, Number(options?.marginMm ?? 12) || 0));
  const orientation = normalizePrintOrientation(options?.orientation);
  return `@page { size: ${size} ${orientation}; margin: ${margin}mm; }`;
}

/** Merge document CSS with Print options `@page` (options win over any embedded @page). */
export function mergeEApprovalPrintCss(
  documentCss: string,
  pageOptions?: EApprovalPrintPageOptions,
): string {
  const pageCss = buildEApprovalPrintPageCss(pageOptions);
  const withoutPage = documentCss.replace(/@page\s*\{[\s\S]*?\}/gi, "").trim();
  return withoutPage ? `${pageCss}\n\n${withoutPage}` : pageCss;
}

export function defaultEApprovalDocumentDesignCss(
  options?: EApprovalPrintPageOptions,
  formFamily?: string | null,
): string {
  if (formFamily === "request_for_payment") {
    return defaultRequestForPaymentDocumentDesignCss(options);
  }

  if (formFamily === "cash_advance" || formFamily === "liquidation" || formFamily === "reimbursement") {
    return defaultCashAdvanceDocumentDesignCss({
      size: options?.size,
      marginMm: options?.marginMm,
      orientation: options?.orientation ?? "landscape",
    });
  }

  return `${buildEApprovalPrintPageCss(options)}

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
  border-bottom: 1px solid #e2e8f0;
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

.ea-form-grid-section { margin-top: 16px; }
.ea-form-hint {
  margin: 8px 0 0;
  font-size: 11px;
  color: #64748b;
}
.ea-print-table-wrap {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}
.ea-print-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 9px;
  table-layout: auto;
}
.ea-print-table th,
.ea-print-table td {
  border: 1px solid #e2e8f0;
  padding: 3px 4px;
  text-align: left;
  vertical-align: top;
  word-break: break-word;
}
.ea-print-table thead th {
  background: #f1f5f9;
  color: #0f172a;
  font-weight: 500;
  font-size: 8px;
  text-align: center;
  line-height: 1.25;
}
.ea-print-table tbody tr:nth-child(even) { background: #f8fafc; }
.ea-print-table-total-col { background: #f1f5f9 !important; }
.ea-print-table tfoot td {
  font-weight: 600;
  border-top: 1px solid #e2e8f0;
  font-size: 9px;
}
.ea-print-table-total-label {
  text-align: center;
  background: #f1f5f9 !important;
  color: #0f172a;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 500;
  font-size: 8px;
}
.ea-print-table-total-value { text-align: right; white-space: nowrap; background: #f8fafc; }

/* Wide expense / liquidation grids stay readable on A4 */
.ea-form-grid-section[data-grid-key="expense_lines"] .ea-print-table,
.ea-form-grid-section[data-grid-key*="expense"] .ea-print-table {
  font-size: 8px;
}
.ea-form-grid-section[data-grid-key="expense_lines"] .ea-print-table th,
.ea-form-grid-section[data-grid-key="expense_lines"] .ea-print-table td,
.ea-form-grid-section[data-grid-key*="expense"] .ea-print-table th,
.ea-form-grid-section[data-grid-key*="expense"] .ea-print-table td {
  padding: 2px 3px;
}

@media print {
  .ea-form-doc { color: #000; }
  .ea-form-label { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .ea-form-docmeta { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .ea-print-table thead th,
  .ea-print-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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

  const previewFields = fields
    .filter((field) => (field.type ?? "text").toLowerCase() !== "grid")
    .map((field, index) => ({
      key: field.name,
      label: field.label || field.name,
      value:
        field.name === logoField
          ? preferredCode
          : samplePreviewValue(field.label || field.name, field.type, index),
      field_type: field.type ?? "text",
    }));

  // Ensure live preview can resolve {{system.subsidiary_logo}} even if the form
  // field list does not currently include Subsidiary (or it was filtered out).
  if (Object.keys(logos).length > 0 && !previewFields.some((field) => field.key === logoField)) {
    previewFields.unshift({
      key: logoField,
      label: "Subsidiary",
      value: preferredCode,
      field_type: "select",
    });
  }

  const grids = printableGridDesignFields(fields).map((field) => {
    const columns =
      field.grid_columns && field.grid_columns.length > 0
        ? field.grid_columns
        : ["Date", "Description", "Amount"];
    const sampleRow = (rowIndex: number) =>
      columns.map((column) => {
        if (/personal/i.test(column)) return rowIndex === 0 ? "3,200.00" : "3,800.00";
        if (/official/i.test(column)) return rowIndex === 0 ? "4,100.00" : "3,900.00";
        if (/total|amount|price|cost/i.test(column)) return rowIndex === 0 ? "7,300.00" : "7,700.00";
        if (/date/i.test(column)) return rowIndex === 0 ? "2026-09-01" : "2026-09-03";
        return `Sample ${column}${rowIndex === 0 ? "" : " 2"}`;
      });
    return {
      key: field.name,
      label: field.label || field.name,
      columns,
      rows: [sampleRow(0), sampleRow(1)],
    };
  });

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
    grids,
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
  if (t === "date_range") return "2026-09-01 – 2026-09-04";
  if (t === "textarea" || t === "richtext") {
    return `Sample ${label} content for live preview. Replace with live submission values when printing.`;
  }
  if (t === "select" || t === "radio") return `Option ${index + 1}`;
  if (t === "checkbox" || t === "boolean") return "Yes";
  if (t === "file") return "sample-attachment.pdf";
  if (t === "checklist_matrix") {
    return "SAQ-Site Survey — Project Site No: SITE-01; Ref No: REF-100; OR No.: OR-55\nCME-Materials — Project Site No: SITE-02; Ref No: ; OR No.: ";
  }
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
        ? "Click Insert starter layout for a letterhead + dynamic form body (fields and grids update automatically)."
        : "Add fields on the Design tab, then insert a starter layout.",
    );
    return tips;
  }
  if (!/\{\{\s*system\.document_no\s*\}\}/.test(trimmedHtml)) {
    tips.push("Add {{system.document_no}} so printed copies show the submission number.");
  }
  if (!documentDesignEmbedsGrids(trimmedHtml) && !/\{\{\s*field\./.test(trimmedHtml)) {
    tips.push(
      "Add {{system.form_body}} (or Insert starter layout) so fields and line-item grids print automatically.",
    );
  } else if (!documentDesignEmbedsGrids(trimmedHtml) && /\{\{\s*field\./.test(trimmedHtml)) {
    tips.push(
      "Re-insert starter layout to switch to {{system.form_body}} — expense line grids and new columns print automatically without editing HTML.",
    );
  }
  if (
    documentDesignEmbedsGrids(trimmedHtml) &&
    !/\{\{\s*system\.form_body\s*\}\}/i.test(trimmedHtml) &&
    !/\{\{\s*system\.form_grids\s*\}\}/i.test(trimmedHtml) &&
    !/\{\{\s*grid\./i.test(trimmedHtml)
  ) {
    tips.push(
      "Prefer {{system.form_body}} so expense columns stay dynamic when you edit the grid in the form builder.",
    );
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
