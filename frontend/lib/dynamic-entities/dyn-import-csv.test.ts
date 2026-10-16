import { describe, expect, it } from "vitest";

import { importBlocksFromCsv, parentRowsFromCsv } from "@/lib/dynamic-entities/dyn-import-csv";

describe("parent rows from a legacy export", () => {
  it("keeps site rows and drops child sections", () => {
    const csv = [
      "Actions,Print,ID,Site Name,Status,Date Endorsed by MNO",
      ",,1001,North Site,Site Acquisition,2026-01-15",
      ",--- Telco Contracts ---",
      ",Actions,Print,ID,Contract Number,Status,Site License Status,Tower Site,Telco Operator",
      ",x,x,705,,,,706,GLOBE",
      "",
      ",,1002,South Site,Site Acquisition,2026-02-02",
      ",--- SAQ Trackers ---",
      ",ID,Actions,Print,Overall Status,Tower Site",
      ",700,x,x,OG Site Acquisition,1002",
    ].join("\n");

    const parsed = parentRowsFromCsv(csv);
    expect(parsed.rows.map((row) => row[3])).toEqual(["North Site", "South Site"]);
  });

  it("skips a template marker and the child header under it", () => {
    const csv = ["Site Name", "## SAQ Trackers ##", "SAQ Status", "North Site"].join("\n");
    const parsed = parentRowsFromCsv(csv);
    expect(parsed.rows).toEqual([["North Site"]]);
  });
});

describe("import blocks keep child sections with their parent", () => {
  it("attaches the SAQ section and leaves an unknown section off the count of sites", () => {
    const csv = [
      "Site Name",
      "North Site",
      ",--- SAQ Trackers ---",
      ",Overall Status",
      ",OG Site Acquisition",
      "",
      "South Site",
      ",--- Telco Contracts ---",
      ",Telco Operator",
      ",GLOBE",
    ].join("\n");

    const parsed = importBlocksFromCsv(csv);
    expect(parsed.blocks.map((block) => block.row[0])).toEqual(["North Site", "South Site"]);
    expect(parsed.blocks[0]?.sections).toEqual([
      {
        label: "SAQ Trackers",
        headers: ["", "Overall Status"],
        rows: [["", "OG Site Acquisition"]],
      },
    ]);
    expect(parsed.blocks[1]?.sections[0]?.label).toBe("Telco Contracts");
  });
});
