import { suggestApiKeyFromLabel } from "@/modules/e-approval/field-api-key";
import type { GridColumnDef } from "@/modules/e-approval/field-options";
import {
  PO_ROW_IDS,
  buildPurchaseOrderFormMetadata,
  ensurePurchaseOrderFieldLayouts,
} from "@/modules/e-approval/procurement-document-layout";
import { layoutWidthForRowColumns, patchFieldLayout } from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export const PO_PRINT_TEMPLATE_KIND = "purchase_order" as const;

export const PO_GRID_FIELD_NAME = "line_items";

export { buildPurchaseOrderFormMetadata, ensurePurchaseOrderFieldLayouts, isProcurementDocumentForm } from "@/modules/e-approval/procurement-document-layout";
export { printFieldValueMap as fieldValueMap } from "@/modules/e-approval/print-utils";

export const PO_GRID_COLUMNS: GridColumnDef[] = [
  { label: "Item", type: "text" },
  { label: "Description", type: "text" },
  { label: "UOM", type: "text" },
  { label: "Qty", type: "number" },
  { label: "Unit price", type: "currency" },
  { label: "Discount", type: "currency" },
  { label: "Amount", type: "currency" },
];

export const PO_METADATA_JSON = {
  print_template_kind: PO_PRINT_TEMPLATE_KIND,
  form_family: "purchase_order",
} as const;

function withRow(
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

export function buildPurchaseOrderPrintTemplate(): Record<string, unknown> {
  return {
    layout_kind: PO_PRINT_TEMPLATE_KIND,
    page: { size: "A4", marginMm: 10 },
    header: {
      showLogo: true,
      title: "Purchase Order",
      showDocumentNo: true,
      showStatus: true,
      showDate: true,
    },
    footer: {
      showApprovalHistory: true,
      showRequestorSignature: true,
      showPageNumbers: true,
      text: "Generated from TowerOS E-Approval",
    },
    blocks: {
      party_row: ["supplier", "ship_to"],
      meta_row: ["delivery_date", "payment_terms", "currency_code", "exchange_rate"],
      line_items_grid: PO_GRID_FIELD_NAME,
      tax_summary_left: [
        "vatable_amount",
        "vat_exempt_amount",
        "zero_rated_amount",
        "vat_amount",
        "total_vat_inclusive",
      ],
      tax_summary_right: ["total_vat_inclusive", "less_discount", "grand_total"],
      signatures: ["prepared_by"],
    },
  };
}

function currencyComputed(
  label: string,
  name: string,
  stepOrder: number,
  computed_from: Record<string, unknown>,
  helpText: string,
): EApprovalFormFieldInput {
  return {
    type: "currency",
    name,
    label,
    step_order: stepOrder,
    validation: { help_text: helpText },
    options: {
      read_only: true,
      computed_from,
    },
  };
}

export function buildPurchaseOrderTemplateFields(
  startIndex: number,
  existingApiKeys: Set<string>,
): { fields: EApprovalFormFieldInput[]; metadata: Record<string, unknown> } {
  const taken = new Set(existingApiKeys);
  const fields: EApprovalFormFieldInput[] = [];
  let order = startIndex;

  const add = (field: Omit<EApprovalFormFieldInput, "step_order"> & { step_order?: number }) => {
    const name = field.name || suggestApiKeyFromLabel(field.label, taken);
    taken.add(name);
    fields.push({ ...field, name, step_order: order });
    order += 1;
  };

  add({ type: "section", name: "section_parties", label: "Supplier & shipping" });
  add(
    withRow(
      {
        type: "textarea",
        name: "supplier",
        label: "Supplier",
        validation: { required: true, placeholder: "Vendor name and address" },
      },
      PO_ROW_IDS.parties,
      0,
      2,
    ),
  );
  add(
    withRow(
      {
        type: "textarea",
        name: "ship_to",
        label: "Ship to",
        validation: { required: true },
      },
      PO_ROW_IDS.parties,
      1,
      2,
    ),
  );
  add({ type: "section", name: "section_terms", label: "Order details" });
  add(
    withRow(
      {
        type: "date",
        name: "delivery_date",
        label: "Delivery date",
        validation: { required: true },
      },
      PO_ROW_IDS.orderMeta,
      0,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "text",
        name: "payment_terms",
        label: "Terms",
        validation: { required: true, placeholder: "e.g. 50% DP / 50% FP" },
      },
      PO_ROW_IDS.orderMeta,
      1,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "text",
        name: "currency_code",
        label: "Currency",
        validation: { required: true, default: "PHP" },
      },
      PO_ROW_IDS.orderMeta,
      2,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "number",
        name: "exchange_rate",
        label: "Exchange rate",
        validation: { required: true, default: "1" },
      },
      PO_ROW_IDS.orderMeta,
      3,
      4,
    ),
  );
  add({ type: "section", name: "section_line_items", label: "Line items" });
  add({
    type: "grid",
    name: PO_GRID_FIELD_NAME,
    label: "Line items",
    validation: { required: true },
    options: { columns: PO_GRID_COLUMNS },
  });
  add({ type: "section", name: "section_tax_summary", label: "Tax summary" });
  add(
    currencyComputed(
      "VATable amount",
      "vatable_amount",
      order,
      { operation: "sum_grid_column", source_field: PO_GRID_FIELD_NAME, column: "Amount" },
      "Auto-calculated from line item amounts.",
    ),
  );
  add({
    type: "currency",
    name: "vat_exempt_amount",
    label: "VAT-exempt amount",
    validation: { default: "0.00" },
  });
  add({
    type: "currency",
    name: "zero_rated_amount",
    label: "Zero-rated amount",
    validation: { default: "0.00" },
  });
  add({
    type: "number",
    name: "vat_rate",
    label: "VAT rate (%)",
    validation: { required: true, default: "12", help_text: "Standard PH VAT is 12%." },
  });
  add(
    currencyComputed(
      "VAT amount",
      "vat_amount",
      order,
      { operation: "percent_of", source_field: "vatable_amount", rate_field: "vat_rate" },
      "Auto-calculated from VATable sales × VAT rate.",
    ),
  );
  add(
    currencyComputed(
      "Total (VAT inclusive)",
      "total_vat_inclusive",
      order,
      {
        operation: "add_fields",
        fields: ["vatable_amount", "vat_exempt_amount", "zero_rated_amount", "vat_amount"],
      },
      "Auto-calculated total before header discount.",
    ),
  );
  add({
    type: "currency",
    name: "less_discount",
    label: "Less: Discount",
    validation: { default: "0.00", help_text: "Header-level discount (not per line)." },
  });
  add(
    currencyComputed(
      "Total amount",
      "grand_total",
      order,
      { operation: "subtract_fields", left_field: "total_vat_inclusive", right_field: "less_discount" },
      "Auto-calculated: Total (VAT inclusive) − Less discount.",
    ),
  );
  add({ type: "section", name: "section_signatures", label: "Signatures" });
  add({
    type: "text",
    name: "prepared_by",
    label: "Prepared by",
    validation: { help_text: "Filled automatically from requestor on submit when empty." },
  });

  return {
    fields: ensurePurchaseOrderFieldLayouts(fields, buildPurchaseOrderFormMetadata(startIndex)),
    metadata: buildPurchaseOrderFormMetadata(startIndex),
  };
}

export function isPurchaseOrderPrintTemplate(template: Record<string, unknown> | null | undefined): boolean {
  return String(template?.layout_kind ?? "") === PO_PRINT_TEMPLATE_KIND;
}

export function isPurchaseOrderFormMetadata(metadata: Record<string, unknown> | null | undefined): boolean {
  return String(metadata?.print_template_kind ?? "") === PO_PRINT_TEMPLATE_KIND;
}
