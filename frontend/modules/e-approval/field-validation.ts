import { gridHasContent, parseGridColumns, parseGridValue, parseSelectChoices } from "@/modules/e-approval/field-options";
import {
  isCheckboxMulti,
  isCheckboxTruthy,
  parseCheckboxValues,
  validateCheckboxCompanions,
} from "@/modules/e-approval/field-checkbox";
import {
  matrixHasCompleteAnswers,
  parseMatrixFieldOptions,
  parseMatrixValue,
} from "@/modules/e-approval/field-matrix";
import {
  parseChecklistMatrixFieldOptions,
  validateChecklistMatrixValue,
} from "@/modules/e-approval/field-checklist-matrix";
import {
  parseSizeMatrixRows,
  parseSizeMatrixValue,
  sizeMatrixHasCompleteAnswers,
  sizeMatrixRowInput,
  sizeMatrixRowIsComplete,
} from "@/modules/e-approval/field-size-matrix";
import { isApproverSelectionValid, type ApproverOption } from "@/modules/e-approval/approver-field-support";
import {
  parseCameraFieldOptions,
  validateCameraSelection,
  type EApprovalCameraPhotoMetadata,
} from "@/modules/e-approval/field-camera-options";
import { validateSelectedFiles } from "@/modules/e-approval/field-file-options";
import { isFieldVisible } from "@/modules/e-approval/field-visibility";
import {
  parseLocationValue,
  parseRatingMaxStars,
  parseTagsValue,
  isSignatureDataUrl,
  validateDateRangeValue,
} from "@/modules/e-approval/field-type-options";
import { isComposeStructuralFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalFieldValidationRules = {
  required?: boolean;
  placeholder?: string;
  max_length?: number;
  default?: string;
  help_text?: string;
};

export function parseFieldValidation(field: EApprovalFormFieldInput): EApprovalFieldValidationRules {
  const raw = field.validation;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return {};
  }

  const v = raw as Record<string, unknown>;

  return {
    required: v.required === true,
    placeholder: typeof v.placeholder === "string" ? v.placeholder : undefined,
    max_length:
      typeof v.max_length === "number" && Number.isFinite(v.max_length) ? Math.max(1, v.max_length) : undefined,
    default: typeof v.default === "string" ? v.default : undefined,
    help_text: typeof v.help_text === "string" ? v.help_text : undefined,
  };
}

export function fieldHelpText(field: EApprovalFormFieldInput): string | undefined {
  return parseFieldValidation(field).help_text;
}

function readValidationRecord(field: EApprovalFormFieldInput): Record<string, unknown> {
  const raw = field.validation;
  return raw && typeof raw === "object" && !Array.isArray(raw) ? { ...(raw as Record<string, unknown>) } : {};
}

export function patchFieldValidation(
  field: EApprovalFormFieldInput,
  patch: Partial<EApprovalFieldValidationRules>,
): Record<string, unknown> | null {
  const current = parseFieldValidation(field);
  const next: EApprovalFieldValidationRules = { ...current, ...patch };

  if (patch.required !== undefined) {
    next.required = patch.required;
  }
  if (patch.placeholder !== undefined) {
    next.placeholder = patch.placeholder.trim() || undefined;
  }
  if (patch.max_length !== undefined) {
    next.max_length = patch.max_length > 0 ? patch.max_length : undefined;
  }
  if (patch.default !== undefined) {
    next.default = patch.default;
  }
  if (patch.help_text !== undefined) {
    next.help_text = patch.help_text.trim() || undefined;
  }

  const out: Record<string, unknown> = { ...readValidationRecord(field) };

  if (next.required) {
    out.required = true;
  } else {
    delete out.required;
  }

  if (next.placeholder) {
    out.placeholder = next.placeholder;
  } else {
    delete out.placeholder;
  }

  if (next.max_length) {
    out.max_length = next.max_length;
  } else {
    delete out.max_length;
  }

  if (next.default !== undefined && next.default !== "") {
    out.default = next.default;
  } else {
    delete out.default;
  }

  if (next.help_text) {
    out.help_text = next.help_text;
  } else {
    delete out.help_text;
  }

  return Object.keys(out).length > 0 ? out : null;
}

export type SubmissionValidationIssue = { fieldName: string; message: string };

export type ValidateSubmissionValuesOptions = {
  approverOptions?: ApproverOption[];
  /** When set, only these approver fields are required on submit (approval-policy forms). */
  requiredApproverFieldNames?: Set<string> | null;
  /** Server-side attachments already stored for this draft. */
  existingAttachmentCountsByField?: Record<string, number>;
  /** Pending camera photo metadata keyed by field name then file name. */
  cameraMetadataByField?: Record<string, Record<string, EApprovalCameraPhotoMetadata>>;
};

export function validateSubmissionValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  attachmentFiles: Record<string, File[]> = {},
  options: ValidateSubmissionValuesOptions = {},
): SubmissionValidationIssue[] {
  const issues: SubmissionValidationIssue[] = [];
  const approverOptions = options.approverOptions ?? [];

  for (const field of fields) {
    if (isComposeStructuralFieldType(field.type)) {
      continue;
    }

    if (!isFieldVisible(field, values)) {
      continue;
    }

    const value = (values[field.name] ?? "").trim();
    const rules = parseFieldValidation(field);
    const label = field.label?.trim() || field.name;

    if (field.type === "approver") {
      const required =
        options.requiredApproverFieldNames != null
          ? options.requiredApproverFieldNames.has(field.name)
          : rules.required;

      if (required && !isApproverSelectionValid(value, approverOptions)) {
        issues.push({ fieldName: field.name, message: `${label} is required.` });
        continue;
      }

      if (value && !isApproverSelectionValid(value, approverOptions)) {
        issues.push({
          fieldName: field.name,
          message: `Select a valid approver for ${label}.`,
        });
      }

      continue;
    }

    if (field.type === "grid") {
      if (rules.required) {
        const columns = parseGridColumns(field);
        const grid = parseGridValue(values[field.name] ?? "", columns.length);
        if (!gridHasContent(grid)) {
          issues.push({ fieldName: field.name, message: `${label} is required.` });
        }
      }
      continue;
    }

    if (field.type === "matrix") {
      const matrixOptions = parseMatrixFieldOptions(field);
      const state = parseMatrixValue(value);
      const allowed = new Set(matrixOptions.columns.map((c) => c.value));
      const rowValues = new Set(matrixOptions.rows.map((r) => r.value));

      for (const [rowKey, colValue] of Object.entries(state)) {
        if (!rowValues.has(rowKey) || !allowed.has(colValue)) {
          issues.push({ fieldName: field.name, message: `${label} contains an invalid answer.` });
          break;
        }
      }

      if (rules.required && !matrixHasCompleteAnswers(value, matrixOptions)) {
        issues.push({ fieldName: field.name, message: `${label} requires an answer for every row.` });
      }
      continue;
    }

    if (field.type === "size_matrix") {
      const rows = parseSizeMatrixRows(field);
      const state = parseSizeMatrixValue(value);
      const rowByValue = new Map(rows.map((r) => [r.value, r]));

      for (const [rowKey, rowValue] of Object.entries(state)) {
        const rowDef = rowByValue.get(rowKey);
        if (!rowDef) {
          issues.push({ fieldName: field.name, message: `${label} contains an invalid answer.` });
          break;
        }
        if (sizeMatrixRowInput(rowDef) === "text") {
          continue;
        }
        if (!rowValue.na) {
          const w = (rowValue.w ?? "").trim();
          const h = (rowValue.h ?? "").trim();
          if ((w !== "" && !Number.isFinite(Number(w))) || (h !== "" && !Number.isFinite(Number(h)))) {
            issues.push({ fieldName: field.name, message: `${label} sizes must be valid numbers.` });
            break;
          }
          if ((w !== "" && h === "") || (w === "" && h !== "")) {
            issues.push({
              fieldName: field.name,
              message: `${label}: enter both width and height, or mark NA.`,
            });
            break;
          }
        }
      }

      if (rules.required && !sizeMatrixHasCompleteAnswers(value, rows)) {
        issues.push({ fieldName: field.name, message: `${label} requires size or NA for every size row.` });
      } else if (!rules.required) {
        for (const row of rows) {
          if (sizeMatrixRowInput(row) === "text") {
            continue;
          }
          const entry = state[row.value];
          if (!entry || entry.na || sizeMatrixRowIsComplete(entry)) {
            continue;
          }
          const w = (entry.w ?? "").trim();
          const h = (entry.h ?? "").trim();
          if (w !== "" || h !== "") {
            issues.push({
              fieldName: field.name,
              message: `${label}: enter both width and height for ${row.label}, or mark NA.`,
            });
            break;
          }
        }
      }
      continue;
    }

    if (field.type === "checklist_matrix") {
      const checklistOptions = parseChecklistMatrixFieldOptions(field);
      const checklistError = validateChecklistMatrixValue(
        value,
        rules.required === true,
        label,
        checklistOptions,
      );
      if (checklistError) {
        issues.push({ fieldName: field.name, message: checklistError });
      }
      continue;
    }

    if (field.type === "file" || field.type === "camera") {
      const files = attachmentFiles[field.name] ?? [];
      const existingCount = options.existingAttachmentCountsByField?.[field.name] ?? 0;
      const total = files.length + existingCount;

      if (field.type === "camera") {
        const cameraOpts = parseCameraFieldOptions(field);
        const min = cameraOpts.min;
        const required = rules.required || min > 0;
        if (required && total === 0) {
          issues.push({ fieldName: field.name, message: `${label} is required.` });
          continue;
        }
        if (min > 0 && total < min) {
          issues.push({
            fieldName: field.name,
            message: `${label} requires at least ${min} photo(s).`,
          });
          continue;
        }
        if (total > cameraOpts.max) {
          issues.push({
            fieldName: field.name,
            message: `${label} allows at most ${cameraOpts.max} photo(s).`,
          });
          continue;
        }
        if (files.length > 0) {
          const cameraError = validateCameraSelection(
            field,
            files,
            options.cameraMetadataByField?.[field.name] ?? {},
          );
          if (cameraError) {
            issues.push({ fieldName: field.name, message: cameraError });
          }
        }
        continue;
      }

      if (rules.required && total === 0) {
        issues.push({ fieldName: field.name, message: `${label} is required.` });
        continue;
      }

      if (files.length > 0) {
        const fileError = validateSelectedFiles(field, files);
        if (fileError) {
          issues.push({ fieldName: field.name, message: fileError });
        }
      }
      continue;
    }

    if (field.type === "checkbox") {
      if (isCheckboxMulti(field)) {
        const selected = parseCheckboxValues(value);
        if (rules.required && selected.length === 0) {
          issues.push({ fieldName: field.name, message: `${label} is required.` });
          continue;
        }

        if (selected.length > 0) {
          const staticChoices = parseSelectChoices(field);
          if (staticChoices.length > 0) {
            const allowed = new Set(staticChoices.map((c) => c.value));
            const invalid = selected.filter((v) => !allowed.has(v));
            if (invalid.length > 0) {
              issues.push({
                fieldName: field.name,
                message: `${label} contains an invalid option.`,
              });
              continue;
            }
          }

          const companionError = validateCheckboxCompanions(value, staticChoices, label);
          if (companionError) {
            issues.push({ fieldName: field.name, message: companionError });
          }
        }
        continue;
      }

      if (rules.required && !isCheckboxTruthy(value)) {
        issues.push({ fieldName: field.name, message: `${label} is required.` });
      }
      continue;
    }

    if (field.type === "date_range") {
      const rangeError = validateDateRangeValue(value, rules.required === true, label);
      if (rangeError) {
        issues.push({ fieldName: field.name, message: rangeError });
      }
      continue;
    }

    if (rules.required && !value) {
      issues.push({ fieldName: field.name, message: `${label} is required.` });
      continue;
    }

    if (!value) {
      continue;
    }

    if (rules.max_length && value.length > rules.max_length) {
      issues.push({ fieldName: field.name, message: `${label} must be at most ${rules.max_length} characters.` });
    }

    if (field.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      issues.push({ fieldName: field.name, message: `${label} must be a valid email address.` });
    }

    if (field.type === "phone") {
      const digits = value.replace(/\D/g, "");
      if (digits.length < 7 || digits.length > 15) {
        issues.push({ fieldName: field.name, message: `${label} must be a valid phone number.` });
      }
    }

    if (field.type === "url") {
      try {
        const url = value.includes("://") ? value : `https://${value}`;
        new URL(url);
      } catch {
        issues.push({ fieldName: field.name, message: `${label} must be a valid URL.` });
      }
    }

    if (field.type === "rating") {
      const max = parseRatingMaxStars(field);
      const rating = Number(value);
      if (!Number.isInteger(rating) || rating < 1 || rating > max) {
        issues.push({ fieldName: field.name, message: `${label} must be between 1 and ${max}.` });
      }
    }

    if (field.type === "location" && value && !parseLocationValue(value)) {
      issues.push({ fieldName: field.name, message: `${label} must include valid coordinates.` });
    }

    if (field.type === "tags" && value && parseTagsValue(value).length === 0) {
      issues.push({ fieldName: field.name, message: `${label} must include at least one valid tag.` });
    }

    if (field.type === "signature") {
      const trimmed = value.trim();
      if (isSignatureDataUrl(trimmed) && trimmed.length > 500_000) {
        issues.push({ fieldName: field.name, message: `${label} signature image is too large.` });
      } else if (!isSignatureDataUrl(trimmed) && trimmed.length > 5000) {
        issues.push({ fieldName: field.name, message: `${label} must be at most 5000 characters.` });
      }
    }
  }

  return issues;
}

export function fieldSupportsValidationRules(type: string): boolean {
  return !isComposeStructuralFieldType(type) && type !== "grid";
}

export function fieldSupportsTypeOptions(type: string): boolean {
  return ["rating", "tags", "location", "file", "camera"].includes(type);
}

export function fieldPlaceholder(field: EApprovalFormFieldInput): string | undefined {
  return parseFieldValidation(field).placeholder;
}

export function fieldMaxLength(field: EApprovalFormFieldInput): number | undefined {
  return parseFieldValidation(field).max_length;
}

export function fieldDefaultValue(field: EApprovalFormFieldInput): string {
  return parseFieldValidation(field).default ?? "";
}
