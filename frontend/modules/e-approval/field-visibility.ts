import { isComposeStructuralFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalVisibilityMode = "show_when" | "hide_when";

export type EApprovalVisibilityOperator = "equals" | "not_equals" | "filled" | "empty" | "contains" | "all_filled";

export type EApprovalFieldVisibilityRule = {
  mode: EApprovalVisibilityMode;
  field: string;
  operator: EApprovalVisibilityOperator;
  value?: string;
  /** Every named field must be filled. Used by `all_filled`. */
  fields?: string[];
};

function normalizeOptions(options: EApprovalFormFieldInput["options"]): Record<string, unknown> {
  if (options && typeof options === "object" && !Array.isArray(options)) {
    return { ...(options as Record<string, unknown>) };
  }

  return {};
}

export function parseFieldVisibility(field: EApprovalFormFieldInput): EApprovalFieldVisibilityRule | null {
  const opts = normalizeOptions(field.options);
  const raw = opts.visibility;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }

  const v = raw as Record<string, unknown>;
  const mode = v.mode === "hide_when" ? "hide_when" : v.mode === "show_when" ? "show_when" : null;
  const whenField = typeof v.field === "string" ? v.field.trim() : "";
  const operatorRaw = v.operator;
  const operator: EApprovalVisibilityOperator | null =
    operatorRaw === "equals" ||
    operatorRaw === "not_equals" ||
    operatorRaw === "filled" ||
    operatorRaw === "empty" ||
    operatorRaw === "contains" ||
    operatorRaw === "all_filled"
      ? operatorRaw
      : null;
  const filledFields = Array.isArray(v.fields)
    ? v.fields.map((name) => (typeof name === "string" ? name.trim() : "")).filter((name) => name !== "")
    : [];

  if (mode && operator === "all_filled") {
    const names = filledFields.length > 0 ? filledFields : whenField !== "" ? [whenField] : [];
    if (names.length === 0) {
      return null;
    }
    return { mode, field: names[0] ?? "", operator, fields: names };
  }

  if (!mode || !whenField || !operator) {
    return null;
  }

  const value = typeof v.value === "string" ? v.value : undefined;

  if ((operator === "equals" || operator === "not_equals" || operator === "contains") && (value === undefined || value.trim() === "")) {
    return null;
  }

  return { mode, field: whenField, operator, value };
}

export function patchFieldVisibility(
  field: EApprovalFormFieldInput,
  rule: EApprovalFieldVisibilityRule | null,
): Record<string, unknown> {
  const opts = normalizeOptions(field.options);

  if (rule?.operator === "all_filled") {
    const names = (rule.fields ?? [rule.field]).map((name) => name.trim()).filter((name) => name !== "");
    if (!rule || names.length === 0) {
      const { visibility: _removed, ...rest } = opts;
      return { ...rest, visibility: undefined };
    }
    return {
      ...opts,
      visibility: {
        mode: rule.mode,
        operator: "all_filled",
        field: names[0],
        fields: names,
      },
    };
  }

  if (!rule || !rule.field.trim()) {
    const { visibility: _removed, ...rest } = opts;
    return { ...rest, visibility: undefined };
  }

  if (
    (rule.operator === "equals" || rule.operator === "not_equals" || rule.operator === "contains") &&
    (rule.value === undefined || rule.value.trim() === "")
  ) {
    const { visibility: _removed, ...rest } = opts;
    return { ...rest, visibility: undefined };
  }

  const visibility: Record<string, unknown> = {
    mode: rule.mode,
    field: rule.field.trim(),
    operator: rule.operator,
  };
  if (rule.value !== undefined && rule.value !== "") {
    visibility.value = rule.value;
  }

  return { ...opts, visibility };
}

export function fieldSupportsVisibilityRules(type: string): boolean {
  return type === "section" || !isComposeStructuralFieldType(type);
}

export function fieldCanControlVisibility(type: string): boolean {
  return !isComposeStructuralFieldType(type);
}

function conditionMatches(
  operator: EApprovalVisibilityOperator,
  controlValue: string,
  expected?: string,
): boolean {
  const value = controlValue.trim();
  const compare = (expected ?? "").trim();

  switch (operator) {
    case "empty":
      return value === "";
    case "filled":
      return value !== "" && value !== "false";
    case "equals":
      return value.toLowerCase() === compare.toLowerCase();
    case "not_equals":
      return value.toLowerCase() !== compare.toLowerCase();
    case "contains":
      return compare !== "" && value.toLowerCase().includes(compare.toLowerCase());
    default:
      return false;
  }
}

function valueIsFilled(value: string): boolean {
  const trimmed = value.trim();
  return trimmed !== "" && trimmed !== "false";
}

export function evaluateFieldVisibility(
  rule: EApprovalFieldVisibilityRule,
  values: Record<string, string>,
): boolean {
  if (rule.operator === "all_filled") {
    const names = rule.fields && rule.fields.length > 0 ? rule.fields : [rule.field];
    const matches = names.every((name) => valueIsFilled(values[name] ?? ""));
    return rule.mode === "show_when" ? matches : !matches;
  }

  const controlValue = values[rule.field] ?? "";
  const matches = conditionMatches(rule.operator, controlValue, rule.value);

  return rule.mode === "show_when" ? matches : !matches;
}

/** A section rule hides that heading and every field until the next section. */
export function fieldsPassingVisibility(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): EApprovalFormFieldInput[] {
  let sectionOpen = true;
  const kept: EApprovalFormFieldInput[] = [];
  for (const field of fields) {
    if (field.type === "section") {
      sectionOpen = isFieldVisible(field, values);
      if (sectionOpen) {
        kept.push(field);
      }
      continue;
    }
    if (!sectionOpen || !isFieldVisible(field, values)) {
      continue;
    }
    kept.push(field);
  }
  return kept;
}

export function isFieldVisible(field: EApprovalFormFieldInput, values: Record<string, string>): boolean {
  const rule = parseFieldVisibility(field);
  if (!rule) {
    return true;
  }

  return evaluateFieldVisibility(rule, values);
}

export function visibleFormFields(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): EApprovalFormFieldInput[] {
  return omitRedundantExpenseTotalScalars(fieldsPassingVisibility(fields, values));
}

/**
 * When an expense grid already shows TOTAL EXPENSES, hide scalar Total* fields
 * (still computed/stored for workflow thresholds and cash overage).
 */
export function omitRedundantExpenseTotalScalars(
  fields: EApprovalFormFieldInput[],
): EApprovalFormFieldInput[] {
  const hasExpenseGridWithMoney = fields.some((field) => {
    if (field.type !== "grid") {
      return false;
    }
    const name = field.name.trim().toLowerCase();
    if (name === "expense_lines" || name.includes("expense")) {
      return true;
    }
    const opts =
      field.options && typeof field.options === "object" && !Array.isArray(field.options)
        ? (field.options as Record<string, unknown>)
        : {};
    const columns = Array.isArray(opts.columns) ? opts.columns : [];
    return columns.some((column) => {
      const label =
        column && typeof column === "object" && "label" in column
          ? String((column as { label?: unknown }).label ?? "")
          : String(column ?? "");
      return /^total$/i.test(label.trim()) || /^amount$/i.test(label.trim());
    });
  });

  if (!hasExpenseGridWithMoney) {
    return fields;
  }

  const gridOwnsColumnTotals = fields.some((field) => {
    if (field.type !== "grid" || !field.options || typeof field.options !== "object" || Array.isArray(field.options)) {
      return false;
    }
    const columns = (field.options as { columns?: unknown }).columns;
    if (!Array.isArray(columns)) {
      return false;
    }
    return columns.some(
      (column) =>
        column &&
        typeof column === "object" &&
        (column as { show_total?: unknown }).show_total === true,
    );
  });

  return fields.filter((field) => {
    const key = field.name.trim().toLowerCase();
    const label = field.label.trim().toLowerCase();
    if (field.type === "grid") {
      return true;
    }
    if (
      key === "requested_amount" ||
      key === "total_reimbursement" ||
      key === "vat_total" ||
      key === "non_vatable_total" ||
      key === "vatable_net"
    ) {
      return !gridOwnsColumnTotals;
    }
    // Keep cash overage / advance — only omit document total scalars mirrored by the footer.
    if (key.includes("overage") || key.includes("shortage") || key.includes("cash_advance")) {
      return true;
    }
    if (/^total([_\s-]|$)/.test(key) || /^totals?([_\s-]|$)/.test(key)) {
      return false;
    }
    if (/^totals?\b/.test(label) && (field.type === "currency" || field.type === "number")) {
      return false;
    }
    return true;
  });
}

export const E_APPROVAL_VISIBILITY_OPERATORS: { value: EApprovalVisibilityOperator; label: string }[] = [
  { value: "equals", label: "Equals" },
  { value: "not_equals", label: "Does not equal" },
  { value: "contains", label: "Contains" },
  { value: "filled", label: "Is filled" },
  { value: "empty", label: "Is empty" },
  { value: "all_filled", label: "All of these are filled" },
];
