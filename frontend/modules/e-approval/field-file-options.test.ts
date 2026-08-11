import { describe, expect, it } from "vitest";

import { parseFileFieldOptions } from "@/modules/e-approval/field-file-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

describe("parseFileFieldOptions", () => {
  it("normalizes jpg to jpeg in allowed types", () => {
    const field: EApprovalFormFieldInput = {
      type: "file",
      name: "core_access_documents",
      label: "Upload",
      validation: {
        required: true,
        maxFiles: 15,
        allowedFileTypes: ["pdf", "png", "jpg", "jpeg"],
      },
    };

    expect(parseFileFieldOptions(field).allowedFileTypes).toEqual(["pdf", "png", "jpeg"]);
  });

  it("parses maxFiles from numeric strings", () => {
    const field: EApprovalFormFieldInput = {
      type: "file",
      name: "docs",
      label: "Upload",
      validation: { maxFiles: "15" },
    };

    expect(parseFileFieldOptions(field).maxFiles).toBe(15);
  });
});
