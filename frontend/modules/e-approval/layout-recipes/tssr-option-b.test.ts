import { describe, expect, it } from "vitest";

import { parseFieldLayout } from "@/modules/e-approval/field-layout";
import {
  applyTssrOptionBLayout,
  formSupportsTssrOptionBLayout,
  TSSR_OPTION_B_FIELD_NAMES as N,
} from "@/modules/e-approval/layout-recipes/tssr-option-b";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(
  name: string,
  type: string,
  label = name,
): EApprovalFormFieldInput {
  return { name, type, label, step_order: 1, options: {} };
}

describe("TSSR Option B layout recipe", () => {
  it("reorders Site Survey fields into sectioned 2-col + full-width blocks", () => {
    const fields: EApprovalFormFieldInput[] = [
      field("a1_general", "section", "A1"),
      field(N.section, "section", "B. Site Survey Report"),
      field(N.distance, "text"),
      field(N.structure, "checkbox"),
      field(N.buildingMounted, "checkbox"),
      field(N.roof, "size_matrix"),
      field(N.attachment, "checkbox"),
      field(N.topographic, "checkbox"),
      field(N.rolling, "matrix"),
      field(N.floors, "matrix"),
      field(N.instructions, "instruction"),
    ];

    expect(formSupportsTssrOptionBLayout(fields)).toBe(true);

    const { fields: next, layoutRows } = applyTssrOptionBLayout(fields, []);
    const names = next.map((f) => f.name);

    expect(names.slice(0, 2)).toEqual(["a1_general", N.section]);
    expect(names).toContain(N.dividerBasics);
    expect(names.indexOf(N.distance)).toBeLessThan(names.indexOf(N.attachment));
    expect(names.indexOf(N.structure)).toBeLessThan(names.indexOf(N.buildingMounted));
    expect(names.indexOf(N.roof)).toBeLessThan(names.indexOf(N.topographic));
    expect(names.indexOf(N.floors)).toBeLessThan(names.indexOf(N.instructions));

    expect(parseFieldLayout(next.find((f) => f.name === N.distance)!)).toMatchObject({
      row_id: "row_tssr_site_basics",
      slot: 0,
      width: "half",
    });
    expect(parseFieldLayout(next.find((f) => f.name === N.attachment)!)).toMatchObject({
      row_id: "row_tssr_site_basics",
      slot: 1,
      width: "half",
    });
    expect(parseFieldLayout(next.find((f) => f.name === N.structure)!).width).toBe("full");
    expect(parseFieldLayout(next.find((f) => f.name === N.buildingMounted)!).width).toBe("full");
    expect(parseFieldLayout(next.find((f) => f.name === N.roof)!).width).toBe("full");
    expect(parseFieldLayout(next.find((f) => f.name === N.roof)!).row_id).toBeUndefined();
    expect(parseFieldLayout(next.find((f) => f.name === N.floors)!).width).toBe("full");

    expect(layoutRows.map((r) => r.id)).toEqual([
      "row_tssr_site_basics",
      "row_tssr_terrain",
    ]);
    expect(layoutRows[0]?.insert_index).toBe(names.indexOf(N.distance));
    expect(layoutRows[1]?.insert_index).toBe(names.indexOf(N.topographic));
  });

  it("keeps site options + B1 photo block after instructions when present", () => {
    const fields: EApprovalFormFieldInput[] = [
      field(N.section, "section", "B. Site Survey Report"),
      field(N.distance, "text"),
      field(N.structure, "checkbox"),
      field(N.buildingMounted, "checkbox"),
      field(N.roof, "size_matrix"),
      field(N.attachment, "checkbox"),
      field(N.topographic, "checkbox"),
      field(N.rolling, "matrix"),
      field(N.floors, "matrix"),
      field(N.instructions, "instruction"),
      field(N.panoramicPhotos, "camera"),
      field(N.siteOptionsGrid, "grid"),
      field(N.sectionSiteOptions, "section", "B. Site Options"),
      field(N.sectionB1Photos, "section", "B1. Photos"),
      field("c_radio_room_access_power", "section", "C"),
    ];

    const { fields: next } = applyTssrOptionBLayout(fields, []);
    const names = next.map((f) => f.name);

    expect(names.indexOf(N.instructions)).toBeLessThan(names.indexOf(N.sectionSiteOptions));
    expect(names.indexOf(N.sectionSiteOptions)).toBeLessThan(names.indexOf(N.siteOptionsGrid));
    expect(names.indexOf(N.siteOptionsGrid)).toBeLessThan(names.indexOf(N.sectionB1Photos));
    expect(names.indexOf(N.sectionB1Photos)).toBeLessThan(names.indexOf(N.panoramicPhotos));
    expect(names.indexOf(N.panoramicPhotos)).toBeLessThan(names.indexOf("c_radio_room_access_power"));
  });
});
