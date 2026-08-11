import { describe, expect, it } from "vitest";

import {
  dateRangeHasValue,
  dateRangeIsComplete,
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

  it("validates completeness and order", () => {
    expect(dateRangeHasValue("")).toBe(false);
    expect(dateRangeIsComplete('{"from":"2026-01-01","to":"2026-01-02"}')).toBe(true);
    expect(validateDateRangeValue("", true, "Travel")).toMatch(/start and end/i);
    expect(validateDateRangeValue('{"from":"2026-01-05","to":"2026-01-01"}', false, "Travel")).toMatch(
      /on or after/i,
    );
    expect(validateDateRangeValue('{"from":"2026-01-01","to":"2026-01-05"}', true, "Travel")).toBeNull();
  });
});
