import { describe, expect, it } from "vitest";

import { entraLicenseChipLabel, entraLicenseSummary } from "./entra-license";

describe("entra license display", () => {
  it("returns null when there is no primary license", () => {
    expect(entraLicenseChipLabel(null, [])).toBeNull();
    expect(entraLicenseChipLabel("", ["Microsoft 365 E3"])).toBeNull();
  });

  it("shows extras as a plus count", () => {
    expect(entraLicenseChipLabel("E3", ["Microsoft 365 E3", "Power BI Pro", "Visio Plan 2"])).toBe("E3 +2");
    expect(entraLicenseChipLabel("E3", ["Microsoft 365 E3"])).toBe("E3");
  });

  it("joins full product names", () => {
    expect(entraLicenseSummary(["Microsoft 365 E3", "Power BI Pro"])).toBe("Microsoft 365 E3, Power BI Pro");
  });
});
