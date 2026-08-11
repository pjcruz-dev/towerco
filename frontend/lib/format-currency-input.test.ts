import { describe, expect, it } from "vitest";

import {
  countCurrencySignificantChars,
  currencyCaretFromSignificantCount,
  formatCurrencyGrouping,
  parseCurrencyTyping,
  toCanonicalCurrency,
} from "@/lib/format-currency-input";

describe("format-currency-input", () => {
  it("formats canonical amounts with thousand separators", () => {
    expect(formatCurrencyGrouping("12698.95")).toBe("12,698.95");
    expect(formatCurrencyGrouping("1000")).toBe("1,000");
    expect(formatCurrencyGrouping("")).toBe("");
    expect(formatCurrencyGrouping("-2500.5")).toBe("-2,500.5");
  });

  it("parses typed / pasted values into display + canonical", () => {
    expect(parseCurrencyTyping("12698.95")).toEqual({
      display: "12,698.95",
      canonical: "12698.95",
    });
    expect(parseCurrencyTyping("12,698.95")).toEqual({
      display: "12,698.95",
      canonical: "12698.95",
    });
    expect(parseCurrencyTyping("12698.")).toEqual({
      display: "12,698.",
      canonical: "12698",
    });
  });

  it("limits decimal places to two by default", () => {
    expect(parseCurrencyTyping("1.999").canonical).toBe("1.99");
    expect(toCanonicalCurrency("12,698.9567")).toBe("12698.95");
  });

  it("maps caret past grouping commas", () => {
    const display = "12,698.95";
    const beforeComma = countCurrencySignificantChars("126");
    expect(currencyCaretFromSignificantCount(display, beforeComma)).toBe(4); // after "12,6"
  });
});
