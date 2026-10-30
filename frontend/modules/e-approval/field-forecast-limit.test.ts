import { describe, expect, it } from "vitest";

import { validateSubmissionValues } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const forecast: EApprovalFormFieldInput = {
  type: "grid",
  name: "expense_lines",
  label: "Forecast lines",
  options: {
    columns: [
      { label: "From date – To date", type: "date_range" },
      { label: "Amount", type: "currency" },
    ],
  },
};

function grid(rows: Array<{ from: string; to: string }>) {
  return JSON.stringify({
    rows: rows.map((row) => ({ "0": JSON.stringify(row), "1": "1000" })),
  });
}

describe("cash advance forecast duration", () => {
  it("rejects an activity longer than 15 days", () => {
    const issues = validateSubmissionValues([forecast], {
      expense_lines: grid([{ from: "2026-10-01", to: "2026-10-16" }]),
    });

    expect(issues.map((issue) => issue.message)).toContain(
      "Forecast activities can cover at most 15 days, from the earliest start to the latest end.",
    );
  });

  it("measures every row from the earliest start to the latest end", () => {
    expect(
      validateSubmissionValues([forecast], {
        expense_lines: grid([
          { from: "2026-10-01", to: "2026-10-08" },
          { from: "2026-10-06", to: "2026-10-15" },
        ]),
      }),
    ).toEqual([]);

    const issues = validateSubmissionValues([forecast], {
      expense_lines: grid([
        { from: "2026-10-01", to: "2026-10-05" },
        { from: "2026-10-10", to: "2026-10-16" },
      ]),
    });
    expect(issues.map((issue) => issue.message)).toContain(
      "Forecast activities can cover at most 15 days, from the earliest start to the latest end.",
    );
  });

  it("requires Nature of Activity on a line that has other values", () => {
    const withNature: EApprovalFormFieldInput = {
      ...forecast,
      options: {
        columns: [
          { label: "From date – To date", type: "date_range" },
          { label: "Amount", type: "currency" },
          { label: "Nature of Activity", type: "text", required: true },
        ],
      },
    };
    const missing = validateSubmissionValues([withNature], {
      expense_lines: JSON.stringify({
        rows: [{ "0": JSON.stringify({ from: "2026-10-01", to: "2026-10-02" }), "1": "1000", "2": "" }],
      }),
    });
    expect(missing.map((issue) => issue.message)).toContain("Nature of Activity is required on each line.");

    const filled = validateSubmissionValues([withNature], {
      expense_lines: JSON.stringify({
        rows: [{ "0": JSON.stringify({ from: "2026-10-01", to: "2026-10-02" }), "1": "1000", "2": "Site survey" }],
      }),
    });
    expect(filled).toEqual([]);
  });

  it("allows 15 days and leaves a liquidation line alone", () => {
    expect(
      validateSubmissionValues([forecast], {
        expense_lines: grid([{ from: "2026-10-01", to: "2026-10-15" }]),
      }),
    ).toEqual([]);

    const liquidation: EApprovalFormFieldInput = {
      ...forecast,
      options: {
        columns: [
          { label: "From date – To date", type: "date_range" },
          { label: "OR No", type: "text" },
          { label: "Expense type", type: "select" },
          { label: "Amount", type: "currency" },
        ],
      },
    };

    expect(
      validateSubmissionValues([liquidation], {
        expense_lines: grid([{ from: "2026-10-01", to: "2026-10-31" }]),
      }),
    ).toEqual([]);
  });
});
