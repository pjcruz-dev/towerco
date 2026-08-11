import { describe, expect, it } from "vitest";

import {
  formatChecklistMatrixDisplay,
  parseChecklistMatrixFieldOptions,
  parseChecklistMatrixState,
  serializeChecklistMatrixState,
  setChecklistMatrixCellValue,
  setChecklistMatrixRowSelected,
  validateChecklistMatrixValue,
} from "@/modules/e-approval/field-checklist-matrix";
import { defaultFieldForType } from "@/modules/e-approval/field-types";

describe("checklist_matrix", () => {
  it("seeds cost-application defaults", () => {
    const field = defaultFieldForType("checklist_matrix", 0);
    expect(field.type).toBe("checklist_matrix");
    const options = parseChecklistMatrixFieldOptions(field);
    expect(options.row_select_label).toBe("Cost Application");
    expect(options.rows.length).toBeGreaterThanOrEqual(9);
    expect(options.columns.map((c) => c.label)).toEqual([
      "Project Site No",
      "Ref No",
      "OR No.",
    ]);
  });

  it("round-trips selected rows with cells", () => {
    const field = defaultFieldForType("checklist_matrix", 0);
    const options = parseChecklistMatrixFieldOptions(field);
    let value = setChecklistMatrixRowSelected("", "others", true, options.columns);
    value = setChecklistMatrixCellValue(value, "others", "ref_no", "R-100", options.columns);

    const state = parseChecklistMatrixState(value, options.columns);
    expect(state.others?.selected).toBe(true);
    expect(state.others?.cells.ref_no).toBe("R-100");
    expect(serializeChecklistMatrixState(state)).toContain("others");
  });

  it("requires at least one selected row when required", () => {
    const field = defaultFieldForType("checklist_matrix", 0);
    const options = parseChecklistMatrixFieldOptions(field);
    expect(validateChecklistMatrixValue("", true, "Cost", options)).toMatch(/at least one/i);
    const value = setChecklistMatrixRowSelected("", "logistics", true, options.columns);
    expect(validateChecklistMatrixValue(value, true, "Cost", options)).toBeNull();
  });

  it("parses typed columns including dropdown choices", () => {
    const field = {
      ...defaultFieldForType("checklist_matrix", 0),
      options: {
        rows: [{ value: "others", label: "Others" }],
        columns: [
          { value: "site", label: "Site", type: "text" },
          {
            value: "status",
            label: "Status",
            type: "select",
            choices: [
              { value: "open", label: "Open" },
              { value: "closed", label: "Closed" },
            ],
          },
        ],
      },
    };
    const options = parseChecklistMatrixFieldOptions(field);
    expect(options.columns[1]?.type).toBe("select");
    expect(options.columns[1]?.choices?.[0]?.label).toBe("Open");

    const value = JSON.stringify({
      others: { selected: true, cells: { site: "S1", status: "open" } },
    });
    expect(formatChecklistMatrixDisplay(value, options)).toContain("Status: Open");
  });
});
