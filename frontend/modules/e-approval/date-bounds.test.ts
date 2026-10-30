import { describe, expect, it } from "vitest";

import {
  dateValueBreaksBound,
  earliestIsoDate,
  fieldLockedByFilledGrid,
  gridColumnDateIssue,
  gridColumnSubsidiaryIssue,
} from "@/modules/e-approval/date-bounds";
import { fieldsPassingVisibility } from "@/modules/e-approval/field-visibility";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const now = new Date(2026, 9, 30);

describe("date bounds", () => {
  it("blocks a funds-needed date inside the three-day lead", () => {
    const min = earliestIsoDate({ kind: "lead", leadDays: 3 }, {}, now);
    expect(min).toBe("2026-11-02");
    expect(dateValueBreaksBound("2026-11-01", { kind: "lead", leadDays: 3 }, {}, now)).toBe(true);
    expect(dateValueBreaksBound("2026-11-02", { kind: "lead", leadDays: 3 }, {}, now)).toBe(false);
  });

  it("requires a forecast range on or after funds needed by, once", () => {
    const column = {
      label: "From date – To date",
      type: "date_range" as const,
      date_min: "field" as const,
      date_min_field: "needed_by",
    };
    const values = { needed_by: "2026-11-02" };
    const message = gridColumnDateIssue(
      column,
      JSON.stringify({ from: "2026-11-01", to: "2026-11-03" }),
      values,
      [{ name: "needed_by", label: "Funds needed by", type: "date", step_order: 1 }],
    );
    expect(message).toBe("From date – To date must be on or after Funds needed by.");
    expect(
      gridColumnDateIssue(column, JSON.stringify({ from: "2026-11-02", to: "2026-11-04" }), values, []),
    ).toBeNull();
  });

  it("flags a site that belongs to another subsidiary", () => {
    const message = gridColumnSubsidiaryIssue(
      { label: "From site / location", type: "select", subsidiary_field: "subsidiary" },
      JSON.stringify({ site: "M1053", fields: { subsidiary: "ATC" } }),
      { subsidiary: "ADIC" },
    );
    expect(message).toBe("From site / location must belong to ADIC.");
  });

  it("locks funds needed by only after a forecast line has a value", () => {
    const field: EApprovalFormFieldInput = {
      name: "needed_by",
      label: "Funds needed by",
      type: "date",
      step_order: 1,
      options: { lock_when_grid: "expense_lines" },
    };
    const grid: EApprovalFormFieldInput = {
      name: "expense_lines",
      label: "Forecast lines",
      type: "grid",
      step_order: 2,
      options: { columns: [{ label: "From date – To date", type: "date_range" }] },
    };
    expect(fieldLockedByFilledGrid(field, [grid], { expense_lines: "" })).toBe(false);
    expect(
      fieldLockedByFilledGrid(field, [grid], {
        expense_lines: JSON.stringify({ rows: [{ "0": "2026-11-02" }] }),
      }),
    ).toBe(true);
  });
});

describe("section visibility", () => {
  const fields: EApprovalFormFieldInput[] = [
    { name: "section_request", label: "Cash advance request", type: "section", step_order: 1 },
    { name: "needed_by", label: "Funds needed by", type: "date", step_order: 2 },
    { name: "subsidiary", label: "Subsidiary", type: "select", step_order: 3 },
    { name: "purpose", label: "Purpose", type: "text", step_order: 4 },
    {
      name: "section_expenses",
      label: "Forecast expenses",
      type: "section",
      step_order: 5,
      options: {
        visibility: {
          mode: "show_when",
          operator: "all_filled",
          field: "needed_by",
          fields: ["needed_by", "subsidiary", "purpose"],
        },
      },
    },
    { name: "expense_lines", label: "Forecast lines", type: "grid", step_order: 6 },
    { name: "notes", label: "Notes", type: "textarea", step_order: 7 },
  ];

  it("hides the forecast section until the three answers are filled", () => {
    const hidden = fieldsPassingVisibility(fields, { needed_by: "2026-11-02", subsidiary: "ATC", purpose: "" });
    expect(hidden.map((field) => field.name)).toEqual(["section_request", "needed_by", "subsidiary", "purpose"]);

    const shown = fieldsPassingVisibility(fields, {
      needed_by: "2026-11-02",
      subsidiary: "ATC",
      purpose: "Site visit",
    });
    expect(shown.map((field) => field.name)).toContain("expense_lines");
    expect(shown.map((field) => field.name)).toContain("notes");
  });
});
