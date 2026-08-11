import { suggestApiKeyFromLabel } from "@/modules/e-approval/field-api-key";
import type { GridColumnDef } from "@/modules/e-approval/field-options";
import {
  PR_ROW_IDS,
  PR_PRINT_TEMPLATE_KIND,
  buildPurchaseRequisitionFormMetadata,
  ensurePurchaseRequisitionFieldLayouts,
  isPurchaseRequisitionFormMetadata,
} from "@/modules/e-approval/procurement-document-layout";
import { layoutWidthForRowColumns, patchFieldLayout } from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export { PR_PRINT_TEMPLATE_KIND, isPurchaseRequisitionFormMetadata } from "@/modules/e-approval/procurement-document-layout";

export const PR_GRID_FIELD_NAME = "line_items";

export const PR_GRID_COLUMNS: GridColumnDef[] = [
  { label: "Description", type: "text" },
  { label: "Qty", type: "number" },
  { label: "Unit price", type: "currency" },
  { label: "Amount", type: "currency" },
];

export const PR_METADATA_JSON = {
  print_template_kind: PR_PRINT_TEMPLATE_KIND,
  form_family: "purchase_requisition",
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

export function buildPurchaseRequisitionPrintTemplate(): Record<string, unknown> {
  return {
    layout_kind: PR_PRINT_TEMPLATE_KIND,
    page: { size: "A4", marginMm: 10 },
    header: {
      showLogo: true,
      title: "Purchase Requisition",
      showDocumentNo: true,
      showStatus: true,
      showDate: true,
      showRequestor: true,
    },
    footer: {
      showApprovalHistory: true,
      showRequestorSignature: true,
      showPageNumbers: true,
      text: "Generated from TowerOS E-Approval",
    },
    blocks: {
      requestor_row: ["requisition_title"],
      meta_row: ["department", "urgency"],
      line_items_grid: PR_GRID_FIELD_NAME,
      totals: ["estimated_total"],
      justification: ["justification"],
      signatures: ["requested_by"],
    },
  };
}

export function buildPurchaseRequisitionTemplateFields(
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

  add({ type: "section", name: "section_header", label: "Requisition header" });
  add(
    withRow(
      {
        type: "text",
        name: "requisition_title",
        label: "Title / summary",
        validation: { required: true },
      },
      PR_ROW_IDS.header,
      0,
      2,
    ),
  );
  add(
    withRow(
      {
        type: "text",
        name: "requested_by",
        label: "Requested by",
        validation: { help_text: "Filled from requestor on submit when empty." },
      },
      PR_ROW_IDS.header,
      1,
      2,
    ),
  );
  add({ type: "section", name: "section_meta", label: "Requisition details" });
  add(
    withRow(
      {
        type: "select",
        name: "department",
        label: "Department",
        validation: { required: true },
        options: {
          choices: [
            { value: "operations", label: "Operations" },
            { value: "it", label: "IT" },
            { value: "network", label: "Network" },
            { value: "facilities", label: "Facilities" },
          ],
        },
      },
      PR_ROW_IDS.meta,
      0,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "select",
        name: "urgency",
        label: "Urgency",
        validation: { required: true },
        options: {
          choices: [
            { value: "normal", label: "Normal" },
            { value: "urgent", label: "Urgent" },
          ],
        },
      },
      PR_ROW_IDS.meta,
      1,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "select",
        name: "currency",
        label: "Currency",
        validation: { required: true, default: "PHP" },
        options: {
          choices: [
            { value: "PHP", label: "PHP" },
            { value: "USD", label: "USD" },
          ],
        },
      },
      PR_ROW_IDS.meta,
      2,
      4,
    ),
  );
  add(
    withRow(
      {
        type: "date",
        name: "needed_by",
        label: "Needed by",
        validation: { required: true },
      },
      PR_ROW_IDS.meta,
      3,
      4,
    ),
  );
  add({ type: "section", name: "section_line_items", label: "Line items" });
  add({
    type: "grid",
    name: PR_GRID_FIELD_NAME,
    label: "Line items",
    validation: { required: true },
    options: { columns: PR_GRID_COLUMNS },
  });
  add({ type: "section", name: "section_totals", label: "Totals" });
  add({
    type: "currency",
    name: "estimated_total",
    label: "Estimated total",
    validation: { required: true },
    options: {
      read_only: true,
      computed_from: {
        operation: "sum_grid_lines",
        source_field: PR_GRID_FIELD_NAME,
        quantity_column: "Qty",
        amount_column: "Unit price",
      },
    },
  });
  add({ type: "section", name: "section_justification", label: "Justification" });
  add({
    type: "textarea",
    name: "justification",
    label: "Business justification",
    validation: { required: true },
  });
  add({
    type: "file",
    name: "quotes",
    label: "Quotes / specifications",
  });

  return {
    fields: ensurePurchaseRequisitionFieldLayouts(fields, buildPurchaseRequisitionFormMetadata(startIndex)),
    metadata: buildPurchaseRequisitionFormMetadata(startIndex),
  };
}

export function isPurchaseRequisitionPrintTemplate(template: Record<string, unknown> | null | undefined): boolean {
  return String(template?.layout_kind ?? "") === PR_PRINT_TEMPLATE_KIND;
}
