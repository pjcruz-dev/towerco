import { describe, expect, it } from "vitest";

import {
  dateRangeHasValue,
  dateRangeIsComplete,
  formatDateRangeDisplay,
  parseDateRangeValue,
  serializeDateRangeValue,
  validateDateRangeValue,
} from "@/modules/e-approval/field-type-options";

describe("date range field helpers", () => {
  it("parses and serializes JSON ranges", () => {
    expect(parseDateRangeValue('{"from":"2026-01-01","to":"2026-01-31"}')).toEqual({
      from: "2026-01-01",
      to: "2026-01-31",
    });
    expect(serializeDateRangeValue({ from: "2026-01-01", to: "2026-01-31" })).toBe(
      '{"from":"2026-01-01","to":"2026-01-31"}',
    );
    expect(serializeDateRangeValue({ from: "", to: "" })).toBe("");
  });

  it("accepts pipe legacy format", () => {
    expect(parseDateRangeValue("2026-01-01|2026-01-05")).toEqual({
      from: "2026-01-01",
      to: "2026-01-05",
    });
  });

  it("formats ranges for display", () => {
    expect(formatDateRangeDisplay('{"from":"2026-09-22","to":"2026-10-20"}')).toBe(
      "2026-09-22 – 2026-10-20",
    );
    expect(formatDateRangeDisplay("")).toBe("—");
    expect(formatDateRangeDisplay('{"from":"2026-09-22","to":""}')).toBe("2026-09-22 – —");
    expect(
      formatDateRangeDisplay('{"from":"2026-10-14T08:30","to":"2026-10-16T17:00"}'),
    ).toBe("2026-10-14 08:30 – 2026-10-16 17:00");
    expect(
      formatDateRangeDisplay('{"from":"2026-10-14T08:30","to":"2026-10-16T17:00"}', "—", false),
    ).toBe("2026-10-14 – 2026-10-16");
  });

  it("validates completeness and order", () => {
    expect(dateRangeHasValue("")).toBe(false);
    expect(dateRangeIsComplete('{"from":"2026-01-01","to":"2026-01-02"}')).toBe(true);
    expect(validateDateRangeValue("", true, "Travel")).toMatch(/start and end/i);
    expect(validateDateRangeValue('{"from":"2026-01-05","to":"2026-01-01"}', false, "Travel")).toMatch(
      /on or after/i,
    );
    expect(validateDateRangeValue('{"from":"2026-01-01","to":"2026-01-05"}', true, "Travel")).toBeNull();
    expect(
      validateDateRangeValue('{"from":"2026-10-14T08:30","to":"2026-10-16T17:00"}', true, "Travel"),
    ).toBeNull();
    expect(
      validateDateRangeValue('{"from":"2026-10-14T18:00","to":"2026-10-14T08:00"}', false, "Travel"),
    ).toMatch(/on or after/i);
    expect(
      validateDateRangeValue('{"from":"2026-10-14T08:00","to":"2026-10-14T18:00"}', true, "Travel"),
    ).toBeNull();
  });
});
