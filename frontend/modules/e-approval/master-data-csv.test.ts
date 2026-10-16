import { describe, expect, it } from "vitest";

import {
  masterDataRowsToCsv,
  planMasterDataImport,
  suggestMasterDataColumnMap,
} from "@/modules/e-approval/master-data-csv";

const columns = [
  { key: "vatable", label: "VATable", type: "boolean" as const },
  { key: "per_diem", label: "Per diem", type: "boolean" as const },
];

describe("master data csv import", () => {
  it("maps label, code, and extra columns and rejects duplicates", () => {
    const headers = ["Label", "Code", "VATable", "Per diem"];
    const map = suggestMasterDataColumnMap(headers, columns);
    expect(map).toEqual({
      Label: "label",
      Code: "code",
      VATable: "vatable",
      "Per diem": "per_diem",
    });

    const planned = planMasterDataImport(
      [
        headers,
        ["Hotel & Accommodation", "hotel_accommodation", "Yes", "No"],
        ["Per Diem", "per_diem", "No", "Yes"],
        ["Per Diem", "per_diem_2", "No", "Yes"],
        ["Gasoline", "per_diem", "Yes", "No"],
        ["", "blank", "Yes", "No"],
      ],
      map,
      columns,
      [{ label: "Lodging", code: "lodging" }],
    );

    expect(planned[0]).toMatchObject({
      label: "Hotel & Accommodation",
      code: "hotel_accommodation",
      error: null,
      data: { vatable: true, per_diem: false },
    });
    expect(planned[1]?.error).toBeNull();
    expect(planned[2]?.error).toContain("repeats the label");
    expect(planned[3]?.error).toContain("repeats the code");
    expect(planned[4]?.error).toContain("needs a label");
  });

  it("treats an existing row as a duplicate", () => {
    const planned = planMasterDataImport(
      [
        ["Name", "Value"],
        ["Finance", "finance"],
      ],
      { Name: "label", Value: "code" },
      [],
      [{ label: "Finance", code: "finance" }],
    );
    expect(planned[0]?.error).toContain("repeats the label");
  });

  it("exports the current rows", () => {
    const csv = masterDataRowsToCsv(columns, [
      {
        label: "Hotel & Accommodation",
        code: "hotel_accommodation",
        is_active: true,
        data: { vatable: true, per_diem: false },
      },
    ]);
    expect(csv.split("\n")[0]).toBe("Label,Code,Active,VATable,Per diem");
    expect(csv).toContain("Hotel & Accommodation,hotel_accommodation,Yes,Yes,No");
  });
});
