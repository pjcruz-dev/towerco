import { describe, expect, it } from "vitest";

import {
  buildDocumentNumberPreview,
  DOCUMENT_NUMBER_BUILTIN_TOKENS,
  DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER,
  documentNumberFieldTokens,
} from "@/modules/e-approval/form-document-number";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(
  name: string,
  type: string,
  label = name,
  options: Record<string, unknown> = {},
): EApprovalFormFieldInput {
  return { name, type, label, step_order: 1, options };
}

describe("document number subsidiary token", () => {
  it("exposes subsidiary as a built-in token and placeholder", () => {
    expect(DOCUMENT_NUMBER_BUILTIN_TOKENS.some((t) => t.token === "{subsidiary}")).toBe(true);
    expect(DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER).toContain("{subsidiary}");
  });

  it("does not duplicate subsidiary as a form-field chip", () => {
    const tokens = documentNumberFieldTokens([
      field("subsidiary", "select", "Subsidiary", {
        choices: [
          { value: "ATC", label: "ATC" },
          { value: "ADIC", label: "ADIC" },
        ],
      }),
      field("summary", "text", "Summary"),
    ]);
    expect(tokens.map((t) => t.token)).toEqual(["{summary}"]);
  });

  it("previews dynamic subsidiary from the form field", () => {
    const preview = buildDocumentNumberPreview(
      {
        ownerCode: "GEN",
        docTypeCode: "F",
        docNoCustomEnabled: true,
        docNoTemplate: "{subsidiary}-{department}-{docTypeCode}-{seq:3}",
      },
      [
        field("subsidiary", "select", "Subsidiary", {
          choices: [
            { value: "ADIC", label: "ADIC" },
            { value: "ATC", label: "ATC" },
          ],
        }),
      ],
      { sampleDepartment: "Finance" },
    );

    expect(preview).toBe("ADIC-FINANCE-F-001");
  });
});
