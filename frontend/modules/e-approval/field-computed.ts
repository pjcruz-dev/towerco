import { fieldOptionsToRecord, parseGridColumnDefs, parseGridValue } from "@/modules/e-approval/field-options";
import { applyGridRowFormulasToValues } from "@/modules/e-approval/grid-row-formulas";
import { formatComputedFieldAmount, parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type GridSumColumnOperation = {
  operation: "sum_grid_column";
  source_field: string;
  column?: string;
  column_index?: number;
};

export type GridLineTotalOperation = {
  operation: "sum_grid_lines";
  source_field: string;
  quantity_column?: string;
  quantity_column_index?: number;
  amount_column?: string;
  amount_column_index?: number;
};

export type GridLineNetTotalOperation = {
  operation: "sum_grid_lines_net";
  source_field: string;
  quantity_column?: string;
  amount_column?: string;
  discount_column?: string;
};

export type PercentOfOperation = {
  operation: "percent_of";
  source_field: string;
  rate_field?: string;
  rate?: number;
};

export type SubtractFieldsOperation = {
  operation: "subtract_fields";
  left_field: string;
  right_field: string;
};

export type AddFieldsOperation = {
  operation: "add_fields";
  fields: string[];
};

export type FieldComputedOperation =
  | GridSumColumnOperation
  | GridLineTotalOperation
  | GridLineNetTotalOperation
  | PercentOfOperation
  | SubtractFieldsOperation
  | AddFieldsOperation;

type ConventionRule = {
  totalField: string;
  config: FieldComputedOperation;
};

const CONVENTION_RULES: ConventionRule[] = [
  {
    totalField: "total_reimbursement",
    config: {
      operation: "sum_grid_column",
      source_field: "expense_lines",
      column: "Amount",
    },
  },
  {
    totalField: "estimated_total",
    config: {
      operation: "sum_grid_lines",
      source_field: "line_items",
      quantity_column: "Qty",
      amount_column: "Unit price",
    },
  },
  {
    totalField: "total_amount",
    config: {
      operation: "sum_grid_lines",
      source_field: "line_items",
      quantity_column: "Qty",
      amount_column: "Unit price",
    },
  },
  {
    totalField: "vatable_amount",
    config: {
      operation: "sum_grid_column",
      source_field: "line_items",
      column: "Amount",
    },
  },
  {
    totalField: "vat_amount",
    config: {
      operation: "percent_of",
      source_field: "vatable_amount",
      rate_field: "vat_rate",
    },
  },
  {
    totalField: "total_vat_inclusive",
    config: {
      operation: "add_fields",
      fields: ["vatable_amount", "vat_exempt_amount", "zero_rated_amount", "vat_amount"],
    },
  },
];

function parseExplicitComputedConfig(field: EApprovalFormFieldInput): FieldComputedOperation | null {
  const options = fieldOptionsToRecord(field);
  const raw = options.computed_from;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }

  const record = raw as Record<string, unknown>;
  const operation = String(record.operation ?? "").trim();

  if (operation === "sum_grid_column") {
    const sourceField = String(record.source_field ?? "").trim();
    if (!sourceField) {
      return null;
    }

    return {
      operation: "sum_grid_column",
      source_field: sourceField,
      column: typeof record.column === "string" ? record.column : undefined,
      column_index: typeof record.column_index === "number" ? record.column_index : undefined,
    };
  }

  if (operation === "sum_grid_lines" || operation === "sum_grid_lines_net") {
    const sourceField = String(record.source_field ?? "").trim();
    if (!sourceField) {
      return null;
    }

    if (operation === "sum_grid_lines_net") {
      return {
        operation: "sum_grid_lines_net",
        source_field: sourceField,
        quantity_column: typeof record.quantity_column === "string" ? record.quantity_column : undefined,
        amount_column: typeof record.amount_column === "string" ? record.amount_column : undefined,
        discount_column: typeof record.discount_column === "string" ? record.discount_column : undefined,
      };
    }

    return {
      operation: "sum_grid_lines",
      source_field: sourceField,
      quantity_column: typeof record.quantity_column === "string" ? record.quantity_column : undefined,
      quantity_column_index:
        typeof record.quantity_column_index === "number" ? record.quantity_column_index : undefined,
      amount_column: typeof record.amount_column === "string" ? record.amount_column : undefined,
      amount_column_index:
        typeof record.amount_column_index === "number" ? record.amount_column_index : undefined,
    };
  }

  if (operation === "percent_of") {
    const sourceField = String(record.source_field ?? "").trim();
    if (!sourceField) {
      return null;
    }

    return {
      operation: "percent_of",
      source_field: sourceField,
      rate_field: typeof record.rate_field === "string" ? record.rate_field : undefined,
      rate: typeof record.rate === "number" ? record.rate : undefined,
    };
  }

  if (operation === "subtract_fields") {
    const leftField = String(record.left_field ?? "").trim();
    const rightField = String(record.right_field ?? "").trim();
    if (!leftField || !rightField) {
      return null;
    }

    return {
      operation: "subtract_fields",
      left_field: leftField,
      right_field: rightField,
    };
  }

  if (operation === "add_fields") {
    const fields = Array.isArray(record.fields)
      ? record.fields.map((item) => String(item ?? "").trim()).filter(Boolean)
      : [];
    if (fields.length === 0) {
      return null;
    }

    return { operation: "add_fields", fields };
  }

  return null;
}

function resolveConvention(fieldName: string, fields: EApprovalFormFieldInput[]): FieldComputedOperation | null {
  const rule = CONVENTION_RULES.find((entry) => entry.totalField === fieldName);
  if (!rule) {
    return null;
  }

  if ("source_field" in rule.config) {
    const gridExists = fields.some(
      (field) => field.name === rule.config.source_field && field.type === "grid",
    );
    if (!gridExists) {
      return null;
    }
  }

  if (rule.config.operation === "subtract_fields") {
    const leftExists = fields.some((field) => field.name === rule.config.left_field);
    const rightExists = fields.some((field) => field.name === rule.config.right_field);
    return leftExists && rightExists ? rule.config : null;
  }

  if (rule.config.operation === "add_fields") {
    const allExist = rule.config.fields.every((name) => fields.some((field) => field.name === name));
    return allExist ? rule.config : null;
  }

  if (rule.config.operation === "percent_of") {
    const sourceExists = fields.some((field) => field.name === rule.config.source_field);
    return sourceExists ? rule.config : null;
  }

  return rule.config;
}

export function resolveFieldComputedConfig(
  field: EApprovalFormFieldInput,
  fields: EApprovalFormFieldInput[],
): FieldComputedOperation | null {
  if (!["currency", "number"].includes(field.type)) {
    return null;
  }

  return parseExplicitComputedConfig(field) ?? resolveConvention(field.name, fields);
}

export function isFieldComputedReadOnly(field: EApprovalFormFieldInput, fields: EApprovalFormFieldInput[]): boolean {
  const options = fieldOptionsToRecord(field);
  if (options.read_only === true) {
    return true;
  }

  return resolveFieldComputedConfig(field, fields) !== null;
}

function findGridField(fields: EApprovalFormFieldInput[], name: string): EApprovalFormFieldInput | null {
  const field = fields.find((entry) => entry.name === name && entry.type === "grid");
  return field ?? null;
}

function resolveColumnIndex(
  columns: ReturnType<typeof parseGridColumnDefs>,
  label: string | undefined,
  index: number | undefined,
): number | null {
  if (typeof index === "number" && index >= 0 && index < columns.length) {
    return index;
  }

  if (!label) {
    return null;
  }

  const normalized = label.trim().toLowerCase();
  const match = columns.findIndex((column) => column.label.trim().toLowerCase() === normalized);
  return match >= 0 ? match : null;
}

function parseNumericCell(raw: string | undefined): number {
  const parsed = parseSubmissionAmount(raw ?? "");
  return parsed ?? 0;
}

function readNumericField(values: Record<string, string>, fieldName: string): number {
  return parseNumericCell(values[fieldName]);
}

function computeGridSumColumn(
  gridField: EApprovalFormFieldInput,
  gridRaw: string,
  config: GridSumColumnOperation,
): number {
  const columns = parseGridColumnDefs(gridField);
  const columnIndex =
    resolveColumnIndex(columns, config.column, config.column_index) ??
    columns.findIndex((column) => column.type === "currency");

  if (columnIndex < 0) {
    return 0;
  }

  const grid = parseGridValue(
    gridRaw,
    columns.length,
    columns.map((column) => column.label),
  );
  return grid.rows.reduce((sum, row) => sum + parseNumericCell(row[String(columnIndex)]), 0);
}

function computeGridLineTotals(
  gridField: EApprovalFormFieldInput,
  gridRaw: string,
  config: GridLineTotalOperation,
): number {
  const columns = parseGridColumnDefs(gridField);
  const qtyIndex = resolveColumnIndex(columns, config.quantity_column, config.quantity_column_index);
  const amountIndex = resolveColumnIndex(columns, config.amount_column, config.amount_column_index);

  if (qtyIndex === null || amountIndex === null) {
    return 0;
  }

  const grid = parseGridValue(
    gridRaw,
    columns.length,
    columns.map((column) => column.label),
  );
  return grid.rows.reduce((sum, row) => {
    const qty = parseNumericCell(row[String(qtyIndex)]);
    const amount = parseNumericCell(row[String(amountIndex)]);
    return sum + qty * amount;
  }, 0);
}

function computeGridLineNetTotals(
  gridField: EApprovalFormFieldInput,
  gridRaw: string,
  config: GridLineNetTotalOperation,
): number {
  const columns = parseGridColumnDefs(gridField);
  const qtyIndex = resolveColumnIndex(columns, config.quantity_column, undefined) ?? resolveColumnIndex(columns, "Qty", undefined);
  const unitIndex =
    resolveColumnIndex(columns, config.amount_column, undefined) ??
    resolveColumnIndex(columns, "Unit price", undefined);
  const discountIndex = resolveColumnIndex(columns, config.discount_column, undefined) ?? resolveColumnIndex(columns, "Discount", undefined);

  if (qtyIndex === null || unitIndex === null) {
    return 0;
  }

  const grid = parseGridValue(
    gridRaw,
    columns.length,
    columns.map((column) => column.label),
  );
  return grid.rows.reduce((sum, row) => {
    const qty = parseNumericCell(row[String(qtyIndex)]);
    const unit = parseNumericCell(row[String(unitIndex)]);
    const discount = discountIndex === null ? 0 : parseNumericCell(row[String(discountIndex)]);
    return sum + Math.max(0, qty * unit - discount);
  }, 0);
}

function computeFromConfig(
  config: FieldComputedOperation,
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): number | null {
  if (
    config.operation === "sum_grid_column" ||
    config.operation === "sum_grid_lines" ||
    config.operation === "sum_grid_lines_net"
  ) {
    const gridField = findGridField(fields, config.source_field);
    if (!gridField) {
      return null;
    }

    const gridRaw = values[config.source_field] ?? "";
    if (config.operation === "sum_grid_column") {
      return computeGridSumColumn(gridField, gridRaw, config);
    }
    if (config.operation === "sum_grid_lines_net") {
      return computeGridLineNetTotals(gridField, gridRaw, config);
    }

    return computeGridLineTotals(gridField, gridRaw, config);
  }

  if (config.operation === "percent_of") {
    const base = readNumericField(values, config.source_field);
    const rate =
      config.rate_field !== undefined
        ? readNumericField(values, config.rate_field)
        : (config.rate ?? 0);
    return (base * rate) / 100;
  }

  if (config.operation === "subtract_fields") {
    return readNumericField(values, config.left_field) - readNumericField(values, config.right_field);
  }

  if (config.operation === "add_fields") {
    return config.fields.reduce((sum, fieldName) => sum + readNumericField(values, fieldName), 0);
  }

  return null;
}

export function computeFieldValue(
  field: EApprovalFormFieldInput,
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): string | null {
  const config = resolveFieldComputedConfig(field, fields);
  if (!config) {
    return null;
  }

  const total = computeFromConfig(config, fields, values);
  if (total === null) {
    return null;
  }

  return formatComputedFieldAmount(total);
}

export function applyComputedFieldValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): Record<string, string> {
  let next = applyGridRowFormulasToValues(fields, values);

  for (let pass = 0; pass < 12; pass += 1) {
    let changed = false;

    for (const field of fields) {
      const computed = computeFieldValue(field, fields, next);
      if (computed === null) {
        continue;
      }

      if (next[field.name] !== computed) {
        next[field.name] = computed;
        changed = true;
      }
    }

    if (!changed) {
      break;
    }
  }

  return next;
}

export function computedFieldHelpText(field: EApprovalFormFieldInput, fields: EApprovalFormFieldInput[]): string | undefined {
  if (!isFieldComputedReadOnly(field, fields)) {
    return undefined;
  }

  const config = resolveFieldComputedConfig(field, fields);
  if (!config) {
    return undefined;
  }

  switch (config.operation) {
    case "sum_grid_column":
      return "Auto-calculated from line amounts.";
    case "sum_grid_lines":
      return "Auto-calculated from quantity × unit price on each line.";
    case "sum_grid_lines_net":
      return "Auto-calculated from quantity × unit price minus discount on each line.";
    case "percent_of":
      return "Auto-calculated as a percentage of the source field.";
    case "subtract_fields":
      return "Auto-calculated by subtracting one field from another.";
    case "add_fields":
      return "Auto-calculated by adding multiple fields.";
    default:
      return undefined;
  }
}
