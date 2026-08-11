import type { EApprovalBuilderLayoutRow } from "@/modules/e-approval/builder-layout-rows";
import { BUILDER_LAYOUT_ROWS_META_KEY } from "@/modules/e-approval/builder-layout-rows";
import {
  layoutWidthForRowColumns,
  normalizeFormFieldLayouts,
  parseFieldLayout,
  patchFieldLayout,
} from "@/modules/e-approval/field-layout";
import {
  PO_PRINT_TEMPLATE_KIND,
  isPurchaseOrderFormMetadata,
} from "@/modules/e-approval/purchase-order-template";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export const PROCUREMENT_PO_FAMILY = "purchase_order" as const;
export const PROCUREMENT_PR_FAMILY = "purchase_requisition" as const;

export const PR_PRINT_TEMPLATE_KIND = "purchase_requisition" as const;

export const PO_ROW_IDS = {
  parties: "po_row_parties",
  orderMeta: "po_row_order_meta",
} as const;

export const PR_ROW_IDS = {
  header: "pr_row_header",
  meta: "pr_row_meta",
} as const;

/** Left column of the PO tax summary block (matches print layout). */
export const PO_TAX_SUMMARY_LEFT_FIELDS = [
  "vatable_amount",
  "vat_exempt_amount",
  "zero_rated_amount",
  "vat_rate",
  "vat_amount",
] as const;

/** Right column totals box (matches print layout). */
export const PO_TAX_SUMMARY_RIGHT_FIELDS = ["total_vat_inclusive", "less_discount", "grand_total"] as const;

export const PO_SECTION_TAX_SUMMARY = "section_tax_summary";
export const PO_SECTION_PARTIES = "section_parties";
export const PO_SECTION_ORDER = "section_terms";

export const PR_SECTION_HEADER = "section_header";
export const PR_SECTION_META = "section_meta";
export const PR_SECTION_TOTALS = "section_totals";
export const PR_SECTION_JUSTIFICATION = "section_justification";

export function isPurchaseRequisitionFormMetadata(metadata: Record<string, unknown> | null | undefined): boolean {
  return String(metadata?.print_template_kind ?? "") === PR_PRINT_TEMPLATE_KIND
    || String(metadata?.form_family ?? "") === PROCUREMENT_PR_FAMILY;
}

export function isProcurementDocumentForm(
  fields: EApprovalFormFieldInput[],
  metadata?: Record<string, unknown> | null,
): boolean {
  if (isPurchaseOrderFormMetadata(metadata) || isPurchaseRequisitionFormMetadata(metadata)) {
    return true;
  }
  if (String(metadata?.form_family ?? "") === PROCUREMENT_PO_FAMILY) {
    return true;
  }

  return fields.some(
    (field) =>
      (field.name === "grand_total" && field.type === "currency")
      || (field.name === "estimated_total" && field.type === "currency"),
  );
}

function withRowLayout(
  field: EApprovalFormFieldInput,
  rowId: string,
  slot: number,
  columns: 2 | 4,
): EApprovalFormFieldInput {
  return {
    ...field,
    options: patchFieldLayout(field, {
      row_id: rowId,
      slot,
      row_columns: columns,
      width: layoutWidthForRowColumns(columns),
    }),
  };
}

function fieldHasExplicitLayout(field: EApprovalFormFieldInput): boolean {
  const options = field.options;
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return false;
  }

  const layout = (options as Record<string, unknown>).layout;
  return layout !== undefined && layout !== null && typeof layout === "object" && !Array.isArray(layout);
}

/** Apply procurement template row defaults only when the designer has not set a layout. */
function withDefaultRowLayout(
  field: EApprovalFormFieldInput,
  rowId: string,
  slot: number,
  columns: 2 | 4,
): EApprovalFormFieldInput {
  if (parseFieldLayout(field).row_id || fieldHasExplicitLayout(field)) {
    return field;
  }

  return withRowLayout(field, rowId, slot, columns);
}

export function buildPurchaseOrderBuilderLayoutRows(insertIndex: number): EApprovalBuilderLayoutRow[] {
  return [
    { id: PO_ROW_IDS.parties, columns: 2, insert_index: insertIndex + 1 },
    { id: PO_ROW_IDS.orderMeta, columns: 4, insert_index: insertIndex + 4 },
  ];
}

export function buildPurchaseRequisitionBuilderLayoutRows(insertIndex: number): EApprovalBuilderLayoutRow[] {
  return [
    { id: PR_ROW_IDS.header, columns: 2, insert_index: insertIndex + 1 },
    { id: PR_ROW_IDS.meta, columns: 4, insert_index: insertIndex + 4 },
  ];
}

export function buildPurchaseOrderFormMetadata(insertIndex: number): Record<string, unknown> {
  return {
    print_template_kind: PO_PRINT_TEMPLATE_KIND,
    form_family: PROCUREMENT_PO_FAMILY,
    [BUILDER_LAYOUT_ROWS_META_KEY]: buildPurchaseOrderBuilderLayoutRows(insertIndex),
  };
}

export function buildPurchaseRequisitionFormMetadata(insertIndex: number): Record<string, unknown> {
  return {
    print_template_kind: PR_PRINT_TEMPLATE_KIND,
    form_family: PROCUREMENT_PR_FAMILY,
    [BUILDER_LAYOUT_ROWS_META_KEY]: buildPurchaseRequisitionBuilderLayoutRows(insertIndex),
  };
}

export function ensurePurchaseOrderFieldLayouts(
  fields: EApprovalFormFieldInput[],
  metadata?: Record<string, unknown> | null,
): EApprovalFormFieldInput[] {
  if (!isPurchaseOrderFormMetadata(metadata) && !fields.some((field) => field.name === "grand_total")) {
    return fields;
  }

  const layoutRows = Array.isArray(metadata?.[BUILDER_LAYOUT_ROWS_META_KEY])
    ? (metadata![BUILDER_LAYOUT_ROWS_META_KEY] as EApprovalBuilderLayoutRow[])
    : [];

  let next = fields.map((field) => {
    switch (field.name) {
      case "supplier":
        return withDefaultRowLayout(field, PO_ROW_IDS.parties, 0, 2);
      case "ship_to":
        return withDefaultRowLayout(field, PO_ROW_IDS.parties, 1, 2);
      case "delivery_date":
        return withDefaultRowLayout(field, PO_ROW_IDS.orderMeta, 0, 4);
      case "payment_terms":
        return withDefaultRowLayout(field, PO_ROW_IDS.orderMeta, 1, 4);
      case "currency_code":
        return withDefaultRowLayout(field, PO_ROW_IDS.orderMeta, 2, 4);
      case "exchange_rate":
        return withDefaultRowLayout(field, PO_ROW_IDS.orderMeta, 3, 4);
      default:
        return field;
    }
  });

  next = normalizeFormFieldLayouts(next, layoutRows);
  return next;
}

export function ensurePurchaseRequisitionFieldLayouts(
  fields: EApprovalFormFieldInput[],
  metadata?: Record<string, unknown> | null,
): EApprovalFormFieldInput[] {
  if (!isPurchaseRequisitionFormMetadata(metadata) && !fields.some((field) => field.name === "estimated_total")) {
    return fields;
  }

  const layoutRows = Array.isArray(metadata?.[BUILDER_LAYOUT_ROWS_META_KEY])
    ? (metadata![BUILDER_LAYOUT_ROWS_META_KEY] as EApprovalBuilderLayoutRow[])
    : [];

  let next = fields.map((field) => {
    switch (field.name) {
      case "requisition_title":
        return withDefaultRowLayout(field, PR_ROW_IDS.header, 0, 2);
      case "requested_by":
        return withDefaultRowLayout(field, PR_ROW_IDS.header, 1, 2);
      case "department":
        return withDefaultRowLayout(field, PR_ROW_IDS.meta, 0, 4);
      case "urgency":
        return withDefaultRowLayout(field, PR_ROW_IDS.meta, 1, 4);
      case "currency":
        return withDefaultRowLayout(field, PR_ROW_IDS.meta, 2, 4);
      case "needed_by":
        return withDefaultRowLayout(field, PR_ROW_IDS.meta, 3, 4);
      default:
        return field;
    }
  });

  next = normalizeFormFieldLayouts(next, layoutRows);
  return next;
}

export function ensureProcurementFieldLayouts(
  fields: EApprovalFormFieldInput[],
  metadata?: Record<string, unknown> | null,
): EApprovalFormFieldInput[] {
  return ensurePurchaseRequisitionFieldLayouts(
    ensurePurchaseOrderFieldLayouts(fields, metadata),
    metadata,
  );
}

export function isPoTaxSummaryField(fieldName: string): boolean {
  return (
    (PO_TAX_SUMMARY_LEFT_FIELDS as readonly string[]).includes(fieldName) ||
    (PO_TAX_SUMMARY_RIGHT_FIELDS as readonly string[]).includes(fieldName)
  );
}

export function poSectionKind(sectionName: string | undefined): "tax_summary" | "parties" | "order" | null {
  if (sectionName === PO_SECTION_TAX_SUMMARY) {
    return "tax_summary";
  }
  if (sectionName === PO_SECTION_PARTIES) {
    return "parties";
  }
  if (sectionName === PO_SECTION_ORDER) {
    return "order";
  }

  return null;
}

export function prSectionKind(sectionName: string | undefined): "header" | "meta" | "totals" | "justification" | null {
  if (sectionName === PR_SECTION_HEADER) {
    return "header";
  }
  if (sectionName === PR_SECTION_META) {
    return "meta";
  }
  if (sectionName === PR_SECTION_TOTALS) {
    return "totals";
  }
  if (sectionName === PR_SECTION_JUSTIFICATION) {
    return "justification";
  }

  return null;
}

export function procurementSectionKind(
  sectionName: string | undefined,
  metadata?: Record<string, unknown> | null,
): ReturnType<typeof poSectionKind> | ReturnType<typeof prSectionKind> {
  if (isPurchaseRequisitionFormMetadata(metadata)) {
    return prSectionKind(sectionName);
  }

  return poSectionKind(sectionName);
}
