import { suggestApiKeyFromLabel } from "@/modules/e-approval/field-api-key";
import { catalogFieldDragId } from "@/modules/e-approval/field-layout";
import type { GridColumnDef } from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalFormFieldBundleId = "expense_lines_total" | "reimbursement_expense_lines_total";

export type EApprovalGridColumnPresetId =
  | "cash_advance_forecast"
  | "expense_lines_liquidation"
  | "expense_lines_reimbursement";

export const E_APPROVAL_FORM_FIELD_BUNDLES: {
  id: EApprovalFormFieldBundleId;
  label: string;
  description: string;
}[] = [
  {
    id: "expense_lines_total",
    label: "Liquidation expense lines",
    description: "Sites, expense-type flags, and a total row. A read-only total stores the Amount sum.",
  },
  {
    id: "reimbursement_expense_lines_total",
    label: "Reimbursement expense lines",
    description: "OR number, sites, expense-type flags, and a total row. A read-only total stores the Amount sum.",
  },
];

const VAT_TREATMENT_CHOICES = [
  { value: "vatable", label: "VATable" },
  { value: "non_vatable", label: "Non-VATable" },
];

const EXPENSE_TYPE_CHOICES = [
  { value: "land", label: "Transportation - Land", data: { vatable: true } },
  { value: "sea", label: "Transportation - Sea", data: { vatable: true } },
  { value: "air", label: "Transportation - Air", data: { vatable: true } },
  { value: "gasoline", label: "Gasoline", data: { vatable: true } },
  { value: "lodging", label: "Lodging", data: { vatable: true, hotel: true } },
  { value: "per_diem", label: "Per Diem", data: { vatable: false, per_diem: true } },
  { value: "car_rental", label: "Car Rental", data: { vatable: true } },
  { value: "others", label: "Others", data: { vatable: true } },
];

function siteColumn(label: string): GridColumnDef {
  return {
    label,
    type: "select",
    master_data_key: "sites",
    multiple: true,
    show_fields: ["label", "code", "address", "name"],
    subsidiary_field: "subsidiary",
  };
}

function moneySplitColumns(): GridColumnDef[] {
  return [
    { label: "VAT treatment", type: "select", choices: VAT_TREATMENT_CHOICES, hidden: true },
    { label: "VAT", type: "currency", locked: true, show_total: true },
    { label: "Non-VATable", type: "currency", locked: true, show_total: true },
    { label: "VATable, net of VAT", type: "currency", locked: true, show_total: true },
  ];
}

function expenseLineColumns(kind: "liquidation" | "reimbursement"): GridColumnDef[] {
  const columns: GridColumnDef[] = [
    { label: "From date – To date", type: "date_range", include_time: false },
    siteColumn("From site / location"),
    siteColumn("To site / location"),
    { label: "OR No", type: "text" },
    { label: "Supplier/Payee", type: "text" },
    { label: "TIN", type: "text" },
    { label: "Description", type: "textarea" },
    { label: "Expense type", type: "select", choices: EXPENSE_TYPE_CHOICES },
    { label: "Amount", type: "currency", required: true, show_total: true },
  ];
  if (kind === "liquidation") {
    columns.push(
      { label: "Forecast amount", type: "currency", locked: true },
      { label: "Nature of Activity", type: "text", locked: true },
    );
  }
  columns.push(...moneySplitColumns());
  if (kind === "liquidation") {
    columns.push({ label: "Variance reason", type: "textarea" });
  }
  return columns;
}

function cashAdvanceForecastColumns(): GridColumnDef[] {
  return [
    {
      label: "From date – To date",
      type: "date_range",
      required: true,
      include_time: false,
      date_min: "field",
      date_min_field: "needed_by",
    },
    siteColumn("From site / location"),
    siteColumn("To site / location"),
    { label: "Expense type", type: "select", choices: EXPENSE_TYPE_CHOICES },
    { label: "Amount", type: "currency", required: true, show_total: true },
    { label: "Nature of Activity", type: "text", required: true },
    ...moneySplitColumns(),
  ];
}

export const GRID_COLUMN_PRESETS: Record<EApprovalGridColumnPresetId, { label: string; columns: GridColumnDef[] }> = {
  cash_advance_forecast: {
    label: "Cash advance forecast",
    columns: cashAdvanceForecastColumns(),
  },
  expense_lines_liquidation: {
    label: "Liquidation expense lines",
    columns: expenseLineColumns("liquidation"),
  },
  expense_lines_reimbursement: {
    label: "Reimbursement expense lines",
    columns: expenseLineColumns("reimbursement"),
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
        label: isReimbursement ? "Total expenses" : "Total liquidation amount",
        step_order: startIndex + 1,
        validation: { required: true, help_text: "Auto-calculated from expense lines." },
        options: {
          read_only: true,
          computed_from: {
            operation: "sum_grid_column",
            source_field: gridName,
            column: "Amount",
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
  _bundleId: EApprovalFormFieldBundleId,
  _insertIndex = 0,
): Record<string, unknown> | null {
  return null;
}
