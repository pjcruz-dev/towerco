import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { fieldOptionsToRecord, mergeFieldOptions } from "@/modules/e-approval/field-options";

export function parseInstructionBody(field: EApprovalFormFieldInput): string {
  const record = fieldOptionsToRecord(field);
  const body = record.body;
  if (typeof body === "string") {
    return body;
  }

  return "";
}

export function setInstructionBody(
  field: EApprovalFormFieldInput,
  body: string,
): Record<string, unknown> {
  return mergeFieldOptions(field, { body });
}

/**
 * Paste from paper/PDF often includes hard wraps mid-sentence. Collapse those
 * so text can use the full card width, while keeping list items / blank lines.
 */
export function formatInstructionBodyForDisplay(raw: string): string {
  const normalized = raw.replace(/\r\n/g, "\n").trim();
  if (normalized === "") {
    return "";
  }

  const lines = normalized.split("\n");
  const blocks: string[] = [];
  let current = "";

  const isListStart = (line: string) => /^([a-z]\.|[0-9]+\.|[-•*])\s+/i.test(line);

  for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed === "") {
      if (current !== "") {
        blocks.push(current);
        current = "";
      }
      continue;
    }

    if (current === "") {
      current = trimmed;
      continue;
    }

    if (isListStart(trimmed)) {
      blocks.push(current);
      current = trimmed;
      continue;
    }

    current = `${current} ${trimmed}`;
  }

  if (current !== "") {
    blocks.push(current);
  }

  return blocks.join("\n\n");
}
