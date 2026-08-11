import { describe, expect, it } from "vitest";

import { normalizeGridFieldValue } from "@/modules/e-approval/field-options";
import { procurementLinkCascadePatch } from "@/modules/e-approval/procurement-link-fields";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { validateSubmissionValues } from "@/modules/e-approval/field-validation";

const lineItemsField: EApprovalFormFieldInput = {
  type: "grid",
  name: "line_items",
  label: "Lines",
  options: {
    columns: [
      { label: "Description", type: "text" },
      { label: "Qty", type: "number" },
      { label: "Unit price", type: "currency" },
    ],
  },
};

describe("normalizeGridFieldValue", () => {
  it("maps labeled backend rows into indexed editor rows", () => {
    const raw = JSON.stringify([
      { Description: "Cable tray", Qty: "4", "Unit price": "1200" },
    ]);

    const normalized = normalizeGridFieldValue(raw, lineItemsField);
    const parsed = JSON.parse(normalized) as { rows: Array<Record<string, string>> };

    expect(parsed.rows[0]?.["0"]).toBe("Cable tray");
    expect(parsed.rows[0]?.["1"]).toBe("4");
    expect(parsed.rows[0]?.["2"]).toBe("1200");
  });
});

describe("procurementLinkCascadePatch", () => {
  it("clears dependent link fields when project changes", () => {
    expect(procurementLinkCascadePatch("project_id", "proj-1")).toEqual({
      project_id: "proj-1",
      rollout_id: "",
      site_id: "",
      boq_line_id: "",
    });
  });

  it("clears BOQ when rollout changes", () => {
    expect(procurementLinkCascadePatch("rollout_id", "roll-1")).toEqual({
      rollout_id: "roll-1",
      boq_line_id: "",
    });
  });

  it("clears project when site is selected directly", () => {
    expect(procurementLinkCascadePatch("site_id", "site-1")).toEqual({
      site_id: "site-1",
      project_id: "",
    });
  });
});

describe("validateSubmissionValues file attachments", () => {
  it("accepts required file fields when server attachments already exist", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "file",
        name: "quotes",
        label: "Vendor quotes",
        validation: { required: true },
      },
    ];

    const issues = validateSubmissionValues(fields, {}, {}, {
      existingAttachmentCountsByField: { quotes: 1 },
    });

    expect(issues).toEqual([]);
  });
});
