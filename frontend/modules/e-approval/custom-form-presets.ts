import { suggestApiKeyFromLabel } from "@/modules/e-approval/field-api-key";
import { catalogFieldDragId } from "@/modules/e-approval/field-layout";
import type { GridColumnDef } from "@/modules/e-approval/field-options";
import {
  buildPurchaseOrderFormMetadata,
  buildPurchaseOrderTemplateFields,
} from "@/modules/e-approval/purchase-order-template";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalFormFieldBundleId =
  | "expense_lines_total"
  | "reimbursement_expense_lines_total"
  | "po_line_items_total"
  | "purchase_order_full";

export type EApprovalGridColumnPresetId =
  | "expense_lines_liquidation"
  | "expense_lines_reimbursement"
  | "po_line_items";

export const E_APPROVAL_FORM_FIELD_BUNDLES: {
  id: EApprovalFormFieldBundleId;
  label: string;
  description: string;
}[] = [
  {
    id: "expense_lines_total",
    label: "Total + liquidation expense lines",
    description: "Currency total auto-sums the Total column (Land/Sea/Air, lodging, etc.).",
  },
  {
    id: "reimbursement_expense_lines_total",
    label: "Total + reimbursement expense lines",
    description: "Currency total auto-sums the Total column (Landfare, Airfare, Toll Fee, etc.).",
  },
  {
    id: "po_line_items_total",
    label: "Total + PO line items",
    description: "Currency total auto-calculates Qty × unit price per row.",
  },
  {
    id: "purchase_order_full",
    label: "Purchase order (full)",
    description: "PO layout with line items, VAT tax summary, totals, and print template.",
  },
];

export const GRID_COLUMN_PRESETS: Record<EApprovalGridColumnPresetId, { label: string; columns: GridColumnDef[] }> = {
  expense_lines_liquidation: {
    label: "Expense lines of Liquidation",
    columns: [
      { label: "Date", type: "date" },
      { label: "OR No", type: "text" },
      { label: "Supplier/Payee", type: "text" },
      { label: "Description", type: "textarea" },
      { label: "Project Site No.", type: "text" },
      { label: "Transportation - Land", type: "currency" },
      { label: "Transportation - Sea", type: "currency" },
      { label: "Transportation - Air", type: "currency" },
      { label: "Gasoline", type: "currency" },
      { label: "Lodging", type: "currency" },
      { label: "Per Diem", type: "currency" },
      { label: "VAT", type: "currency" },
      { label: "Total", type: "currency" },
    ],
  },
  expense_lines_reimbursement: {
    label: "Expense lines of Reimbursement",
    columns: [
      { label: "Date", type: "date" },
      { label: "OR No", type: "text" },
      { label: "Supplier/Payee", type: "text" },
      { label: "Description", type: "textarea" },
      { label: "Project Site No.", type: "text" },
      { label: "Landfare", type: "currency" },
      { label: "Airfare", type: "currency" },
      { label: "Gasoline", type: "currency" },
      { label: "Toll Fee", type: "currency" },
      { label: "Per Diem", type: "currency" },
      { label: "VAT", type: "currency" },
      { label: "Total", type: "currency" },
    ],
  },
  po_line_items: {
    label: "PO line items",
    columns: [
      { label: "Description", type: "text" },
      { label: "Qty", type: "number" },
      { label: "Unit price", type: "currency" },
    ],
  },
};

/** @deprecated Use expense_lines_liquidation */
export const GRID_COLUMN_PRESETS_LEGACY_EXPENSE_LINES = GRID_COLUMN_PRESETS.expense_lines_liquidation;

function uniqueName(label: string, taken: Set<string>): string {
  return suggestApiKeyFromLabel(label, taken);
}

export function buildFormFieldBundle(
  bundleId: EApprovalFormFieldBundleId,
  startIndex: number,
  existingApiKeys: Set<string>,
): EApprovalFormFieldInput[] | null {
  const taken = new Set(existingApiKeys);

  if (bundleId === "expense_lines_total" || bundleId === "reimbursement_expense_lines_total") {
    const isReimbursement = bundleId === "reimbursement_expense_lines_total";
    const preset = isReimbursement
      ? GRID_COLUMN_PRESETS.expense_lines_reimbursement
      : GRID_COLUMN_PRESETS.expense_lines_liquidation;
    const totalName = uniqueName(isReimbursement ? "total_reimbursement" : "total_amount", taken);
    taken.add(totalName);
    const gridName = uniqueName("expense_lines", taken);

    return [
      {
        type: "currency",
        name: totalName,
        label: isReimbursement ? "Total reimbursement amount" : "Total liquidation amount",
        step_order: startIndex + 1,
        validation: { required: true, help_text: "Auto-calculated from expense lines." },
        options: {
          read_only: true,
          computed_from: {
            operation: "sum_grid_column",
            source_field: gridName,
            column: "Total",
          },
        },
      },
      {
        type: "grid",
        name: gridName,
        label: preset.label,
        step_order: startIndex + 2,
        validation: { required: true },
        options: { columns: preset.columns },
      },
    ];
  }

  if (bundleId === "po_line_items_total") {
    const totalName = uniqueName("estimated_total", taken);
    taken.add(totalName);
    const gridName = uniqueName("line_items", taken);

    return [
      {
        type: "currency",
        name: totalName,
        label: "Estimated total",
        step_order: startIndex + 1,
        validation: { required: true, help_text: "Auto-calculated from line items." },
        options: {
          read_only: true,
          computed_from: {
            operation: "sum_grid_lines",
            source_field: gridName,
            quantity_column: "Qty",
            amount_column: "Unit price",
          },
        },
      },
      {
        type: "grid",
        name: gridName,
        label: "Line items",
        step_order: startIndex + 2,
        validation: { required: true },
        options: { columns: GRID_COLUMN_PRESETS.po_line_items.columns },
      },
    ];
  }

  if (bundleId === "purchase_order_full") {
    return buildPurchaseOrderTemplateFields(startIndex, taken).fields;
  }

  return null;
}

export function isFormFieldBundleCatalogId(catalogId: string): catalogId is `bundle:${EApprovalFormFieldBundleId}` {
  return catalogId.startsWith("bundle:");
}

export function parseFormFieldBundleCatalogId(catalogId: string): EApprovalFormFieldBundleId | null {
  if (!isFormFieldBundleCatalogId(catalogId)) {
    return null;
  }

  const id = catalogId.slice("bundle:".length) as EApprovalFormFieldBundleId;
  return E_APPROVAL_FORM_FIELD_BUNDLES.some((bundle) => bundle.id === id) ? id : null;
}

export function formFieldBundleCatalogDragId(bundleId: EApprovalFormFieldBundleId): string {
  return catalogFieldDragId(`bundle:${bundleId}`);
}

export function getFormFieldBundleMetadataPatch(
  bundleId: EApprovalFormFieldBundleId,
  insertIndex = 0,
): Record<string, unknown> | null {
  if (bundleId === "purchase_order_full") {
    return buildPurchaseOrderFormMetadata(insertIndex);
  }

  return null;
}
