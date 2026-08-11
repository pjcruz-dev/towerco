import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export const E_APPROVAL_FIELD_TYPES = [
  "text",
  "textarea",
  "email",
  "phone",
  "url",
  "number",
  "currency",
  "date",
  "date_range",
  "select",
  "radio",
  "checkbox",
  "matrix",
  "size_matrix",
  "checklist_matrix",
  "approver",
  "approver_list",
  "file",
  "camera",
  "signature",
  "rating",
  "location",
  "tags",
  "grid",
  "section",
  "divider",
  "page_break",
  "instruction",
] as const;

export type EApprovalFieldType = (typeof E_APPROVAL_FIELD_TYPES)[number];

export const E_APPROVAL_FIELD_TYPE_LABELS: Record<EApprovalFieldType, string> = {
  text: "Short text",
  textarea: "Long text",
  email: "Email",
  phone: "Phone",
  url: "URL",
  number: "Number",
  currency: "Currency",
  date: "Date",
  date_range: "Date range",
  select: "Dropdown list",
  radio: "Radio group",
  checkbox: "Checkbox",
  matrix: "Yes/No matrix",
  size_matrix: "Size matrix",
  checklist_matrix: "Checklist matrix",
  approver: "Approver picker",
  approver_list: "Approver list (multi)",
  file: "File upload",
  camera: "Camera / photo capture",
  signature: "Signature",
  rating: "Rating",
  location: "Location",
  tags: "Tags",
  grid: "Grid / line items",
  section: "Section heading",
  divider: "Divider",
  page_break: "Page break",
  instruction: "Instruction / notice",
};

/** Quick-add palette shown as buttons in the form builder. */
export const E_APPROVAL_FIELD_PALETTE_PRIMARY: EApprovalFieldType[] = [
  "text",
  "textarea",
  "number",
  "date",
  "date_range",
  "select",
  "approver",
  "approver_list",
];

/** Grouped options for the consolidated “Add field” control. */
export const E_APPROVAL_FIELD_ADD_GROUPS: { label: string; types: EApprovalFieldType[] }[] = [
  {
    label: "Common",
    types: ["text", "textarea", "email", "phone", "url", "number", "date", "date_range", "select", "approver", "approver_list"],
  },
  { label: "Choices & structure", types: ["radio", "checkbox", "matrix", "size_matrix", "checklist_matrix", "rating", "tags", "section", "divider", "page_break", "instruction", "grid"] },
  { label: "Advanced", types: ["currency", "file", "signature", "location"] },
  { label: "Field capture", types: ["camera"] },
];

export function formatEApprovalFieldTypeLabel(type: string): string {
  if ((E_APPROVAL_FIELD_TYPES as readonly string[]).includes(type)) {
    return E_APPROVAL_FIELD_TYPE_LABELS[type as EApprovalFieldType];
  }

  return type;
}

export const E_APPROVAL_STEP_TYPES = [
  { value: "user", label: "Fixed user" },
  { value: "field", label: "From approver field" },
  { value: "user_list", label: "From approver list (dynamic N)" },
  { value: "field_map", label: "Mapped field value" },
  { value: "manager", label: "Direct manager (Entra ID)" },
  { value: "role", label: "Tenant role (first approver)" },
] as const;

export function defaultFieldForType(type: EApprovalFieldType, index: number): EApprovalFormFieldInput {
  const label =
    type === "section"
      ? "Section"
      : type === "divider"
        ? "—"
        : type === "page_break"
          ? "Page break"
          : type === "instruction"
            ? "Instructions"
            : `${formatEApprovalFieldTypeLabel(type)} ${index + 1}`;

  const name =
    type === "section"
      ? `section_${index + 1}`
      : type === "divider"
        ? `divider_${index + 1}`
        : type === "page_break"
          ? `page_break_${index + 1}`
          : type === "instruction"
            ? `instruction_${index + 1}`
            : type === "grid"
              ? `grid_${index + 1}`
              : `field_${index + 1}`;

  const base = {
    type,
    name,
    label,
    step_order: index + 1,
  };

  if (type === "select" || type === "radio" || type === "checkbox") {
    return {
      ...base,
      options: { choices: [{ value: "a", label: "Option A" }, { value: "b", label: "Option B" }] },
    };
  }

  if (type === "section") {
    return { ...base, name: `section_${index + 1}`, label: "Section" };
  }

  if (type === "divider") {
    return { ...base, name: `divider_${index + 1}`, label: "—" };
  }

  if (type === "page_break") {
    return { ...base, name: `page_break_${index + 1}`, label: "Page break" };
  }

  if (type === "instruction") {
    return {
      ...base,
      name: `instruction_${index + 1}`,
      label: "Instructions",
      options: {
        body: "a. Check the actual site condition and take photos where needed.\nb. Confirm required plans and documents with the lessor or building owner.",
      },
    };
  }

  if (type === "grid") {
    return {
      ...base,
      name: `grid_${index + 1}`,
      label: "Line items",
      options: {
        columns: [
          { label: "Description", type: "text" },
          { label: "Amount", type: "currency" },
        ],
      },
    };
  }

  if (type === "matrix") {
    return {
      ...base,
      name: `matrix_${index + 1}`,
      label: "Does it require?",
      options: {
        rows: [
          { value: "a", label: "A. Cut and Fill" },
          { value: "b", label: "B. Slope Protection" },
        ],
        columns: [
          { value: "yes", label: "Yes" },
          { value: "no", label: "No" },
        ],
      },
    };
  }

  if (type === "size_matrix") {
    return {
      ...base,
      name: `size_matrix_${index + 1}`,
      label: "Building components",
      options: {
        rows: [
          { value: "roofdeck", label: "Roofdeck", input: "size" },
          { value: "elevator_shaft", label: "Elevator Shaft", input: "size" },
          { value: "water_tank", label: "Water Tank", input: "size" },
          { value: "wall", label: "Wall", input: "size" },
          { value: "other", label: "Other (specify)", input: "text" },
          { value: "existing_utilities", label: "Existing Utilities", input: "text" },
        ],
      },
    };
  }

  if (type === "checklist_matrix") {
    return {
      ...base,
      name: `checklist_matrix_${index + 1}`,
      label: "Cost application",
      options: {
        row_select_label: "Cost Application",
        rows: [
          { value: "saq_site_survey", label: "SAQ-Site Survey" },
          { value: "saq_permitting", label: "SAQ-Permitting" },
          { value: "saq_soil_testing", label: "SAQ Soil Testing" },
          { value: "cme_materials", label: "CME-Materials" },
          { value: "cme_labor", label: "CME-Labor" },
          { value: "logistics", label: "Logistics" },
          { value: "various_department", label: "Various Department" },
          { value: "finance_and_accounting", label: "Finance and Accounting" },
          { value: "others", label: "Others" },
        ],
        columns: [
          { value: "project_site_no", label: "Project Site No", type: "text" },
          { value: "ref_no", label: "Ref No", type: "text" },
          { value: "or_no", label: "OR No.", type: "text" },
        ],
      },
    };
  }

  if (type === "rating") {
    return { ...base, options: { max_stars: 5 } };
  }

  if (type === "tags") {
    return { ...base, options: { tag_suggestions: [], allow_custom: true } };
  }

  if (type === "location") {
    return { ...base, options: { allow_geolocation: true } };
  }

  if (type === "file") {
    return {
      ...base,
      validation: {
        allowedFileTypes: ["jpeg", "png", "pdf"],
        maxFiles: 5,
      },
    };
  }

  if (type === "camera") {
    return {
      ...base,
      name: `camera_${index + 1}`,
      label: "Site photos",
      options: {
        capture_mode: "camera",
        min: 1,
        max: 12,
        geotag: true,
        caption: true,
        slots: [],
      },
      validation: {
        required: true,
      },
    };
  }

  return base;
}

export {
  optionsFromEditorJson,
  optionsToEditorJson,
  parseGridColumnDefs,
  parseGridColumns,
  parseSelectChoices,
  resolveFieldDisplayLabel,
  setGridColumnDefs,
  setGridColumns,
  type GridColumnDef,
  type GridColumnType,
} from "@/modules/e-approval/field-options";
