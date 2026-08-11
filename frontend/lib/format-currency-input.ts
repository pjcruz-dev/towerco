/**
 * Live currency typing helpers — display with thousand separators, store a clean numeric string.
 * Example display: 12,698.95 → canonical: 12698.95
 */

export type CurrencyTypingResult = {
  display: string;
  canonical: string;
};

const DEFAULT_MAX_DECIMALS = 2;

/** Strip grouping/spaces; keep optional leading minus, digits, and one decimal point. */
export function toCanonicalCurrency(raw: string, maxDecimals = DEFAULT_MAX_DECIMALS): string {
  const trimmed = raw.trim();
  if (trimmed === "") {
    return "";
  }

  const negative = trimmed.startsWith("-");
  let body = trimmed.replace(/[^\d.]/g, "");
  const dot = body.indexOf(".");
  let intPart = dot === -1 ? body : body.slice(0, dot);
  let decPart = dot === -1 ? null : body.slice(dot + 1).replace(/\./g, "").slice(0, maxDecimals);

  intPart = intPart.replace(/^0+(?=\d)/, "");

  if (dot === -1) {
    return `${negative ? "-" : ""}${intPart}`;
  }

  if (decPart === null || decPart === "") {
    // Trailing decimal while typing — keep the point only in display; store integer part.
    return `${negative ? "-" : ""}${intPart || "0"}`;
  }

  return `${negative ? "-" : ""}${intPart || "0"}.${decPart}`;
}

/** Format a stored/canonical amount for display (adds thousand separators). */
export function formatCurrencyGrouping(canonical: string, maxDecimals = DEFAULT_MAX_DECIMALS): string {
  const trimmed = canonical.trim();
  if (trimmed === "") {
    return "";
  }

  const negative = trimmed.startsWith("-");
  const body = trimmed.replace(/[^\d.]/g, "");
  const dot = body.indexOf(".");
  let intPart = dot === -1 ? body : body.slice(0, dot);
  let decPart = dot === -1 ? null : body.slice(dot + 1).replace(/\./g, "").slice(0, maxDecimals);

  intPart = intPart.replace(/^0+(?=\d)/, "");
  const grouped = (intPart || (dot !== -1 ? "0" : "")).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

  if (dot === -1) {
    return `${negative ? "-" : ""}${grouped}`;
  }

  return `${negative ? "-" : ""}${grouped || "0"}.${decPart ?? ""}`;
}

/**
 * Parse a keystroke/paste into display + canonical values.
 * Preserves a trailing decimal point in `display` while the user is typing cents.
 */
export function parseCurrencyTyping(raw: string, maxDecimals = DEFAULT_MAX_DECIMALS): CurrencyTypingResult {
  const trimmed = raw.trim();
  if (trimmed === "" || trimmed === "-") {
    return { display: trimmed === "-" ? "-" : "", canonical: "" };
  }

  const negative = trimmed.startsWith("-");
  let body = trimmed.replace(/[^\d.]/g, "");
  const hasDot = body.includes(".");
  const dot = body.indexOf(".");
  let intPart = hasDot ? body.slice(0, dot) : body;
  let decPart = hasDot ? body.slice(dot + 1).replace(/\./g, "").slice(0, maxDecimals) : null;

  intPart = intPart.replace(/^0+(?=\d)/, "");

  const grouped = (intPart || (hasDot ? "0" : "")).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  const sign = negative ? "-" : "";

  if (!hasDot) {
    return {
      display: `${sign}${grouped}`,
      canonical: `${sign}${intPart}`,
    };
  }

  const display = `${sign}${grouped || "0"}.${decPart ?? ""}`;
  const canonical =
    decPart === null || decPart === ""
      ? `${sign}${intPart || "0"}`
      : `${sign}${intPart || "0"}.${decPart}`;

  return { display, canonical };
}

/** Map caret from pre-format digit/dot count into the formatted string. */
export function currencyCaretFromSignificantCount(display: string, significantBeforeCaret: number): number {
  if (significantBeforeCaret <= 0) {
    return display.startsWith("-") ? 1 : 0;
  }

  let seen = 0;
  for (let i = 0; i < display.length; i += 1) {
    const ch = display[i]!;
    if ((ch >= "0" && ch <= "9") || ch === "." || ch === "-") {
      seen += 1;
      if (seen >= significantBeforeCaret) {
        return i + 1;
      }
    }
  }

  return display.length;
}

export function countCurrencySignificantChars(raw: string): number {
  let count = 0;
  for (const ch of raw) {
    if ((ch >= "0" && ch <= "9") || ch === "." || ch === "-") {
      count += 1;
    }
  }
  return count;
}
